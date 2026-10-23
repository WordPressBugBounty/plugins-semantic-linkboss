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
				'callback'            => array( $this, 'rest_refresh_token' ),
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
		 * Exchange the API key for an access token
		 */
		$exchange = self::exchange_key_for_token( $api_key );

		/**
		 * Handle transport failure
		 */
		if ( is_wp_error( $exchange ) ) {
			return new WP_Error(
				'rest_request_failed',
				esc_html__( 'Failed to connect to the authentication server.', 'semantic-linkboss' ),
				array( 'status' => 500 )
			);
		}

		$response_code = $exchange['code'];
		$response_body = $exchange['body'];

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

		delete_option( 'linkboss_site_disconnected' );

		update_option( 'linkboss_api_key', $api_key );

		// A different key may mean another account: sync everything again
		Connect::reset_sync_if_new_key();

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
	 * REST: Refresh the Access Token for the socket
	 *
	 * @since 2.8.6
	 */
	public function rest_refresh_token() {
		/**
		 * Browser-triggered (socket reconnects), so refresh at
		 * most once a minute per site — otherwise every open admin
		 * tab hammers the auth API while the socket is unreachable
		 */
		if ( ! get_transient( 'linkboss_token_refresh_lock' ) ) {
			set_transient( 'linkboss_token_refresh_lock', 1, MINUTE_IN_SECONDS );
			self::refresh_access_token();
		}

		return rest_ensure_response(
			array(
				'access_token' => self::get_access_token(),
			)
		);
	}

	/**
	 * Refresh Access Token by API KEY
	 *
	 * Used by the refresh cron & disconnect retry.
	 *
	 * @since 0.0.5
	 */
	public static function refresh_access_token() {
		$api_key = get_option( 'linkboss_api_key', false );

		if ( ! $api_key ) {
			return;
		}

		$exchange = self::exchange_key_for_token( $api_key );

		/**
		 * Keep the current token when the exchange fails;
		 * the next 401 response will retry silently.
		 */
		if ( is_wp_error( $exchange ) || 200 !== $exchange['code'] ) {
			return;
		}

		if ( isset( $exchange['body']->access ) ) {
			self::set_access_token( $exchange['body']->access );
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

		/**
		 * Backoff for cron-side callers: this fires from wp-cron
		 * paths that run on every page load, so without a lock a
		 * broken api_key silently hammers /api/v2/auth forever.
		 * Mirrors rest_refresh_token()'s 1/min lock but wider
		 * because wp-cron runs on every page view.
		 */
		if ( get_transient( 'linkboss_auth_retry_lock' ) ) {
			return false;
		}
		set_transient( 'linkboss_auth_retry_lock', 1, 5 * MINUTE_IN_SECONDS );

		$exchange = self::exchange_key_for_token( $api_key );

		if ( is_wp_error( $exchange ) ) {
			return false;
		}

		if ( 405 === $exchange['code'] ) {
			update_option( 'linkboss_site_disconnected', true );
			return false;
		}

		if ( isset( $exchange['body']->access ) && 200 === $exchange['code'] ) {
			self::set_access_token( $exchange['body']->access );
			return true;
		}

		return false;
	}

	/**
	 * POST to a LinkBoss auth endpoint as this site
	 *
	 * Adds the site identity (client) & plugin version.
	 *
	 * @param string $url     Auth endpoint URL.
	 * @param array  $body    Request fields.
	 * @param int    $timeout Request timeout in seconds.
	 * @return array|WP_Error Raw response or WP_Error on transport failure.
	 * @since 2.8.6
	 */
	private static function post_auth( $url, $body, $timeout = 5 ) {
		return wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
				),
				'body'    => wp_json_encode( array_merge( array( 'client' => get_site_url() ), $body ) ),
				'method'  => 'POST',
				'timeout' => $timeout,
			)
		);
	}

	/**
	 * Exchange an API Key for an Access Token
	 *
	 * Shared request helper for the auth endpoint.
	 *
	 * @param string $api_key The site API key.
	 * @return array|WP_Error array( 'code' => int, 'body' => object ) or WP_Error on transport failure.
	 * @since 2.8.6
	 */
	public static function exchange_key_for_token( $api_key ) {
		$response = self::post_auth( SEMANTIC_LB_AUTH_URL, array( 'api_key' => $api_key ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => json_decode( wp_remote_retrieve_body( $response ) ),
		);
	}

	/**
	 * Exchange a one-time Connect Code for credentials
	 *
	 * Exchanges the connect code delivered by the LinkBoss app
	 * redirect handshake for the site API key & access token.
	 *
	 * @param string $connect_code The one-time connect code.
	 * @return true|WP_Error
	 * @since 2.8.6
	 */
	public static function exchange_connect_code( $connect_code ) {
		$response = self::post_auth( SEMANTIC_LB_AUTH_CONNECT_URL, array( 'connect_code' => $connect_code ), 15 );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'rest_request_failed',
				esc_html__( 'Failed to connect to the authentication server.', 'semantic-linkboss' ),
				array( 'status' => 500 )
			);
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 200 !== $response_code ) {
			$error_code = isset( $response_body->error ) ? sanitize_key( $response_body->error ) : 'api_error';
			$message    = isset( $response_body->message ) ? $response_body->message : esc_html__( 'Connection failed.', 'semantic-linkboss' );

			$error_data = array( 'status' => $response_code );

			/**
			 * The already_registered exchange error carries the
			 * masked owner name to show in the admin notice
			 */
			if ( isset( $response_body->display_name ) ) {
				$error_data['display_name'] = sanitize_text_field( $response_body->display_name );
			}

			return new WP_Error(
				$error_code,
				wp_kses_post( $message ),
				$error_data
			);
		}

		if ( ! isset( $response_body->access ) || ! isset( $response_body->api_key ) ) {
			return new WP_Error(
				'api_error',
				esc_html__( 'Invalid response from the authentication server.', 'semantic-linkboss' ),
				array( 'status' => 500 )
			);
		}

		self::set_access_token( $response_body->access );
		update_option( 'linkboss_api_key', sanitize_text_field( $response_body->api_key ) );
		delete_option( 'linkboss_site_disconnected' );

		return true;
	}
}
