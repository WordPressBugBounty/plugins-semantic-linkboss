<?php
/**
 * Sync Posts API Class
 *
 * @package SEMANTIC_LB
 * @since 2.0.3
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

use SEMANTIC_LB\Classes\Auth;
use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Sync Posts API Class
 *
 * @since 2.0.3
 */
class Sync_Posts_Api extends Sync_Posts {

	use Global_Functions;

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
	 * Class constructor
	 *
	 * @since 2.0.3
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'sync';
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
				'callback'            => array( $this, 'handle_sync' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
				// 'permission_callback' => '__return_true',
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
	 * Set Sync
	 *
	 * @since 2.7.0
	 */
	public function handle_sync( WP_REST_Request $request ) {
		$params = $request->get_params();

		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : false;

		if ( ! $action ) {
			return new WP_Error( 'no_init', esc_html__( 'Oops, Init is not found.' ), array( 'status' => 404 ) );
		}

		switch ( $action ) {
			case 'sync_init':
				return $this->sync_init( $params );
			case 'sync_finish':
				return $this->sync_finish();
			case 'prepare_batch_for_sync':
				return $this->prepare_batch_for_sync();
			default:
				return new WP_Error( 'no_action', esc_html__( 'Oops, Action is not found.' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Call Batch Process for Sync
	 */
	public function get_batch_process( $params ) {

		if ( isset( $params['force_data'] ) && ( 'yes' === sanitize_text_field( wp_unslash( $params['force_data'] ) ) ) ) {
			self::$force_data = true;
		}

		$batches = $this->ready_batch_for_process();

		$response = array(
			'status'  => 'success',
			'batches' => $batches,
		);

		update_option( 'linkboss_sync_batch', $batches );

		return $batches;
		/*
		return rest_ensure_response(
			array(
				'status' => 'success',
				'data'   => $response,
			),
			200
		);
		*/
	}

	/**
	 * Prepare the content of categories for sync.
	 */
	public function ready_wp_category_contents_for_sync() {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		if ( ! $woo_enabled ) {
			return;
		}

		$taxonomies = array( 'category', 'product_cat' ); // Include 'product_cat' for WooCommerce
		$categories_data = array();

		foreach ( $taxonomies as $taxonomy ) {
			$categories = get_terms( array(
				'taxonomy'   => $taxonomy,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => false,
			) );

			if ( is_wp_error( $categories ) ) {
				// error_log( 'Error retrieving terms: ' . $categories->get_error_message() );
				return; // Exit if there's an error
			}

			foreach ( $categories as $category ) {
				if ( ! empty( $category->description ) ) {
					// Prepare the data in the specified format
					$categories_data[] = array(
						'_postId'    => $category->term_id,
						'category'   => wp_json_encode( array( $category->term_id ) ),
						'title'      => $category->name,
						'content'    => $category->description,
						'postType'   => 'Category Archive',
						'postStatus' => 'publish',
						'createdAt'  => current_time( 'mysql' ),
						'updatedAt'  => current_time( 'mysql' ),
						'url'        => get_term_link( $category ),
						'builder'    => 'classic',
						'meta'       => null,
					);
				}
			}
		}

		if ( ! empty( $categories_data ) ) {
			$response = $this->send_group( $categories_data, '', false );

			if ( is_wp_error( $response ) ) {
				// error_log( 'Error sending group: ' . $response->get_error_message() );
				return;
			}
		}
	}

	/**
	 * Aggregate category sync methods
	 */
	public function trigger_category_sync() {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		if ( ! $woo_enabled ) {
			return;
		}

		$this->ready_wp_category_contents_for_sync();
	}

	public function prepare_batch_for_sync() {

		self::$show_msg = true;
		/**
		 * Data Idea
		 * $batches = [ [ 1, 2, 3, 4 ], [ 5, 6, 7, 8 ], [ 9, 10, 11, 12 ] ];
		 * $batches = [ [ 5, 6, 7, 8 ], [ 9, 10, 11, 12 ] ];
		 * $batches    = [ [ 9, 10, 11, 12 ] ];
		 */
		$batches = get_option( 'linkboss_sync_batch', array() );

		$sent_batch = isset( $batches[0] ) ? $batches[0] : array();
		$next_batch = array_slice( $batches, 1 );

		$res = $this->ready_wp_posts_for_sync( $sent_batch );

		$response = array(
			'status'       => 'success',
			'sent_batch'   => $sent_batch,
			'next_batches' => count( $next_batch ) > 0 ? $next_batch : false,
			'batch_length' => count( $next_batch ),
			'has_batch'    => count( $next_batch ) > 0 ? 'yes' : false,
			'srv_status'   => $res,
		);

		update_option( 'linkboss_sync_batch', $next_batch );

		return rest_ensure_response(
			array(
				'status' => 'success',
				'data'   => $response,
			)
		);
	}

	/**
	 * Ready WordPress Posts as JSON
	 * Ready posts by Batch
	 *
	 * @since 2.0.3
	 */
	public function ready_wp_posts_for_sync( $batch ) {
		/**
		 * $batch is an array of post_id
		 * Example: [ 3142, 3141, 3140 ]
		 * $batch = [ 3653, 4025, 4047 ];
		 */

		$posts = $this->get_post_pages( false, $batch, -1, array( 'publish', 'trash' ) );

		$prepared_posts = $this->prepared_posts_for_sync( $posts );

		/**
		 * Remove null values and reindex the array
		 * Because of Oxygen Builder
		 */
		$prepared_posts = array_values( array_filter( $prepared_posts ) );

		if ( count( $prepared_posts ) <= 0 ) {
			return array(
				'status' => 200,
				'title'  => 'Success',
				'msg'    => esc_html__( 'Posts are up to date.', 'semantic-linkboss' ),
			);
		}

		return self::send_group( $prepared_posts, $batch, false );
	}

	/**
	 * Send Categories as JSON on last batch
	 *
	 * @since 2.0.3
	 */
	public function ready_wp_categories_for_sync() {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		// Get regular WordPress categories
		$categories = get_categories(
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

		// Get WooCommerce product categories if enabled
		if ( $woo_enabled && taxonomy_exists( 'product_cat' ) ) {
			$product_categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'orderby'    => 'name',
					'order'      => 'ASC',
					'hide_empty' => false,
				)
			);

			// Merge categories if product categories exist and no error occurred
			if ( ! is_wp_error( $product_categories ) && ! empty( $product_categories ) ) {
				$categories = array_merge( $categories, $product_categories );
			}
		}

		$categories_data = array_map(
			function ( $category ) {
				return array(
					'categoryId' => $category->term_id,
					'name'       => $category->name,
					'slug'       => $category->slug,
				);
			},
			$categories
		);

		self::$show_msg = true;
		$response       = $this->send_group( $categories_data, '', true );

		return $response;
	}

	/**
	 * Sync Init
	 *
	 * @since 2.2.0
	 */
	public function sync_init( $params ) {
		$posts      = isset( $params['posts'] ) ? (int) sanitize_text_field( wp_unslash( $params['posts'] ) ) : 0;
		$pages      = isset( $params['pages'] ) ? (int) sanitize_text_field( wp_unslash( $params['pages'] ) ) : 0;
		$sync_done  = isset( $params['sync_done'] ) ? (int) sanitize_text_field( wp_unslash( $params['sync_done'] ) ) : 0;
		$force_data = isset( $params['force_data'] ) ? sanitize_text_field( wp_unslash( $params['force_data'] ) ) : false;

		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		global $wpdb;

		// Get category count based on WooCommerce toggle setting
		if ( $woo_enabled ) {
			// Query to count both regular categories and WooCommerce product categories
			$category_posts_query = $wpdb->prepare(
				"SELECT term_id FROM {$wpdb->terms} WHERE term_id IN (SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s OR taxonomy = %s)",
				'category',
				'product_cat'
			);
		} else {
			// Original query for just regular categories
			$category_posts_query = $wpdb->prepare(
				"SELECT term_id FROM {$wpdb->terms} WHERE term_id IN (SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s)",
				'category'
			);
		}

		$category = count( $wpdb->get_results( $category_posts_query ) );

		$status  = ( 0 === $sync_done ) ? 'complete' : 'partial';
		$status  = ( 'yes' === $force_data ) ? 'complete' : $status;
		$api_url = SEMANTIC_LB_SYNC_INIT;

		$access_token = Auth::get_access_token();

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts'    => $woo_enabled ? $posts + $category : $posts, // Only include category count if WooCommerce sync is enabled
			'pages'    => $pages,
			'category' => $category,
			'status'   => $status,
		);

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			Auth::get_tokens_by_auth_code();
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg = isset( $res_body->message ) ? $res_body->message : '';
			return new WP_Error( 'error', esc_html( $msg . '. Error Code - ' . wp_remote_retrieve_response_code( $response ) ), array( 'status' => 400 ) );
		}

		/**
		 * Ensure batch processing completes before returning response
		 *
		 * First get the batch process for sync with App Server
		 */
		$batch = $this->get_batch_process( array( 'force_data' => $force_data ) );

		/**
		 * Validate batch result if necessary
		 */
		if ( empty( $batch ) ) {
			return new WP_Error( 'error', esc_html__( 'There is no waiting Batch, all data sync already.', 'semantic-linkboss' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => esc_html( $res_body->message ),
				'batch'   => $batch,
			),
			200
		);
	}


	/**
	 * Sync Finished
	 *
	 * @since 2.2.0
	 */
	public function sync_finish() {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		// Sync WooCommerce product category contents if enabled
		if ( $woo_enabled ) {
			$this->ready_wp_category_contents_for_sync();
		}

		/**
		 * Request Categories Sync
		 */
		$categories_res = $this->ready_wp_categories_for_sync();

		if ( 200 !== $categories_res['status'] && 201 !== $categories_res['status'] ) {
			return rest_ensure_response(
				array(
					'status' => 'error',
					'title'  => esc_html( 'Error - ' . $categories_res['status'] ),
					'msg'    => esc_html( $categories_res['msg'] ),
				)
			);
		}

		$api_url      = SEMANTIC_LB_SYNC_FINISH;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
		}

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array();

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg    = isset( $res_body->message ) ? $res_body->message : '';
			$remain = isset( $res_body->remain ) ? $res_body->remain : 0;
			$notify = isset( $res_body->notify ) ? $res_body->notify : false;

			if ( $notify ) {
				return rest_ensure_response(
					array(
						'status' => 'error',
						'title'  => esc_html( 'Error - ' . wp_remote_retrieve_response_code( $response ) ),
						'msg'    => esc_html( $msg . '. Remaining Contents- ' . $remain ),
					),
					400
				);
			}
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'title'   => 'Sync Finished!',
				// 'message' => esc_html( $res_body->message ),
				'message' => esc_html__( 'If you need to re-sync, follow this guide - ', 'semantic-linkboss' ) . '<a href="https://www.youtube.com/watch?v=3VnCHXizv1U" target="_blank">https://www.youtube.com/watch?v=3VnCHXizv1U</a>',
			),
			200
		);
	}
}
