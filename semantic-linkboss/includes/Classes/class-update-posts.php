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
	 * Update Posts
	 *
	 * @since 2.7.0
	 */
	public function get_update_posts_socket( $request ) {
		$sync_status = get_transient( 'linkboss_sync_ongoing' );

		if ( 'yes' === $sync_status ) {
			return new WP_Error( 'sync_ongoing', esc_html__( 'Sync Ongoing and that\'s why Post Update Blocked.', 'semantic-linkboss' ), array( 'status' => 403 ) );
		}

		$api_url      = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
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
			return Auth::get_tokens_by_auth_code();
		}

		if ( 200 !== $status ) {
			return new WP_Error( 'response_error', esc_html__( 'Response Error!', 'semantic-linkboss' ), array( 'status' => $status ) );
		}

		if ( empty( $res_body ) ) {
			return new WP_REST_Response(
				array(
					'status' => 'error',
					'title'  => 'Oops!',
					'msg'    => esc_html__( 'No data to Update', 'semantic-linkboss' ),
				),
				200
			);
		}

		$results = self::update_posts( $res_body );
		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Fetch Posts by PUT request
	 */
	public static function fetch_update_posts() {

		$sync_status = get_transient( 'linkboss_sync_ongoing' );

		if ( 'yes' === $sync_status ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title'  => 'Error!',
					'msg'    => esc_html__( 'Sync Ongoing and that\'s why Post Update Blocked.', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		$api_url      = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
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
			return Auth::get_tokens_by_auth_code();
		}

		if ( 200 !== $status ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title'  => 'Error! ' . $status,
					'msg'    => esc_html__( 'Response Error!', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		if ( empty( $res_body ) ) {

			// echo wp_json_encode(
			//  array(
			//      'status' => 'success',
			//      'title'  => 'Success!',
			//      'msg'    => esc_html__( 'No data to Update', 'semantic-linkboss' ),
			//  )
			// );
			wp_die();
		}

		self::update_posts( $res_body );
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
		$pattern = '/<div class="classic-post-content">(.*?)<\/div>/s';
		if ( preg_match( $pattern, $html_content, $matches ) ) {
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
	public static function update_posts( $data ) {
		/**
		 * Store the post IDs that were updated successfully
		 * id: "post_id"
		 */
		$updated_posts = array();
		$results       = array(); // Store results for all posts

		// Check if ACF support is enabled
		// $acf_enabled = get_option( 'linkboss_acf_enabled', false );
		// // Check if WooCommerce category sync is enabled
		// $woo_enabled = get_option( 'linkboss_woo_enabled', false );

		/**
		 * Assuming $data is the array containing post data
		 */
		foreach ( $data as $post_data ) {
			/**
			 * Check if this is a category archive and WooCommerce is enabled
			 */
			if ( isset( $post_data['postType'] ) && 'Category Archive' === $post_data['postType'] ) {
				$term_id = self::update_category_description( $post_data );
				if ( $term_id ) {
					// Add the term ID to updated posts
					array_push( $updated_posts, array( 'post_id' => (string) $term_id ) );
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
				// Handle Bricks
				update_post_meta( $post_id, '_bricks_page_content_2', wp_slash( $post_data['meta'] ) );
				$post_updated = true; // Mark as updated
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
							'post_modified'     => $date,
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
						'post_modified'     => $date,
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
							'post_modified'     => $date,
							'post_modified_gmt' => $date,
						),
						array( 'ID' => $post_id )
					);
				}
			}

			if ( $post_id && get_post( $post_id ) ) {
				wp_cache_delete( $post_id, 'posts' );
				clean_post_cache( $post_id );

				// Clear Elementor cache (latest supported method)
				if ( class_exists( '\Elementor\Plugin' ) ) {
					$elementor_instance = \Elementor\Plugin::instance();
					if (
						isset( $elementor_instance->files_manager ) &&
						method_exists( $elementor_instance->files_manager, 'clear_cache' )
					) {
						$elementor_instance->files_manager->clear_cache();
					}
				}
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
				$results[] = array(
					'status'   => 'success',
					'title'    => 'Post updated.',
					'msg'      => esc_html( $post_title ),
					'post_id'  => $post_id,
					'post_url' => get_permalink( $post_id ),
				);
			}
		}

		/**
				 * Send updated Post IDs to LinkBoss
				 */
		if ( ! empty( $updated_posts ) ) {
			self::send_updated_posts_ids( $updated_posts );
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
}

if ( class_exists( 'SEMANTIC_LB\Classes\Update_Posts' ) ) {
	\SEMANTIC_LB\Classes\Update_Posts::get_instance();
}
