<?php
/**
 * Admin class
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of Menu
 *
 * @since 0.0.0
 */

class Admin {

	/**
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->dispatch_actions();
		new Admin\Menu();
	}

	/**
	 * Dispatch Actions
	 *
	 * @since 1.0.0
	 */
	public function dispatch_actions() {
		new Classes\Auth();
		new Classes\Settings();
		new Classes\Dashboard();

		$api_valid = get_option( 'linkboss_api_key', false );

		if ( $api_valid ) {
			new Classes\Sync_Posts();
			new Classes\Sync_Posts_Api();
		}
	}
}
