<?php
/**
 * Core File
 *
 * @package SEMANTIC_LB
 * @since 2.7.0
 */

namespace SEMANTIC_LB;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Core
 * Register Files / Layouts
 *
 * @since 1.0.0
 * @author Shahidul Islam
 */
final class Core {

	/**
	 * Instance
	 *
	 * @var object
	 * @since 1.0.0
	 */
	private static $instance;

	/**
	 * Instance
	 *
	 * @return object
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();

			do_action( 'dci_loaded' );
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Init
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$this->include_files();
	}

	/**
	 * Include Files
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function include_files() {
		require_once SEMANTIC_LB_INC_PATH . 'Traits/global-functions.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-init.php';
		require_once SEMANTIC_LB_INC_PATH . 'class-admin.php';
		require_once SEMANTIC_LB_INC_PATH . 'class-menu.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-auth.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-settings.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-dashboard.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-notices.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-sync-posts.php';

		$api_valid = get_option( 'linkboss_api_key', false );

		if ( $api_valid ) {
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-update-posts.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-updates.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-cron.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-sync-posts-api.php';

			if ( defined( 'SEMANTIC_LB_ELEMENTOR' ) ) {
				require_once SEMANTIC_LB_INC_PATH . 'Classes/Builders/class-elementor.php';
			}
		}

		/**
		 * Admin Files Only
		 */
		if ( is_admin() ) {
			require_once SEMANTIC_LB_INC_PATH . 'class-admin-feeds.php';
		}
	}
}

if ( class_exists( 'SEMANTIC_LB\Core' ) ) {
	new \SEMANTIC_LB\Core();
}
