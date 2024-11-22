<?php
/**
 * Fetch Handler
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Description of Fetch
 *
 * @since 0.0.0
 */
class Fetch {

	use Global_Functions;

	private static $instance = null;

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
		$lb_nonce = wp_create_nonce( 'lb-nonce' );
		if ( ! wp_verify_nonce( $lb_nonce, 'lb-nonce' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		/**
		 * if page = page=linkboss-settings
		 * then fetch reports
		 */
		if ( isset( $page ) && 'semantic-linkboss' === $page ) {
			$this->fetch_reports();
		}

		if ( isset( $page ) && 'semantic-linkboss' === $page ) {
			$this->fetch_reports();
		}
		add_action( 'wp_ajax_linkboss_sync_reports_manually', array( $this, 'fetch_reports_manually' ) );
	}

	public function fetch_reports_manually() {
		delete_option( 'linkboss_reports' );
		echo wp_json_encode( array(
			'status' => 'success',
			'msg' => 'Reports Fetched Successfully',
		), true );
		wp_die();
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Fetch' ) ) {
	\SEMANTIC_LB\Classes\Fetch::get_instance();
}
