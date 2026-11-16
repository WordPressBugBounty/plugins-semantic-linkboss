<?php

/**
 * Update Posts Handler
 * Fetch Posts from LinkBoss and Update to WordPress
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use SEMANTIC_LB\Classes\Updates;
use SEMANTIC_LB\Classes\Auth;

/**
 * Description of Update Posts
 *
 * @since 0.0.0
 */
class Update_Posts {
	private static $instance = null;

	/** Most seconds spent writing posts in one pull; the rest stays queued for the next one. */
	const WRITE_BUDGET_SECONDS = 15;

	/** Finished posts are confirmed to LinkBoss in groups of this size, so a hard stop redoes little. */
	const CONFIRM_EVERY = 5;

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Rest Base
	 *
	 * @var string
	 */

	protected $rest_base;

	/**
	 * Get Instance
	 *
	 * @since 0.0.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'update-posts';
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register the routes
	 *
	 * @since 2.7.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'get_update_posts_socket' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Check the permissions for getting the settings
	 *
	 * @since 2.7.0
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Update Posts (socket / "Sync from LinkBoss" button)
	 *
	 * @since 2.7.0
	 */
	public function get_update_posts_socket( $request ) {
		// Keep writing even if the browser tab closes mid-batch.
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}

		$pulled = self::pull_and_apply();

		if ( is_wp_error( $pulled ) ) {
			return $pulled;
		}

		if ( empty( $pulled['results'] ) ) {
			return new WP_REST_Response(
				array(
					'status' => 'error',
					'title'  => 'Oops!',
					'msg'    => esc_html__( 'No data to Update', 'semantic-linkboss' ),
				),
				200
			);
		}

