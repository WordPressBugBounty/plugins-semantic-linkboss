<?php

/**
 * Auth Handler
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

/**
 * Description of Auth
 *
 * @since 0.0.0
 */
class Auth {

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
	 * Construct
	 *
	 * @since 2.7.0
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'auth';
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
				'callback'            => array( $this, 'auth_check' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refresh-token',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'refresh_access_token' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
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
	 * Set Access Token
	 *
	 * @since 2.1.0
	 */
	public static function set_access_token( $access_token ) {
		update_option( 'linkboss_access_token', $access_token );
	}

	/**
	 * Get Access Token
	 *
	 * @since 2.1.0
	 */
	public static function get_access_token() {
		$access_token = get_option( 'linkboss_access_token', false );
		if ( ! $access_token ) {
			// Removed refresh token
			return get_option( 'linkboss_access_token', false );
		}
		return $access_token;
	}

	/**
	 * Check
	 *
	 * @since 2.7.0
	 */
	public function auth_check( WP_REST_Request $request ) {
		/**
		 * Retrieve parameters and nonce from the request
		 */
		$params = $request->get_params();
		$nonce  = $request->get_header( 'X-WP-Nonce' );

		/**
		 * Verify the nonce
		 */
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'Nonce verification failed, please refresh the page & try again!', 'semantic-linkboss' ),
				array(
					'status' => 403,
					'title'  => 'Nonce Failed!',
				)
			);
		}

		/**
		 * Get and sanitize API key
		 */
		$api_key = isset( $params['api_key'] ) ? sanitize_text_field( wp_unslash( $params['api_key'] ) ) : false;

		/**
		 * Check if API key is present
		 */
		if ( ! $api_key ) {
			return new WP_Error(
				'rest_missing_api_key',
				esc_html__( 'API key is required.', 'semantic-linkboss' ),
				array(
					'status' => 400,
					'title'  => 'Missing API Key',
				)
			);
		}

		/**
		 * Prepare the request arguments
		 */
		$api_url      = SEMANTIC_LB_AUTH_URL;
		$headers      = array(
			'Content-Type'     => 'application/json',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);
		$body         = array(
			'client'  => get_site_url(),
			'api_key' => $api_key,
		);
		$request_args = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'method'  => 'POST',
		);

		/**
		 * Make the request
		 */
		$response = wp_remote_post( $api_url, $request_args );

		/**
		 * Handle the response
		 */
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'rest_request_failed',
				esc_html__( 'Failed to connect to the authentication server.', 'semantic-linkboss' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Decode the response body
		 */
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ) );

		/**
		 * Handle specific response codes
		 */
		if ( 405 === $response_code ) {
			$message = isset( $response_body->message ) ? $response_body->message : esc_html__( 'Unexpected error occurred.', 'semantic-linkboss' );
			return new WP_Error(
				'rest_forbidden',
				wp_kses_post( $message ) . esc_html__( ' This site may have been removed from the LinkBoss app due to inactivity within the last 10 minutes. Please re-add the site through the app dashboard to continue.', 'semantic-linkboss' ),
				array( 'status' => 405 )
			);
		}

		if ( 200 !== $response_code ) {
			$message = isset( $response_body->message ) ? $response_body->message : esc_html__( 'Unknown error occurred.', 'semantic-linkboss' );
			return new WP_Error(
				'rest_error',
				wp_kses_post( $message ) . ' Error Code - ' . $response_code,
				array( 'status' => $response_code )
			);
		}

		/**
		 * If access token is present, save it
		 */
		if ( isset( $response_body->access ) ) {
			self::set_access_token( $response_body->access );
		}

		update_option( 'linkboss_api_key', $api_key );

		/**
		 * Clear any existing transient errors
		 *
		 * delete_transient( 'linkboss_auth_error' );
		 */

		/**
		 * Return success response
		 */
		return new WP_REST_Response(
			array(
				'status'  => 'success',
				'message' => esc_html__( 'API key verified successfully.', 'semantic-linkboss' ),
			),
			200
		);
	}


	/**
	 * Refresh Access Token by API KEY
	 *
	 * PATCH Request
	 *
	 * @since 0.0.5
	 */
	public static function refresh_access_token() {
		$api_url = SEMANTIC_LB_AUTH_URL;
		$api_key = get_option( 'linkboss_api_key', false );

		if ( ! $api_key ) {
			return;
		}

		$headers = array(
			'Content-Type'     => 'application/json',
			'method'           => 'POST',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'client'  => get_site_url(),
			'api_key' => $api_key,
		);

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body, true ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		// todo: handle error if ( wp_remote_retrieve_response_code( $response ) !== 200 )

		if ( isset( $res_body->access ) ) {
			self::set_access_token( $res_body->access );
		}
	}

	/**
	 * Get Access Token by Auth Code When server provides 401
	 *
	 * @version 0.0.9
	 */
	public static function get_tokens_by_auth_code() {
		$api_key = get_option( 'linkboss_api_key', false );

		if ( ! $api_key ) {
			return false;
		}

		$api_url = SEMANTIC_LB_AUTH_URL;

		$headers = array(
			'Content-Type'     => 'application/json',
			'method'           => 'POST',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'client'  => get_site_url(),
			'api_key' => $api_key,
		);

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body, true ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		$res_code = wp_remote_retrieve_response_code( $response );

		if ( isset( $res_body->access ) && 200 === $res_code ) {
			self::set_access_token( $res_body->access );
			return true;
		}

		return false;
	}
}
