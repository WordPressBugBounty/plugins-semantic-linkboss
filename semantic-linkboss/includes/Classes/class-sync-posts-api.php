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

	public static $sync_speed = 512;

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

		$categories = get_categories(
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);

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
		$category   = isset( $params['category'] ) ? (int) sanitize_text_field( wp_unslash( $params['category'] ) ) : 0;
		$sync_done  = isset( $params['sync_done'] ) ? (int) sanitize_text_field( wp_unslash( $params['sync_done'] ) ) : 0;
		$force_data = isset( $params['force_data'] ) ? sanitize_text_field( wp_unslash( $params['force_data'] ) ) : false;

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
			'posts'    => $posts,
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
