<?php

/**
 * Remote commands from the LinkBoss app
 * Signed "pull now" webhook: LinkBoss asks the plugin to fetch its queued post updates right away
 * instead of waiting for WP-cron. The request carries no content; posts are still fetched over the
 * plugin's own authenticated call to LinkBoss, so a forged or replayed request can only trigger an extra pull.
 *
 * @package SEMANTIC_LB
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Remote
 */
class Remote {

	const SECRET_OPTION       = 'linkboss_webhook_secret';
	const REGISTERED_FLAG     = 'linkboss_remote_registered';
	const MAX_CLOCK_SKEW      = 300;
	const NONCE_TTL           = 600;
	const REGISTER_URL_SUFFIX = 'remote';

	private static $instance = null;

	/**
	 * Why the last register() call got no secret (shown in the 401 so LinkBoss can tell the user).
	 *
	 * @var string
	 */
	private static $register_error = '';

	/**
	 * Get Instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register the routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'linkboss/v1',
			'/remote',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_command' ),
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * Verify the LinkBoss signature: sha256=HMAC(secret, "{timestamp}.{raw body}"), a fresh timestamp and an unused nonce.
	 */
	public function verify_signature( WP_REST_Request $request ) {
		$timestamp = (int) $request->get_header( 'x_linkboss_timestamp' );
		$signature = (string) $request->get_header( 'x_linkboss_signature' );
		$body      = $request->get_body();

		if ( ! $timestamp || ! $signature || abs( time() - $timestamp ) > self::MAX_CLOCK_SKEW ) {
			return self::bad_signature();
		}

		$secret = get_option( self::SECRET_OPTION, '' );

		// First command on this site (or the secret was lost): fetch it now. A request that reaches here is a
		// LinkBoss command, so don't wait for the hourly cron back-off; just limit it to one attempt a minute.
		if ( ! $secret && false === get_transient( 'linkboss_remote_register_try' ) ) {
			set_transient( 'linkboss_remote_register_try', 1, MINUTE_IN_SECONDS );
			$secret = self::register();
		}

		if ( ! $secret ) {
			$why = self::$register_error ? self::$register_error : __( 'retrying shortly', 'semantic-linkboss' );
			return new WP_Error(
				'linkboss_secret_unavailable',
				/* translators: %s: why the secret couldn't be fetched */
				sprintf( esc_html__( 'Could not get the LinkBoss webhook secret (%s).', 'semantic-linkboss' ), esc_html( $why ) ),
				array( 'status' => 401 )
			);
		}

		if ( ! hash_equals( self::sign( $secret, $timestamp, $body ), $signature ) ) {
			// LinkBoss may have rotated its key: refresh once per hour at most, then check again.
			if ( false !== get_transient( 'linkboss_remote_rekeyed' ) ) {
				return self::bad_signature();
			}
			set_transient( 'linkboss_remote_rekeyed', 1, HOUR_IN_SECONDS );

			$secret = self::register();

			if ( ! $secret || ! hash_equals( self::sign( $secret, $timestamp, $body ), $signature ) ) {
				return self::bad_signature();
			}
		}

		$params = json_decode( $body, true );
		$nonce  = is_array( $params ) && isset( $params['nonce'] ) ? (string) $params['nonce'] : '';

		if ( '' === $nonce ) {
			return self::bad_signature();
		}

		$nonce_key = 'linkboss_remote_nonce_' . md5( $nonce );

		if ( false !== get_transient( $nonce_key ) ) {
			return self::bad_signature();
		}

		set_transient( $nonce_key, 1, self::NONCE_TTL );

		return true;
	}

	/**
	 * Run a verified command.
	 */
	public function handle_command( WP_REST_Request $request ) {
		$params  = json_decode( $request->get_body(), true );
		$command = is_array( $params ) && isset( $params['command'] ) ? $params['command'] : '';

		if ( 'pull' !== $command ) {
			return new WP_Error( 'linkboss_unknown_command', esc_html__( 'Unknown command.', 'semantic-linkboss' ), array( 'status' => 400 ) );
		}

		// Batch size asked for by LinkBoss (20 per "Sync now" click), kept within 1-100.
		$limit  = is_array( $params ) && isset( $params['limit'] ) ? max( 1, min( 100, (int) $params['limit'] ) ) : 0;
		// Keep writing even if LinkBoss stops waiting, so a batch is never cut off halfway.
		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}

		$pulled = Update_Posts::pull_and_apply( $limit );

		if ( is_wp_error( $pulled ) ) {
			return $pulled;
		}

		return new WP_REST_Response(
			array(
				'command'        => 'pull',
				'updated'        => $pulled['updated'],
				'failed'         => $pulled['failed'],
				'plugin_version' => SEMANTIC_LB_VERSION,
			),
			200
		);
	}

	/**
	 * Tell LinkBoss where the remote route lives (the WordPress install, which can differ from the
	 * public URL on headless sites) and store the secret it returns.
	 *
	 * @return string The secret, or '' on failure.
	 */
	public static function register() {
		$access_token = Auth::get_access_token();

		self::$register_error = '';

		// Not connected yet (e.g. mid-connect): try again on the next cron run or webhook, without the hour back-off.
		if ( ! $access_token ) {
			self::$register_error = __( 'site not connected: no access token', 'semantic-linkboss' );
			return '';
		}

		// Retry in an hour on failure, so an older LinkBoss API or a network issue isn't hit every cron run.
		set_transient( self::REGISTERED_FLAG, 1, HOUR_IN_SECONDS );

		$response = wp_remote_post(
			SEMANTIC_LB_REMOTE_URL . self::REGISTER_URL_SUFFIX,
			array(
				'headers' => array(
					'Content-Type'     => 'application/json',
					'Authorization'    => "Bearer $access_token",
					'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
				),
				'body'    => wp_json_encode( array( 'endpoint' => rest_url( 'linkboss/v1/remote' ) ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			/* translators: %s: network or SSL error */
			self::$register_error = sprintf( __( 'could not reach LinkBoss: %s', 'semantic-linkboss' ), $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			/* translators: %s: HTTP status code */
			self::$register_error = sprintf( __( 'LinkBoss answered HTTP %s', 'semantic-linkboss' ), $code );
			return '';
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$secret = is_array( $body ) && isset( $body['secret'] ) ? sanitize_text_field( $body['secret'] ) : '';

		if ( ! $secret ) {
			self::$register_error = __( 'LinkBoss sent no secret', 'semantic-linkboss' );
			return '';
		}

		update_option( self::SECRET_OPTION, $secret, false );
		set_transient( self::REGISTERED_FLAG, 1, DAY_IN_SECONDS );

		return $secret;
	}

	/**
	 * Re-register daily (keeps LinkBoss's copy of the endpoint current if the site URL changes or its cache is lost).
	 * Cheap enough to call from the 2-minute cron.
	 */
	public static function maybe_register() {
		if ( false === get_transient( self::REGISTERED_FLAG ) ) {
			self::register();
		}
	}

	/**
	 * Signature for a timestamp and raw body.
	 */
	private static function sign( $secret, $timestamp, $body ) {
		return 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Generic rejection (doesn't say which check failed).
	 */
	private static function bad_signature() {
		return new WP_Error( 'linkboss_bad_signature', esc_html__( 'Invalid LinkBoss signature.', 'semantic-linkboss' ), array( 'status' => 401 ) );
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Remote' ) ) {
	\SEMANTIC_LB\Classes\Remote::get_instance();
}