		return new WP_REST_Response( $pulled['results'], 200 );
	}

	/**
	 * Fetch queued post updates from LinkBoss and write them to WordPress.
	 * Shared by the socket route, the cron job and remote "pull" commands.
	 *
	 * @param int $limit Most posts to fetch in this pull (0 = LinkBoss default). "Sync now" from the app asks for 20.
	 * @return array|WP_Error { results, updated, failed } or an error with an HTTP status.
	 */
	public static function pull_and_apply( $limit = 0 ) {
		$sync_status = get_transient( 'linkboss_sync_ongoing' );

		if ( 'yes' === $sync_status ) {
			return new WP_Error( 'sync_ongoing', esc_html__( 'Sync Ongoing and that\'s why Post Update Blocked.', 'semantic-linkboss' ), array( 'status' => 403 ) );
		}

		$api_url      = $limit > 0 ? add_query_arg( 'limit', (int) $limit, SEMANTIC_LB_POSTS_SYNC_URL ) : SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			Auth::get_tokens_by_auth_code();
			return new WP_Error( 'linkboss_auth_retry', esc_html__( 'Re-authenticating with LinkBoss. Please try again.', 'semantic-linkboss' ), array( 'status' => 503 ) );
		}

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$arg = array(
			'headers' => $headers,
			'method'  => 'GET',
		);

		$response = wp_remote_get( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$res_body = isset( $res_body['posts'] ) ? $res_body['posts'] : array();

		$status = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status ) {
			Auth::get_tokens_by_auth_code();
			return new WP_Error( 'linkboss_auth_retry', esc_html__( 'Re-authenticating with LinkBoss. Please try again.', 'semantic-linkboss' ), array( 'status' => 503 ) );
		}

		if ( 403 === $status ) {
			update_option( 'linkboss_site_disconnected', true );
			return new WP_Error( 'site_disconnected', esc_html__( 'Site has been removed from LinkBoss app. Please re-add it.', 'semantic-linkboss' ), array( 'status' => 403 ) );
		}

		if ( 200 !== $status ) {
			// Say what went wrong, so the app (and wp-admin) can show it.
			$detail = is_wp_error( $response )
				/* translators: %s: network or SSL error message */
				? sprintf( esc_html__( 'Could not reach LinkBoss: %s', 'semantic-linkboss' ), esc_html( $response->get_error_message() ) )
				/* translators: %s: HTTP status code */
				: sprintf( esc_html__( 'LinkBoss answered HTTP %s', 'semantic-linkboss' ), esc_html( (string) $status ) );

			return new WP_Error( 'response_error', $detail, array( 'status' => 502 ) );
		}

		if ( empty( $res_body ) ) {
			return array(
				'results' => array(),
				'updated' => 0,
				'failed'  => 0,
			);
		}

		$results = self::update_posts( $res_body, microtime( true ) + self::write_budget() );
		$updated = count(
			array_filter(
				$results,
				function ( $result ) {
					return 'success' === $result['status'];
				}
			)
		);

		return array(
			'results'  => $results,
			'updated'  => $updated,
			'failed'   => count( $results ) - $updated,
			// Fetched but not started within the time budget: still queued, sent with the next pull.
			'deferred' => max( 0, count( $res_body ) - count( $results ) ),
		);
	}

	/**
	 * Seconds to spend writing in one pull: 15, or half of PHP's time limit when that is lower, so the request always
	 * finishes on its own (no reliance on raising the host's limit).
	 */
	private static function write_budget() {
		$limit = (int) ini_get( 'max_execution_time' );

		return $limit > 0 ? min( self::WRITE_BUDGET_SECONDS, max( 5, (int) floor( $limit / 2 ) ) ) : self::WRITE_BUDGET_SECONDS;
	}

	/**
	 * Purge this post's page from common page caches (only those installed), so visitors see the new links.
	 * Never throws: a misbehaving cache plugin must not break the sync.
	 */
	private static function purge_page_caches( $post_id ) {
		try {
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id ); // WP Rocket
			}
			if ( has_action( 'litespeed_purge_post' ) ) {
				do_action( 'litespeed_purge_post', $post_id ); // LiteSpeed Cache
			}
			if ( function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $post_id ); // W3 Total Cache
			}
			if ( function_exists( 'wp_cache_post_change' ) ) {
				wp_cache_post_change( $post_id ); // WP Super Cache
			}
			if ( function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
				wpfc_clear_post_cache_by_id( $post_id ); // WP Fastest Cache
			}
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				sg_cachepress_purge_cache( get_permalink( $post_id ) ); // SiteGround Optimizer
			}
			if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				\WpeCommon::purge_varnish_cache( $post_id ); // WP Engine
			}
			if ( has_action( 'cache_enabler_clear_page_cache_by_post' ) ) {
				do_action( 'cache_enabler_clear_page_cache_by_post', $post_id ); // Cache Enabler
			}

			/**
			 * After LinkBoss wrote new content to a post: hosts and other caches can purge here.
			 *
			 * @param int $post_id The updated post.
			 */
			do_action( 'linkboss_post_content_updated', $post_id );
		} catch ( \Throwable $e ) {
			// Ignore: the content is saved; a cache will catch up on its own.
		}
	}

	/**
	 * Delete Elementor's generated CSS for this post only. Returns false when that isn't possible (the caller then
	 * clears Elementor's whole CSS cache once per batch instead of once per post).
	 */
	private static function clear_elementor_post_css( $post_id ) {
		try {
			if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
				\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
				return true;
			}
		} catch ( \Throwable $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Cron: fetch and apply queued post updates.
	 * Returns quietly: wp_die() here would end the whole wp-cron run (and other plugins' due events with it).
	 */
	public static function fetch_update_posts() {
		if ( get_option( 'linkboss_site_disconnected', false ) ) {
			return;
		}

		self::pull_and_apply();
	}

	public static function thrive_extract_content_before_more( $content, $post_id ) {
		// Pattern to match everything before the "More Tag"
		$pattern = '/(.*?)(<p class="tve_more_tag" id="more-' . $post_id . '">.*?<\/p>)/s';

		// Check if there's a "More Tag" in the content
		if ( preg_match( $pattern, $content, $matches ) ) {
			// Return content before "More Tag"
			return $matches[1]; // Content before "More Tag"
		}

		// If no "More Tag" is found, return the full content
		return $content;
	}

	/**
	 * Update Thrive data in WordPress.
	 *
	 * @param int   $post_id The ID of the post to update.
	 * @param array $data The data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update_thrive_data( $post_id, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$content_key     = 'tve_updated_post';
		$thrive_template = get_post_meta( $post_id, 'tve_landing_page', true );

		// see if this is a thrive templated page
		if ( ! empty( $thrive_template ) ) {
			// get the template key
			$content_key     = 'tve_updated_post_' . $thrive_template;
			$before_more_key = 'tve_content_before_more_' . $thrive_template;
		} else {
			$content_key     = 'tve_updated_post';
			$before_more_key = 'tve_content_before_more';
		}

		update_post_meta( $post_id, $content_key, $data );

		// Ensure $data['content'] is set and is a string
		$content = isset( $data['content'] ) && is_string( $data['content'] ) ? $data['content'] : $data;

		// Extract content before "More Tag" and update tve_content_before_more
		// $before_more_content = self::thrive_extract_content_before_more( $content, $post_id );
		// update_post_meta( $post_id, $before_more_key, $before_more_content );

		return true;
	}

	/**
	 * Extract classic content from HTML content.
	 *
	 * @param string $html_content The HTML content to extract from.
	 * @return string The extracted classic content.
	 */
	private static function extract_classic_content( $html_content ) {
		// First try to match content with the comment marker
		$pattern = '/<div class="classic-post-content">(.*?)<!-- END-CLASSIC-CONTENT --><\/div>/s';
		if ( preg_match( $pattern, $html_content, $matches ) ) {
			return $matches[1];
		}
		
		// Fallback to the original pattern if the comment marker is not found
		// This ensures backward compatibility with existing content
		$fallback_pattern = '/<div class="classic-post-content">(.*?)<\/div>/s';
		if ( preg_match( $fallback_pattern, $html_content, $matches ) ) {
			return $matches[1];
		}
		
		return '';
	}

	/**
	 * Extract ACF fields from HTML content.
	 *
	 * @param string $html_content The HTML content to extract from.
	 * @return array The extracted ACF fields.
	 */
	private static function extract_acf_fields( $html_content ) {
		$acf_fields = array();
		$pattern = '/<div class="acf-builder-item" data-type="([^"]+)" data-custom-field-name="([^"]+)">(.*?)<\/div>/s';

		if ( preg_match_all( $pattern, $html_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$field_type = $match[1];
				$field_name = $match[2];
				$content = $match[3];

				if ( $field_type === 'wysiwyg' ) {
					$field_content = trim( $content );
				} else {
					$content_pattern = '/<p class="custom-field-content">(.*?)<\/p>/s';
					$field_content = preg_match( $content_pattern, $content, $content_match ) ? trim( $content_match[1] ) : '';
				}

				$acf_fields[] = array(
					'name' => $field_name,
					'type' => $field_type,
					'content' => $field_content,
				);
			}
		}
		return $acf_fields;
	}

	/**
	 * Separate Elementor and ACF meta.
	 *
	 * @param array $meta The meta to separate.
	 * @return array The separated meta.
	 */
	private static function separate_elementor_and_acf_meta( $meta ) {
		$acf_meta = array();
		$elementor_meta = array();

		foreach ( $meta as $item ) {
			if ( isset( $item['custom_field_name'] ) ) {
				$acf_meta[] = $item;
			} else {
				$elementor_meta[] = $item;
			}
		}

		return array(
			'acf_meta' => $acf_meta,
			'elementor_meta' => $elementor_meta,
		);
	}

	/**
	 * Update category description
	 *
	 * @param array $item The category data to update.
	 * @return int|bool The term ID on success, false on failure.
	 */
	public static function update_category_description( $item ) {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		if ( ! $woo_enabled ) {
			return false;
		}

		$term_id = isset( $item['_postId'] ) ? intval( $item['_postId'] ) : 0;
		$new_description = isset( $item['content'] ) ? $item['content'] : '';

		// Determine if the term_id belongs to 'category' or 'product_cat'
		$taxonomies = array( 'category', 'product_cat' );
		$term = null;

		foreach ( $taxonomies as $taxonomy ) {
			$term = get_term( $term_id, $taxonomy );
			if ( ! is_wp_error( $term ) && $term ) {
				break;
			}
		}

		if ( is_wp_error( $term ) || ! $term ) {
			return false;
		}

		// Proceed with updating the category description
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'term_description', 'wp_kses_data' );

		$result = wp_update_term( $term_id, $term->taxonomy, array(
			'description' => wp_kses_post( $new_description ), // optionally, strip only bad tags, if necessary
		) );

		add_filter( 'pre_term_description', 'wp_filter_kses' );
		add_filter( 'term_description', 'wp_kses_data' );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return $term_id;
	}

	/**
	 * Server Response to Update Posts
	 */
	public static function update_posts( $data, $deadline = 0 ) {
		/**
		 * Store the post IDs that were updated successfully
		 * id: "post_id"
		 */
		$updated_posts = array();
		$results       = array(); // Store results for all posts

		// Confirmed to LinkBoss in small groups as we go (see CONFIRM_EVERY).
		$to_confirm = array();

		// Set when Elementor's per-post CSS couldn't be deleted: clear its whole cache once at the end instead.
		$clear_all_elementor_css = false;

		// Check if ACF support is enabled
		// $acf_enabled = get_option( 'linkboss_acf_enabled', false );
		// // Check if WooCommerce category sync is enabled
		// $woo_enabled = get_option( 'linkboss_woo_enabled', false );

		/**
		 * Assuming $data is the array containing post data
		 */
		foreach ( $data as $post_data ) {
			// Time budget used up: leave the rest queued for the next pull (always answer in time, never hit PHP's limit).
			if ( $deadline && microtime( true ) >= $deadline ) {
				break;
			}

			/**
			 * Check if this is a category archive and WooCommerce is enabled
			 */
			if ( isset( $post_data['postType'] ) && 'Category Archive' === $post_data['postType'] ) {
				$term_id = self::update_category_description( $post_data );
				if ( $term_id ) {
					// Add the term ID to updated posts
					array_push( $updated_posts, array( 'post_id' => (string) $term_id ) );
					$to_confirm[] = array( 'post_id' => (string) $term_id );
					$results[] = array(
						'status'   => 'success',
						'title'    => 'Category updated.',
						'msg'      => esc_html__( 'Category description updated for term ID: ', 'semantic-linkboss' ) . $term_id,
						'post_id'  => $term_id,
					);
				} else {
					$results[] = array(
						'status'  => 'error',
						'title'   => 'Category update failed.',
						'msg'     => esc_html__( 'Failed to update category description for term ID: ', 'semantic-linkboss' ) . $post_data['_postId'],
						'post_id' => $post_data['_postId'],
					);
				}
				continue;
			}

			/**
			 * Prepare the post data for updating
			 */
			$post_id       = $post_data['_postId'];
			$post_content  = isset( $post_data['content'] ) && ! empty( $post_data['content'] ) ? $post_data['content'] : '';
			$post_modified = $post_data['updatedAt'];
			$post_type     = isset( $post_data['postType'] ) ? $post_data['postType'] : '';
			$builder       = isset( $post_data['builder'] ) ? $post_data['builder'] : '';
			$meta          = isset( $post_data['meta'] ) ? $post_data['meta'] : null;

			$timestamp = strtotime( $post_modified );
			$date      = gmdate( 'Y-m-d H:i:s', $timestamp );

			$post_updated = false;

			// First check for builder types
			if ( 'acf-elementor' === $post_type && 'elementor' === $builder && ! empty( $meta ) ) {
				// Handle ACF-Elementor
				$separated_meta = self::separate_elementor_and_acf_meta( $meta );

				// Update Elementor meta
				if ( ! empty( $separated_meta['elementor_meta'] ) ) {
					$encoded_meta = addslashes( wp_json_encode( $separated_meta['elementor_meta'] ) );
					update_post_meta( $post_id, '_elementor_data', $encoded_meta );
				}

				// Update ACF fields
				if ( function_exists( 'update_field' ) && ! empty( $separated_meta['acf_meta'] ) ) {
					foreach ( $separated_meta['acf_meta'] as $acf_item ) {
						$field_content = $acf_item['settings']['editor'];
						update_field( $acf_item['custom_field_name'], $field_content, $post_id );
					}
				}

				$post_updated = true; // Assume success if meta is processed
			} elseif ( isset( $post_data['builder'] ) && 'elementor' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Regular Elementor without ACF
				$encoded_meta = addslashes( wp_json_encode( $post_data['meta'] ) );
				update_post_meta( $post_id, '_elementor_data', $encoded_meta );
				$post_updated = true;
			} elseif ( isset( $post_data['builder'] ) && 'bricks' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Handle Bricks - Using direct $wpdb update
				$value_to_save = $post_data['meta']; 

				if (is_array($value_to_save)) {
					$value_to_save = serialize($value_to_save);
				}

				global $wpdb;
				$meta_table = $wpdb->postmeta;
				$meta_key_to_update = '_bricks_page_content_2';
				
				$data_for_db = array(
					'meta_value' => $value_to_save 
				);
				$where_clause = array(
					'post_id'  => $post_id,
					'meta_key' => $meta_key_to_update
				);
				
				$existing_meta_id = $wpdb->get_var( $wpdb->prepare( 
					"SELECT meta_id FROM $meta_table WHERE post_id = %d AND meta_key = %s", 
					$post_id, 
					$meta_key_to_update 
				) );
				
				$result = false;
				if ( $existing_meta_id ) {
					$result = $wpdb->update( $meta_table, $data_for_db, $where_clause );
				} else {
					$data_for_insert = array_merge( $data_for_db, array(
						'post_id'  => $post_id,
						'meta_key' => $meta_key_to_update
					) );
					$result = $wpdb->insert( $meta_table, $data_for_insert );
				}
				
				if (false !== $result) { 
					wp_cache_delete($post_id, 'post_meta'); 
				}
				$post_updated = (false !== $result); 
			} elseif ( isset( $post_data['builder'] ) && 'oxygen' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Handle Oxygen
				$meta_exists = get_post_meta( $post_id, '_ct_builder_json', true );
				$meta_value  = is_array( $post_data['meta'] ) ? wp_slash( wp_json_encode( $post_data['meta'] ) ) : $post_data['meta'];
				$meta_key    = $meta_exists ? '_ct_builder_json' : 'ct_builder_json';
				update_post_meta( $post_id, $meta_key, $meta_value );
				$post_updated = true; // Mark as updated
			} elseif ( isset( $post_data['builder'] ) && 'thrive' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Handle Thrive
				self::update_thrive_data( $post_id, $post_content );
				$post_updated = true; // Mark as updated
			} elseif ( isset( $post_data['builder'] ) && 'beaver' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Handle Beaver
				$meta_data = $post_data['meta'];
				foreach ( $meta_data as $key => $value ) {
					$meta_data[ $key ] = (object) $value;
				}
				update_post_meta( $post_id, '_fl_builder_data', $meta_data );
				update_post_meta( $post_id, '_fl_builder_draft', $meta_data );
				$post_updated = true; // Mark as updated
			} elseif ( isset( $post_data['builder'] ) && 'divi' === $post_data['builder'] && ! empty( $post_content ) ) {
				// Handle Divi Builder (both 4.x and 5.x formats)
				// Get the original post content first
				$original_post = get_post( $post_id );
				$original_content = $original_post ? $original_post->post_content : '';

				// Detect Divi version from original content (more reliable than meta)
				$divi_version = '4.x'; // Default
				if ( ! empty( $original_content ) ) {
					if ( preg_match( '/<!-- wp:divi\//', $original_content ) ) {
						$divi_version = '5.x';
					} elseif ( preg_match( '/\[et_pb_/', $original_content ) ) {
						$divi_version = '4.x';
					}
				}

				// Fallback to meta if available
				if ( isset( $post_data['meta']['divi_version'] ) && ! empty( $post_data['meta']['divi_version'] ) ) {
					$divi_version = $post_data['meta']['divi_version'];
				}

				// Check if content is our wrapped format or raw shortcode/block format
				$is_wrapped_format = ( false !== strpos( $post_content, 'divi-text-module' ) );

				if ( $is_wrapped_format ) {
					// Extract modules from the wrapped content
					$updated_modules = self::extract_divi_modules_from_content( $post_content );

					if ( ! empty( $updated_modules ) && ! empty( $original_content ) ) {
						// Update based on Divi version
						if ( '5.x' === $divi_version ) {
							$new_content = self::update_divi_5x_content( $original_content, $updated_modules );
						} else {
							$new_content = self::update_divi_4x_content( $original_content, $updated_modules );
						}

						if ( $new_content !== $original_content ) {
							global $wpdb;
							$post_updated = $wpdb->update(
								$wpdb->posts,
								array(
									'post_content'      => $new_content,
									'post_modified'     => get_date_from_gmt( $date ),
									'post_modified_gmt' => $date,
								),
								array( 'ID' => $post_id )
							);

							if ( false !== $post_updated ) {
								$post_updated = true;
							}
						} else {
							$post_updated = true;
						}
					} else {
						// No modules extracted but wrapped format - nothing to update
						$post_updated = true;
					}
				} else {
					// Raw format - LinkBoss returned raw shortcode/block content
					// This happens when the original post had no et_pb_text/divi/text modules
					// These pages have no editable text content, so skip the update
					// to avoid breaking the shortcode escaping
					$post_updated = true; // Mark as success but don't actually update
				}
			} elseif ( 'acf-classic' === $post_type && 'classic' === $builder && ! empty( $post_content ) ) {
				// Handle ACF-Classic
				global $wpdb;

				$classic_content = self::extract_classic_content( $post_content );

				// Update post content if classic content is present
				if ( ! empty( $classic_content ) ) {
					$wpdb->update(
						$wpdb->posts,
						array(
							'post_content'      => $classic_content,
							'post_modified'     => get_date_from_gmt( $date ),
							'post_modified_gmt' => $date,
						),
						array( 'ID' => $post_id )
					);
					$post_updated = true;
				}

				// Update ACF fields if function exists
				if ( function_exists( 'update_field' ) ) {
					$acf_fields = self::extract_acf_fields( $post_content );
					foreach ( $acf_fields as $field ) {
						if ( update_field( $field['name'], $field['content'], $post_id ) ) {
							$post_updated = true;
						}
					}

					// Check if any ACF field exists
					if ( ! $post_updated && isset( $acf_fields[0] ) && get_field( $acf_fields[0]['name'], $post_id ) ) {
						$post_updated = true;
					}
				}

				// Update post modified dates
				$post_updated_db = $wpdb->update(
					$wpdb->posts,
					array(
						'post_modified'     => get_date_from_gmt( $date ),
						'post_modified_gmt' => $date,
					),
					array( 'ID' => $post_id )
				);

				if ( false === $post_updated_db ) {
					$post_updated = false;
				}
			} else {
				/**
				 * Update the post content for regular content
				 */
				if ( ! empty( $post_content ) ) {
					global $wpdb;
					$post_updated = $wpdb->update(
						$wpdb->posts,
						array(
							'post_content'      => $post_content,
							'post_modified'     => get_date_from_gmt( $date ),
							'post_modified_gmt' => $date,
						),
						array( 'ID' => $post_id )
					);

					// Verify the row really changed (the modified time we set is stored). Content itself isn't compared:
					// WordPress may adjust characters on save, and a false mismatch would retry the post forever.
					if ( is_int( $post_updated ) && $post_updated > 0 ) {
						$stored = $wpdb->get_var( $wpdb->prepare( "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
						if ( $stored !== $date ) {
							$post_updated = false;
						}
					}
				}
			}

			if ( $post_id && get_post( $post_id ) ) {
				wp_cache_delete( $post_id, 'posts' );
				clean_post_cache( $post_id );

				// Elementor: delete only this post's CSS (clearing every page's CSS per post was heavy on the server).
				if ( class_exists( '\Elementor\Plugin' ) && ! self::clear_elementor_post_css( $post_id ) ) {
					$clear_all_elementor_css = true;
				}

				// Page caches (WP Rocket, LiteSpeed, ...): purge this page so visitors see the new links.
				self::purge_page_caches( $post_id );
			}

			/**
					* Get the post title
					*/
			$post_title = get_the_title( $post_id );
			$post_title = mb_strimwidth( $post_title, 0, 100, '...' );

			/**
					* Check if the post was updated successfully
					*/
			if ( isset( $post_updated ) && false === $post_updated ) {
				$results[] = array(
					'status'  => 'error',
					'title'   => 'Failed to update!',
					'msg'     => esc_html( $post_title ),
					'post_id' => $post_id,
				);
			} else {
				/*
				 * Post updated successfully
				 */
				/**
				 * Update for new sync batch
				 */

				/**
				 * Store the post ID in the array
				 */
				array_push( $updated_posts, array( 'post_id' => $post_id ) );
				$to_confirm[] = array( 'post_id' => $post_id );
				$results[] = array(
					'status'   => 'success',
					'title'    => 'Post updated.',
					'msg'      => esc_html( $post_title ),
					'post_id'  => $post_id,
					'post_url' => get_permalink( $post_id ),
				);
			}

			// Confirm finished posts in small groups, so a hard stop (PHP limit, crash) only redoes a few.
			if ( count( $to_confirm ) >= self::CONFIRM_EVERY ) {
				self::send_updated_posts_ids( $to_confirm );
				$to_confirm = array();
			}
		}

		if ( $clear_all_elementor_css ) {
			try {
				$elementor_instance = \Elementor\Plugin::instance();
				if ( isset( $elementor_instance->files_manager ) && method_exists( $elementor_instance->files_manager, 'clear_cache' ) ) {
					$elementor_instance->files_manager->clear_cache();
				}
			} catch ( \Throwable $e ) {
				// Ignore: Elementor regenerates CSS on its own.
			}
		}

		/**
		 * Send the remaining updated Post IDs to LinkBoss
		 */
		if ( ! empty( $to_confirm ) ) {
			self::send_updated_posts_ids( $to_confirm );
		}

		return $results; // Return results for all posts
	}
	/**
	 * Send updated Post IDs to LinkBoss
	 * PATCH /api/plugin/sync : BODY - { posts: [{id: "post_id"}, {id: "post_id"}] }
	 */
	public static function send_updated_posts_ids( $updated_posts ) {
		$api_url      = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts' => $updated_posts,
		);

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body, true ),
			'method'  => 'PATCH',
		);

		$res = wp_remote_request( $api_url, $arg );

		$res_body = json_decode( wp_remote_retrieve_body( $res ) );
		$res_code = wp_remote_retrieve_response_code( $res );
	}

	/**
	 * Extract Divi modules from the content returned by LinkBoss.
	 *
	 * @param string $content The HTML content with divi-text-module divs.
	 * @return array Array of modules with index and content.
	 * @since 2.7.7
	 */
	private static function extract_divi_modules_from_content( $content ) {
		$modules = array();

		// Match <div class="divi-text-module" data-module-index="N" data-module-type="...">content<!-- END-DIVI-MODULE --></div>
		$pattern = '/<div class="divi-text-module" data-module-index="(\d+)" data-module-type="([^"]+)">(.*?)<!-- END-DIVI-MODULE --><\/div>/s';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$modules[] = array(
					'index'   => (int) $match[1],
					'type'    => $match[2],
					'content' => $match[3],
				);
			}
		}

		return $modules;
	}

	/**
	 * Update Divi 4.x shortcode content with modified modules.
	 *
	 * @param string $post_content The original post content.
	 * @param array  $updated_modules Array of modules with new content.
	 * @return string The updated post content.
	 * @since 2.7.7
	 */
	private static function update_divi_4x_content( $post_content, $updated_modules ) {
		// Create a map of index to new content
		$module_map = array();
		foreach ( $updated_modules as $module ) {
			$module_map[ $module['index'] ] = $module['content'];
		}

		$module_index = 0;
		$post_content = preg_replace_callback(
			'/\[et_pb_text\s+([^\]]*)\](.*?)\[\/et_pb_text\]/s',
			function ( $match ) use ( &$module_index, $module_map ) {
				$new_content = isset( $module_map[ $module_index ] )
					? $module_map[ $module_index ]
					: $match[2];

				$module_index++;
				return '[et_pb_text ' . $match[1] . ']' . $new_content . '[/et_pb_text]';
			},
			$post_content
		);

		return $post_content;
	}

	/**
	 * Update Divi 5.x Gutenberg block content with modified modules.
	 *
	 * @param string $post_content The original post content.
	 * @param array  $updated_modules Array of modules with new content.
	 * @return string The updated post content.
	 * @since 2.7.7
	 */
	private static function update_divi_5x_content( $post_content, $updated_modules ) {
		// Create a map of index to module data
		$module_map = array();
		foreach ( $updated_modules as $module ) {
			$module_map[ $module['index'] ] = $module;
		}

		$module_index = 0;
		$result = '';
		$last_end = 0;

		// Find all divi blocks (text, toggle, etc.) in order of appearance
		$all_blocks_pattern = '/<!-- wp:divi\/(text|toggle)\s+/';
		preg_match_all( $all_blocks_pattern, $post_content, $all_block_matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		if ( ! empty( $all_block_matches ) ) {
			foreach ( $all_block_matches as $block_match ) {
				$block_start_pos = $block_match[0][1];
				$block_type = $block_match[1][0]; // 'text' or 'toggle'

				// Add content before this block
				$result .= substr( $post_content, $last_end, $block_start_pos - $last_end );

				// Find the end of the block comment (the closing -->)
				$comment_end_pos = strpos( $post_content, '-->', $block_start_pos );
				if ( false === $comment_end_pos ) {
					$last_end = $block_start_pos;
					continue;
				}

				// Extract the full block start comment
				$block_start_comment = substr( $post_content, $block_start_pos, $comment_end_pos - $block_start_pos + 3 );

				// Check if this is a self-closing block (ends with /-->)
				$is_self_closing = preg_match( '/\/-->$/', $block_start_comment );

				// Parse JSON from block start comment (supports both --> and /--> endings)
				if ( preg_match( '/^<!-- wp:divi\/(text|toggle)\s+(\{.*\})\s*\/?-->$/s', $block_start_comment, $json_match ) ) {
					$block_type_from_json = $json_match[1];
					$json_str = $json_match[2];
					$attrs = json_decode( $json_str, true );

					if ( $is_self_closing ) {
						// Self-closing block: content is in JSON, no closing tag
						$block_end = $comment_end_pos + 3;
						$original_block = substr( $post_content, $block_start_pos, $block_end - $block_start_pos );

						// Check if JSON parsed successfully
						if ( null === $attrs && JSON_ERROR_NONE !== json_last_error() ) {
							// JSON parsing failed, keep original
							$result .= $original_block;
							$module_index++;
						} elseif ( isset( $module_map[ $module_index ] ) ) {
							$module_data = $module_map[ $module_index ];
							$new_content = $module_data['content'];

							// Update the content in the JSON attributes
							if ( isset( $attrs['content']['innerContent']['desktop']['value'] ) ) {
								$attrs['content']['innerContent']['desktop']['value'] = $new_content;
							}

							// Rebuild as self-closing block
							$new_block = '<!-- wp:divi/' . $block_type_from_json . ' ' . json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
							$result .= $new_block;
							$module_index++;
						} else {
							// No update for this module, keep original
							$result .= $original_block;
							$module_index++;
						}
					} else {
						// Regular block: content between tags, has closing tag
						$block_content_start = $comment_end_pos + 3;

						// Find matching closing comment
						$end_pattern = '/<!-- \/wp:divi\/' . preg_quote( $block_type, '/' ) . ' -->/';
						if ( ! preg_match( $end_pattern, $post_content, $end_match, PREG_OFFSET_CAPTURE, $block_content_start ) ) {
							$last_end = $block_start_pos;
							continue;
						}

						$block_content_end = $end_match[0][1];
						$block_end = $block_content_end + strlen( '<!-- /wp:divi/' . $block_type . ' -->' );

						// Extract original block
						$original_block = substr( $post_content, $block_start_pos, $block_end - $block_start_pos );

						// Check if JSON parsed successfully
						if ( null === $attrs && JSON_ERROR_NONE !== json_last_error() ) {
							// JSON parsing failed, keep original
							$result .= $original_block;
							$module_index++;
						} elseif ( isset( $module_map[ $module_index ] ) ) {
							$module_data = $module_map[ $module_index ];
							$new_content = $module_data['content'];

							// Update the content in the JSON attributes
							if ( isset( $attrs['content']['innerContent']['desktop']['value'] ) ) {
								$attrs['content']['innerContent']['desktop']['value'] = $new_content;
							}

							// Rebuild block with JSON_UNESCAPED_UNICODE to preserve characters
							$new_block = '<!-- wp:divi/' . $block_type_from_json . ' ' . json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' -->' . $new_content . '<!-- /wp:divi/' . $block_type_from_json . ' -->';
							$result .= $new_block;
							$module_index++;
						} else {
							// No update for this module, keep original
							$result .= $original_block;
							$module_index++;
						}
					}

					$last_end = $block_end;
				} else {
					// Keep original if parsing fails - need to find where block ends
					if ( $is_self_closing ) {
						$block_end = $comment_end_pos + 3;
					} else {
						// Try to find closing tag
						$end_pattern = '/<!-- \/wp:divi\/' . preg_quote( $block_type, '/' ) . ' -->/';
						if ( preg_match( $end_pattern, $post_content, $end_match, PREG_OFFSET_CAPTURE, $comment_end_pos + 3 ) ) {
							$block_end = $end_match[0][1] + strlen( '<!-- /wp:divi/' . $block_type . ' -->' );
						} else {
							$block_end = $comment_end_pos + 3;
						}
					}
					$result .= substr( $post_content, $block_start_pos, $block_end - $block_start_pos );
					$module_index++;
					$last_end = $block_end;
				}
			}
		}

		// Add remaining content
		$result .= substr( $post_content, $last_end );

		return $result;
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Update_Posts' ) ) {
	\SEMANTIC_LB\Classes\Update_Posts::get_instance();
}
