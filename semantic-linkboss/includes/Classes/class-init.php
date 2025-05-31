<?php
/**
 * Init Handler
 *
 * @package SEMANTIC_LB
 * @since 2.7.0
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

use SEMANTIC_LB\Traits\Global_Functions;


/**
 * Description of Init
 *
 * @since 2.7.0
 */
class Init {

	use Global_Functions;

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
	 * @since 2.7.0
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
	 * @since 2.7.0
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'init';
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
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'get_init' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set',
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'set_init' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
	}

	/**
	 * Check the permissions for getting the settings
	 *
	 * @since 2.7.0
	 */
	public function get_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check the permissions for updating the settings
	 *
	 * @since 2.7.0
	 */
	public function update_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Set Init
	 *
	 * @since 2.7.0
	 */
	public function get_init( WP_REST_Request $request ) {
		$params = $request->get_params();

		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : false;

		if ( ! $action ) {
			return new WP_Error( 'no_init', esc_html__( 'Oops, Init is not found.' ), array( 'status' => 404 ) );
		}

		switch ( $action ) {
			case 'init_batch_reports':
				$reports = self::sync_batch_init_reports();
				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => 'Init Reports fetched successfully',
						'reports' => $reports,
					),
					200
				);

			default:
				return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Set Init
	 *
	 * @since 2.7.0
	 */
	public function set_init( WP_REST_Request $request ) {
		$params = $request->get_params();

		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : false;

		if ( ! $action ) {
			return new WP_Error( 'no_init', esc_html__( 'Oops, Init is not found.' ), array( 'status' => 404 ) );
		}

		switch ( $action ) {
			case 'init_posts_ids_batch':
				$result = self::init_posts_ids_batch( true );
				
				// Check if we're using URL-based syncing
				$query_data = get_option( 'linkboss_custom_query', '' );
				$is_url_sync = isset( $query_data['sync_by'] ) && 'urls' === $query_data['sync_by'];
				
				if ($result === true) {
					// No more posts to process
					return rest_ensure_response(
						array(
							'status' => 'success',
							'message' => 'The contents are prepared, please press the Sync button below.',
						),
						200
					);
				} else if ($result === false) {
					// More posts to process in regular mode
					return rest_ensure_response(
						array(
							'status' => 'success',
							'message' => 'The contents are prepared, please press the Sync button below.',
							'has_post' => true,
						),
						200
					);
				} else if ($result === 'url_sync_complete') {
					// URL sync is complete, no more posts to process
					return rest_ensure_response(
						array(
							'status' => 'success',
							'message' => 'The contents are prepared, please press the Sync button below.',
							'has_post' => false,
						),
						200
					);
				} else if ($result === 'url_sync_in_progress') {
					// URL sync is in progress, but we've processed all URLs for this batch
					return rest_ensure_response(
						array(
							'status' => 'success',
							'message' => 'The contents are prepared, please press the Sync button below.',
							'has_post' => false,
						),
						200
					);
				}
				
				// Default fallback (should not reach here)
				return rest_ensure_response(
					array(
						'status' => 'success',
						'message' => 'The contents are prepared, please press the Sync button below.',
						'has_post' => false,
					),
					200
				);

			default:
				return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Init Table IDs
	 *
	 * It's the pair feature of class-ajax-init.php
	 */
	public static function init_posts_ids_batch( $api_call = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';
		
		/**
		 * Custom Query Builder
		 */
		$query_data = get_option( 'linkboss_custom_query', '' );
		$is_url_sync = isset( $query_data['sync_by'] ) && 'urls' === $query_data['sync_by'];
		
		if ($is_url_sync && isset($query_data['url_list']) && !empty($query_data['url_list'])) {
			// URL-based syncing
			$url_list = $query_data['url_list'];
			$urls = array_filter(array_map('trim', explode("\n", $url_list)));
			$posts = array();
			
			// Create an instance of Sync_Posts to use the enhanced URL to post ID function
			$sync_posts = new \SEMANTIC_LB\Classes\Sync_Posts();
			
			// Track if we added any new posts to the batch
			$added_new_posts = false;
			$processed_all_urls = true;
			
			// Get post IDs from URLs
			foreach ($urls as $url) {
				// Use the enhanced URL to post ID function
				$post_id = $sync_posts->enhanced_url_to_postid($url);
				
				if ($post_id > 0) {
					// Check if this post is already in the sync batch table
					// phpcs:ignore
					$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE post_id = %d", $post_id));
					
					if (!$exists) {
						$post = get_post($post_id);
						if ($post) {
							// Directly insert into the sync_batch table instead of adding to posts array
							$content_size = mb_strlen($post->post_content, '8bit');
							
							// Insert the post into the sync_batch table
							// phpcs:ignore
							$result = $wpdb->insert(
								$table_name,
								array(
									'post_id' => $post_id,
									'post_type' => $post->post_type,
									'post_status' => $post->post_status,
									'sent_status' => null,
									'content_size' => $content_size,
								),
								array('%d', '%s', '%s', '%s', '%d')
							);
							
							if ($result) {
								$added_new_posts = true;
								
								// Still add to posts array for batch processing
								$posts[] = array(
									'ID' => $post_id,
									'post_type' => $post->post_type,
									'post_content' => $post->post_content,
									'post_status' => $post->post_status
								);
							} else {
								$processed_all_urls = false;
							}
						} else {
							$processed_all_urls = false;
						}
					} else {
						// If the post exists in the sync batch table, reset its sent_status to null
						// phpcs:ignore
						$result = $wpdb->update(
							$table_name,
							array('sent_status' => null),
							array('post_id' => $post_id),
							array('%s'),
							array('%d')
						);
						
						if ($result) {
							// Add to posts array for batch processing
							$post = get_post($post_id);
							if ($post) {
								$posts[] = array(
									'ID' => $post_id,
									'post_type' => $post->post_type,
									'post_content' => $post->post_content,
									'post_status' => $post->post_status
								);
							}
						} else {
							$processed_all_urls = false;
						}
					}
				} else {
					$processed_all_urls = false;
				}
			}
			
			// For URL-based syncing, we need to return a special status
			if ($api_call) {
				if (count($posts) === 0) {
					// No posts were found or all posts were already in the sync batch table
					return 'url_sync_complete';
				} else {
					// We processed some posts, but there might be more
					return 'url_sync_in_progress';
				}
			}
		} else {
			// Post type-based syncing (original behavior)
			$post_type_query = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post', 'page' );

			/**
			 * Escape each item and wrap it in single quotes
			 */
			$post_type_query_escaped = array_map(
				function ( $type ) {
					return "'" . esc_sql( $type ) . "'";
				},
				$post_type_query
			);

			$post_type_query_string = implode( ', ', $post_type_query_escaped );

			$sql = "SELECT p.ID, p.post_type, p.post_content, p.post_status
					FROM {$wpdb->prefix}posts p
					LEFT JOIN {$wpdb->prefix}linkboss_sync_batch l ON p.ID = l.post_id
					WHERE l.post_id IS NULL
					AND p.post_type IN ($post_type_query_string)
					AND p.post_status = 'publish' LIMIT 100";

			// phpcs:ignore
			$posts = $wpdb->get_results( $sql, ARRAY_A );
		}

		/**
		 * Split the data into batches of 200
		 */
		$batches = array_chunk( $posts, 200 );

		$total_batches = count( $batches );
		$current_batch = 0;

		foreach ( $batches as $batch ) {
			/**
			 * Create the SQL query for inserting
			 */
			$insert_sql = "INSERT IGNORE INTO $table_name (post_id, post_type, post_status, sent_status, content_size) VALUES ";

			/**
			 * Use the array values to construct the query
			 */
			$values = array();

			foreach ( $batch as $post ) {
				$post_id = $post['ID'];
				$post_content = $post['post_content'];
				$post_type = $post['post_type'];
				$post_status = $post['post_status'];
				$content_size = mb_strlen( $post_content, '8bit' ); // Calculate size in bytes
				$__sent_status = null;

				/**
				 * Custom Query Builder
				 */

				$obj = new self();
				$_posts = $obj->get_post_pages( $post_type, $post_id, 1 );

				/**
				 * /Custom Query Builder
				 */

				if ( empty( $_posts ) ) {
					$__sent_status = 'ignore';
				} else {
					$__sent_status = null;
				}

				/**
				 * Escape and include the post_id, post_type, and content size in the query
				 */
				if ( null === $__sent_status ) {
					$values[] = "($post_id, '$post_type', '$post_status', NULL, $content_size)";
				} else {
					$values[] = "($post_id, '$post_type', '$post_status', 0, $content_size)";
				}
			}

			/**
			 * Combine the values and execute the query
			 */
			$insert_sql .= implode( ', ', $values );
			// phpcs:ignore
			$wpdb->query( $insert_sql );

			/**
			 * Update progress
			 */
			++$current_batch;
		}

		/**
		 * API Call when no more posts to sync
		 */

		if ( $api_call && $total_batches <= 0 ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sync Batch Init Reports
	 *
	 * @since 2.7.0
	 */
	public static function sync_batch_init_reports() {

		$reports = array();
		$query_data = get_option( 'linkboss_custom_query', '' );
		$is_url_sync = isset( $query_data['sync_by'] ) && 'urls' === $query_data['sync_by'];
		
		// Set sync method flag
		$reports['sync_method'] = $is_url_sync ? 'urls' : 'post_types';
		
		if ($is_url_sync) {
			// For URL-based syncing
			$url_list = isset( $query_data['url_list'] ) ? $query_data['url_list'] : '';
			$urls = array_filter(array_map('trim', explode("\n", $url_list)));
			$valid_urls_count = 0;
			
			// Create an instance of Sync_Posts to use the enhanced URL to post ID function
			$sync_posts = new \SEMANTIC_LB\Classes\Sync_Posts();
			
			// Count valid URLs (those that resolve to post IDs)
			foreach ($urls as $url) {
				if (!empty($url)) {
					$post_id = $sync_posts->enhanced_url_to_postid($url);
					if ($post_id > 0) {
						$valid_urls_count++;
						$post = get_post($post_id);
					}
				}
			}
			
			$reports['urls'] = $valid_urls_count;
			$reports['pages'] = 0; // Not relevant for URL syncing
			$reports['posts'] = 0; // Not relevant for URL syncing
		} else {
			// For post type-based syncing (original behavior)
			/**
			 * Get WordPress total page count
			 *
			 * Global Function Class
			 */
			$obj = new self();
			$reports['pages'] = (int) $obj->report_pages_count();
			$reports['posts'] = (int) $obj->report_posts_count();
			$reports['urls'] = 0; // Not relevant for post type syncing
		}

		/**
		 * Get total sync batch count from wp_linkboss_sync_batch table
		 */
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		// phpcs:ignore
		$total_batch_query = "SELECT COUNT(*) FROM {$table_name} WHERE post_status = 'publish' OR post_status = 'trash'";
		// phpcs:ignore
		$reports['total_queue_batch'] = (int) $wpdb->get_var( $total_batch_query );

		// phpcs:ignore
		$sql_sync = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status IS NULL AND (post_status = 'publish' OR post_status = 'trash')";
		// phpcs:ignore
		$reports['on_queue'] = (int) $wpdb->get_var( $sql_sync );

		// phpcs:ignore
		$sql_sync_done = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status = '1'";
		// phpcs:ignore
		$reports['sync_done'] = (int) $wpdb->get_var( $sql_sync_done );

		// phpcs:ignore
		$sql_need_sync = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status IS NULL AND (post_status = 'publish' OR post_status = 'trash')";
		// phpcs:ignore
		$reports['sync_remaing'] = (int) $wpdb->get_var( $sql_need_sync );
		
		if ($is_url_sync) {
			// For URL-based syncing, calculate queue_remaining based on URLs
			// If we have more URLs than total_queue_batch, we need to add more to the queue
			// Otherwise, we just need to sync what's already in the queue
			$reports['queue_remaining'] = max(0, $reports['urls'] - $reports['total_queue_batch']);
			
			// For URL-based syncing, we need to ensure sync_done is correctly reported
			// If we're syncing by URLs, sync_done should be the number of URLs that have been synced
			if ($reports['urls'] > 0) {
				// Calculate the percentage of URLs that have been synced
				$reports['sync_done'] = min($reports['sync_done'], $reports['urls']);
			}
		} else {
			// For post type-based syncing (original behavior)
			$reports['queue_remaining'] = (int) $reports['pages'] + (int) $reports['posts'] - (int) $reports['total_queue_batch'];
		}

		// Content size
		// phpcs:ignore
		$sql_content_size = "SELECT SUM(content_size) FROM {$table_name} WHERE sent_status = '1' OR sent_status IS NULL";
		// phpcs:ignore
		$content_size = $wpdb->get_var( $sql_content_size );
		$reports['content_size'] = self::bytes_to_size( $content_size ) >= 0 ? self::bytes_to_size( $content_size ) : 0 . ' (KB)';

		/**
		 * Elementor data
		 */
		// phpcs:ignore
		$sql_elementor_data = "SELECT COUNT(*)
                FROM {$wpdb->prefix}postmeta pm
                LEFT JOIN {$wpdb->prefix}linkboss_sync_batch l ON pm.meta_id = l.post_id
                WHERE pm.meta_key = '_elementor_data'";
		// phpcs:ignore
		$reports['elementor_data'] = (int) $wpdb->get_var( $sql_elementor_data );

		// Create the object if it doesn't exist yet
		if (!isset($obj)) {
			$obj = new self();
		}
		
		// For URL-based syncing, set total_categories to 0 as it's not relevant
		if ($is_url_sync) {
			$reports['total_categories'] = 0;
		} else {
			$reports['total_categories'] = (int) $obj->report_total_categories();
		}

		return $reports;
	}

	/**
	 * Bytes to Size
	 */
	public static function bytes_to_size( $bytes, $precision = 2 ) {
		$kilo_byte = 1024;
		$mega_byte = $kilo_byte * 1024;
		$giga_byte = $mega_byte * 1024;
		$tera_byte = $giga_byte * 1024;

		if ( ( $bytes >= 0 ) && ( $bytes < $kilo_byte ) ) {
			return $bytes . ' (B)';
		} elseif ( ( $bytes >= $kilo_byte ) && ( $bytes < $mega_byte ) ) {
			return round( $bytes / $kilo_byte, $precision ) . ' (KB)';
		} elseif ( ( $bytes >= $mega_byte ) && ( $bytes < $giga_byte ) ) {
			return round( $bytes / $mega_byte, $precision ) . ' (MB)';
		} elseif ( ( $bytes >= $giga_byte ) && ( $bytes < $tera_byte ) ) {
			return round( $bytes / $giga_byte, $precision ) . ' (GB)';
		} elseif ( $bytes >= $tera_byte ) {
			return round( $bytes / $tera_byte, $precision ) . ' (TB)';
		} else {
			return $bytes . ' (KB)';
		}
	}

	/**
	 * Total Categories
	 *
	 * @since 2.3.0
	 */
	public function report_total_categories() {
		$query_data = get_option( 'linkboss_custom_query', '' );
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post' );

		// Remove 'page' from the array
		if ( in_array( 'page', $post_type ) ) {
			$key = array_search( 'page', $post_type );
			unset( $post_type[ $key ] );
		}

		// Flatten post type array if only one type is present
		if ( count( $post_type ) === 1 ) {
			$post_type = array_shift( $post_type );
		}

		// Collect all categories
		$all_categories = array();

		if ( is_array( $post_type ) ) {
			// Multiple post types
			foreach ( $post_type as $type ) {
				$taxonomies = $this->get_hierarchical_taxonomies( $type );
				foreach ( $taxonomies as $taxonomy ) {
					$categories = get_terms(
						array(
							'taxonomy' => $taxonomy,
							'parent' => 0,
							'hide_empty' => false,
						)
					);

					if ( ! is_wp_error( $categories ) ) {
						$all_categories = array_merge( $all_categories, $categories );
					}
				}
			}
		} else {
			// Single post type
			$taxonomies = $this->get_hierarchical_taxonomies( $post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$categories = get_terms(
					array(
						'taxonomy' => $taxonomy,
						'parent' => 0,
						'hide_empty' => false,
					)
				);

				if ( ! is_wp_error( $categories ) ) {
					$all_categories = array_merge( $all_categories, $categories );
				}
			}
		}

		// Remove duplicate categories
		$all_categories = array_unique( $all_categories, SORT_REGULAR );
		// Count categories
		$total_cat = count( $all_categories );

		$cat = isset( $query_data['_categories'] ) ? count( $query_data['_categories'] ) : '';

		if ( $cat ) {
			$total_cat = $cat;
		}

		return $total_cat;
	}

	public function get_hierarchical_taxonomies( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$hierarchical_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->hierarchical ) {
				$hierarchical_taxonomies[] = $taxonomy->name;
			}
		}

		return $hierarchical_taxonomies;
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Init' ) ) {
	\SEMANTIC_LB\Classes\Init::get_instance();
}
