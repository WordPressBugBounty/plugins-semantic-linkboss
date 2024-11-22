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

/**
 * Description of Auth
 *
 * @since 0.0.0
 */
class Auth {

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_lb_auth_check', array( $this, 'lb_auth_check' ) );
		add_action( 'wp_ajax_sync_init', array( $this, 'sync_init' ) );
		add_action( 'wp_ajax_sync_finish', array( $this, 'sync_finish' ) );
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
			// self::refresh_access_token();
			return get_option( 'linkboss_access_token', false );
		}
		return $access_token;
	}

	/**
	 * Check
	 *
	 * @since 0.0.0
	 */
	public function lb_auth_check() {

		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Nonce Failed!',
					'msg' => esc_html( 'Nonce Failed, please refresh the page & try again!' ),
				)
			);
			wp_die();
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : false;

		$api_url = SEMANTIC_LB_AUTH_URL;

		$headers = array(
			'Content-Type' => 'application/json',
			'method' => 'POST',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'client' => get_site_url(),
			'api_key' => $api_key,
		);

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body, true ),
			'method' => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg = isset( $res_body->message ) ? $res_body->message : '';
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html( $msg . '. Error Code - ' . wp_remote_retrieve_response_code( $response ) ),
				)
			);
			wp_die();
		}

		if ( isset( $res_body->access ) ) {
			self::set_access_token( $res_body->access );
		}

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html( $res_body->message ),
			)
		);

		delete_transient( 'linkboss_auth_error' );

		wp_die();
	}

	/**
	 * Refresh Access Token by API KEY
	 *
	 * PATCH Request
	 * @since 0.0.5
	 */
	public static function refresh_access_token() {
		$api_url = SEMANTIC_LB_AUTH_URL;
		$api_key = get_option( 'linkboss_api_key', false );

		if ( ! $api_key ) {
			return;
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'method' => 'POST',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'client' => get_site_url(),
			'api_key' => $api_key,
		);

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body, true ),
			'method' => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			//todo: handle error
		}

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
			'Content-Type' => 'application/json',
			'method' => 'POST',
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'client' => get_site_url(),
			'api_key' => $api_key,
		);

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body, true ),
			'method' => 'POST',
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

	/**
	 * Sync Init
	 *
	 * @since 2.2.0
	 */
	public function sync_init() {

		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Nonce Failed!',
					'msg' => esc_html( 'Nonce Failed, please refresh the page & try again!' ),
				)
			);
			wp_die();
		}

		$posts = isset( $_POST['posts'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['posts'] ) ) : 0;
		$category = isset( $_POST['category'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 0;
		$sync_done = isset( $_POST['sync_done'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['sync_done'] ) ) : 0;

		$status = 'partial';

		if ( 0 === $sync_done ) {
			$status = 'complete';
		}

		$api_url = SEMANTIC_LB_SYNC_INIT;

		$access_token = self::get_access_token();

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts' => $posts,
			'category' => $category,
			'status' => $status,
		);

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body ),
			'method' => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg = isset( $res_body->message ) ? $res_body->message : '';
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html( $msg . '. Error Code - ' . wp_remote_retrieve_response_code( $response ) ),
				)
			);
			wp_die();
		}

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html( $res_body->message ),
			)
		);

		wp_die();
	}

	/**
	 * Sync Finished
	 *
	 * @since 2.2.0
	 */
	public function sync_finish() {

		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Nonce Failed!',
					'msg' => esc_html( 'Nonce Failed, please refresh the page & try again!' ),
				)
			);
			wp_die();
		}

		$api_url = SEMANTIC_LB_SYNC_FINISH;
		$access_token = self::get_access_token();

		if ( ! $access_token ) {
			return self::get_tokens_by_auth_code();
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array();

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body ),
			'method' => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$msg = isset( $res_body->message ) ? $res_body->message : '';
			$remain = isset( $res_body->remain ) ? $res_body->remain : 0;
			$notify = isset( $res_body->notify ) ? $res_body->notify : false;

			if ( $notify ) {
				echo wp_json_encode(
					array(
						'status' => 'error',
						'title' => esc_html( 'Error - ' . wp_remote_retrieve_response_code( $response ) ),
						'msg' => esc_html( $msg . '. Remaining Contents- ' . $remain ),
					)
				);
			}
			wp_die();
		}

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html( $res_body->message ),
			)
		);

		wp_die();
	}
}
