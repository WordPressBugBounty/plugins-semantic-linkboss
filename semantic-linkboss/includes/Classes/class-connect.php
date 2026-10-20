<?php

/**
 * Connect Handler
 *
 * Handles the LinkBoss app connect handshake: builds the
 * signup & login URLs, processes the app's redirect callback,
 * and disconnects the site from the app.
 *
 * @package SEMANTIC_LB
 * @since 2.8.6
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * Description of Connect
 *
 * @since 2.8.6
 */
class Connect {

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
	 * Handshake nonce transient prefix
	 */
	const NONCE_PREFIX = 'linkboss_connect_';

	/**
	 * Handshake nonce lifetime
	 */
	const NONCE_LIFETIME = 15 * MINUTE_IN_SECONDS;

	/**
	 * How long a minted nonce is reused before minting a new one;
	 * shorter than NONCE_LIFETIME so a reused nonce always has
	 * time left, and matching the UI's 10-min refetch
	 */
	const NONCE_REUSE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Code exchanges allowed per throttle window
	 */
	const THROTTLE_LIMIT = 6;

	/**
	 * Callback throttle window
	 */
	const THROTTLE_WINDOW = 10 * MINUTE_IN_SECONDS;

	/**
	 * Construct
	 *
	 * @since 2.8.6
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'auth';
		add_action( 'admin_init', array( $this, 'process_connect_callback' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register the routes
	 *
	 * @since 2.8.6
	 */
	public function register_rest_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/connect-url',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'get_connect_url' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/disconnect',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
	}

	/**
	 * Check the permissions for updating the settings
	 *
	 * @since 2.8.6
	 */
	public function update_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create a single-use handshake nonce
	 *
	 * Bound to the current user and valid for a short window,
	 * so the connect flow can only start inside an
	 * authenticated admin session.
	 *
	 * @since 2.8.6
	 */
	public static function create_connect_nonce() {
		$user_id = get_current_user_id();

		/**
		 * Reuse the user's recent nonce, so reloading the
		 * Connection tab doesn't stack up new transients
		 */
		$current = get_transient( self::NONCE_PREFIX . 'user_' . $user_id );

		if ( $current && self::get_nonce_owner( $current ) === $user_id ) {
			return $current;
		}

		$token = wp_generate_password( 40, false, false );

		set_transient( self::NONCE_PREFIX . $token, $user_id, self::NONCE_LIFETIME );
		set_transient( self::NONCE_PREFIX . 'user_' . $user_id, $token, self::NONCE_REUSE_WINDOW );

		return $token;
	}

	/**
	 * Get the user a handshake nonce was minted for
	 *
	 * @param string $token Handshake nonce.
	 * @since 2.8.6
	 * @return int User ID, 0 when the nonce is unknown or expired.
	 */
	private static function get_nonce_owner( $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9]{40}$/', $token ) ) {
			return 0;
		}

		return (int) get_transient( self::NONCE_PREFIX . $token );
	}

	/**
	 * Consume a handshake nonce (single-use)
	 *
	 * @param string $token Handshake nonce.
	 * @since 2.8.6
	 */
	private static function consume_nonce( $token ) {
		$user_id = get_current_user_id();

		delete_transient( self::NONCE_PREFIX . $token );

		if ( get_transient( self::NONCE_PREFIX . 'user_' . $user_id ) === $token ) {
			delete_transient( self::NONCE_PREFIX . 'user_' . $user_id );
		}
	}

	/**
	 * Build the callback URL the app redirects back to
	 *
	 * @param string $token Handshake nonce.
	 * @since 2.8.6
	 */
	public static function build_return_url( $token ) {
		return add_query_arg(
			array(
				'page'                   => 'semantic-linkboss',
				'linkboss_callback'      => 1,
				'linkboss_connect_nonce' => $token,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Get the connect configuration with a fresh handshake nonce
	 *
	 * @since 2.8.6
	 */
	public static function get_connect_config() {
		$token = self::create_connect_nonce();

		$signup_args = array(
			'site_connect'     => 1,
			'site_connect_url' => self::build_return_url( $token ),
			'domain'           => get_site_url(),
			'plugin_version'   => SEMANTIC_LB_VERSION,
		);

		return array(
			'signup_url'          => SEMANTIC_LB_APP_REGISTER_URL . '?' . http_build_query( $signup_args ),
			'login_url'           => SEMANTIC_LB_AUTH_LOGIN_URL,
			'forgot_password_url' => SEMANTIC_LB_APP_FORGOT_PASSWORD_URL,
			'redirect_url'        => self::build_return_url( $token ),
			'domain'              => get_site_url(),
			'plugin_version'      => SEMANTIC_LB_VERSION,
		);
	}

	/**
	 * Get the connect configuration
	 *
	 * Returns a fresh handshake nonce on every call.
	 *
	 * @since 2.8.6
	 */
	public function get_connect_url( WP_REST_Request $request ) {
		/**
		 * Retrieve the nonce from the request
		 */
		$nonce = $request->get_header( 'X-WP-Nonce' );

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

		return rest_ensure_response( self::get_connect_config() );
	}

	/**
	 * Process the connect callback from the LinkBoss app
	 *
	 * The app redirects back to wp-admin with either a one-time
	 * connect code or an error. Verify the handshake nonce,
	 * exchange the code for credentials, then redirect to a
	 * clean URL so no secrets linger in the browser history.
	 *
	 * @since 2.8.6
	 */
	public function process_connect_callback() {
		if ( ! isset( $_REQUEST['linkboss_callback'] ) ) {
			return;
		}

		/**
		 * Only administrators may complete the handshake
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=semantic-linkboss' ) );
			exit;
		}

		/**
		 * Every callback must carry a live handshake nonce minted
		 * for this admin. Anything else (forged links, expired
		 * sessions) is dropped without showing app-supplied text.
		 */
		$nonce = isset( $_REQUEST['linkboss_connect_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['linkboss_connect_nonce'] ) ) : '';

		if ( self::get_nonce_owner( $nonce ) !== get_current_user_id() ) {
			$this->redirect_with_status( 'error', 'nonce_invalid' );
		}

		/**
		 * Error callback from the app: surface the error while
		 * keeping the nonce alive so the login form can be retried.
		 * Not throttled — failed logins are rate-limited app-side.
		 */
		if ( isset( $_REQUEST['linkboss_error'] ) ) {
			$error_code   = sanitize_key( wp_unslash( $_REQUEST['linkboss_error'] ) );
			$display_name = isset( $_REQUEST['display_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['display_name'] ) ) : '';

			$this->redirect_with_status( 'error', $error_code, $display_name );
		}

		/**
		 * Validate the connect code
		 */
		$connect_code = isset( $_REQUEST['connect_code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['connect_code'] ) ) : '';

		if ( ! preg_match( '/^[A-Za-z0-9_-]{20,128}$/', $connect_code ) ) {
			$this->redirect_with_status( 'error', 'nonce_invalid' );
		}

		/**
		 * Throttle code exchanges per site
		 */
		if ( $this->is_throttled() ) {
			$this->redirect_with_status( 'error', 'rate_limited' );
		}

		/**
		 * Consume the handshake nonce (single-use)
		 */
		self::consume_nonce( $nonce );

		/**
		 * Exchange the one-time code for credentials
		 */
		$was_connected = (bool) get_option( 'linkboss_api_key' );
		$exchange      = Auth::exchange_connect_code( $connect_code );

		if ( is_wp_error( $exchange ) ) {
			$exchange_data = $exchange->get_error_data();
			$display_name  = ( is_array( $exchange_data ) && isset( $exchange_data['display_name'] ) ) ? $exchange_data['display_name'] : '';

			$this->redirect_with_status( 'error', $exchange->get_error_code(), $display_name );
		}

		/**
		 * Success — reopen the wizard for fresh connects so the
		 * first sync can auto-start; otherwise land on the
		 * Connection tab.
		 */
		if ( $was_connected ) {
			$this->redirect_with_status( 'success', '', '', admin_url( 'admin.php?page=semantic-linkboss&linkboss_connect=success#settings' ) );
		}

		$this->redirect_with_status( 'success', '', '', admin_url( 'admin.php?page=semantic-linkboss&tab=setupWizard&linkboss_connect=success' ) );
	}

	/**
	 * Disconnect the site from the LinkBoss app
	 *
	 * Best-effort remote unbind, then clear the local
	 * credentials & scheduled jobs.
	 *
	 * @since 2.8.6
	 */
	public function disconnect( WP_REST_Request $request ) {
		/**
		 * Retrieve the nonce from the request
		 */
		$nonce = $request->get_header( 'X-WP-Nonce' );

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
		 * Best-effort remote unbind; the site may already be
		 * deleted app-side, so the result is ignored.
		 */
		$access_token = Auth::get_access_token();

		if ( $access_token ) {
			$response = $this->delete_remote_site( $access_token );

			/**
			 * An expired access token answers 401 — refresh it
			 * from the stored key & retry once, so the domain is
			 * actually freed app-side
			 */
			if ( 401 === (int) wp_remote_retrieve_response_code( $response ) ) {
				Auth::refresh_access_token();

				$fresh_token = Auth::get_access_token();

				if ( $fresh_token && $fresh_token !== $access_token ) {
					$this->delete_remote_site( $fresh_token );
				}
			}
		}

		/**
		 * Clear local credentials
		 */
		delete_option( 'linkboss_api_key' );
		delete_option( 'linkboss_access_token' );
		delete_option( 'linkboss_site_disconnected' );

		/**
		 * Clear scheduled jobs
		 */
		wp_clear_scheduled_hook( 'linkboss_access_token' );
		wp_clear_scheduled_hook( 'linkboss_fetch_and_update_posts' );
		wp_clear_scheduled_hook( 'linkboss_ready_batch_for_process' );
		wp_clear_scheduled_hook( 'linkboss_init_posts_ids_batch' );

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => esc_html__( 'Site disconnected from LinkBoss.', 'semantic-linkboss' ),
			)
		);
	}

	/**
	 * Remove this site from the LinkBoss app
	 *
	 * @param string $access_token Bearer token.
	 * @since 2.8.6
	 * @return array|\WP_Error
	 */
	private function delete_remote_site( $access_token ) {
		return wp_remote_request(
			SEMANTIC_LB_REMOTE_URL . 'site',
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization'    => 'Bearer ' . $access_token,
					'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
				),
				'timeout' => 5,
			)
		);
	}

	/**
	 * Check & increment the exchange throttle
	 *
	 * @since 2.8.6
	 */
	private function is_throttled() {
		$throttle = (int) get_transient( 'linkboss_connect_throttle' );

		if ( self::THROTTLE_LIMIT <= $throttle ) {
			return true;
		}

		set_transient( 'linkboss_connect_throttle', $throttle + 1, self::THROTTLE_WINDOW );

		return false;
	}

	/**
	 * Redirect to a clean URL carrying only the status
	 *
	 * The connect code & handshake nonce are never carried
	 * past this redirect.
	 *
	 * @param string $status       'success' or 'error'.
	 * @param string $error_code   Error code when $status is 'error'.
	 * @param string $display_name Optional masked owner name.
	 * @param string $target       Explicit redirect target.
	 * @since 2.8.6
	 */
	private function redirect_with_status( $status, $error_code = '', $display_name = '', $target = '' ) {
		if ( '' === $target ) {
			$args = array( 'linkboss_connect' => $status );

			if ( '' !== $error_code ) {
				$args['linkboss_error'] = $error_code;
			}

			if ( '' !== $display_name ) {
				$args['display_name'] = $display_name;
			}

			$target = add_query_arg( $args, admin_url( 'admin.php?page=semantic-linkboss' ) );
		}

		wp_safe_redirect( $target );
		exit;
	}
}
