<?php

/**
 * Plugin File
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;

/**
 * Plugin class
 *
 * @since 0.0.0
 */

final class Plugin {

	/**
	 * Require Files
	 *
	 * @return void
	 */
	public function includes_files() {
		require_once SEMANTIC_LB_INC_PATH . 'Traits/global-functions.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-init.php';
		require_once SEMANTIC_LB_INC_PATH . 'class-admin.php';
		require_once SEMANTIC_LB_INC_PATH . 'Admin/class-layouts.php';
		require_once SEMANTIC_LB_INC_PATH . 'Admin/class-menu.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-auth.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-settings.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-render.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-ajax-init.php';
		require_once SEMANTIC_LB_INC_PATH . 'Classes/class-notices.php';

		$api_valid = get_option( 'linkboss_api_key', false );

		if ( $api_valid ) {

			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-posts.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-update-posts.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-updates.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-fetch.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-cron.php';
			require_once SEMANTIC_LB_INC_PATH . 'Classes/class-ajax-sync.php';

			if ( defined( 'SEMANTIC_LB_ELEMENTOR' ) ) {
				require_once SEMANTIC_LB_INC_PATH . 'Classes/Builders/class-elementor.php';
			}
		}

		/**
		 * Admin Files Only
		 */
		if ( is_admin() ) {
			require_once SEMANTIC_LB_INC_PATH . 'Admin/class-admin-feeds.php';
		}
	}
	/**
	 * Init Plugin
	 *
	 * @since 0.0.0
	 * @return void
	 */
	public function init() {
		$this->includes_files();
		new Admin();
	}

	/**
	 * Enqueue Styles
	 *
	 * @since 0.0.0
	 */
	public function enqueue_admin_styles( $hook_suffix) {

		if (
			'toplevel_page_semantic-linkboss' !== $hook_suffix &&
			'linkboss_page_semantic-linkboss-settings' !== $hook_suffix &&
			'linkboss_page_semantic-linkboss-logs' !== $hook_suffix
		) {
			return;
		}

		$direction_suffix = is_rtl() ? '.rtl' : '';
		wp_enqueue_style( 'linkboss-tailwind', SEMANTIC_LB_ASSETS_URL . 'css/tailwind' . $direction_suffix . '.min.css', array(), SEMANTIC_LB_VERSION );
		wp_enqueue_style( 'lb-datatable', SEMANTIC_LB_ASSETS_URL . 'vendor/css/dataTables.tailwindcss.min.css', array(), '1.13.7' );
		wp_enqueue_style( 'select2', SEMANTIC_LB_ASSETS_URL . 'vendor/css/select2.min.css', array(), '4.0.13' );
		wp_enqueue_style( 'semantic-linkboss', SEMANTIC_LB_ASSETS_URL . 'css/semantic-linkboss' . $direction_suffix . '.min.css', array(), SEMANTIC_LB_VERSION );
	}

	/**
	 * Enqueue Scripts
	 *
	 * @since 0.0.0
	 */
	public function enqueue_admin_scripts() {
		/**
		 * Vendor JS Files
		 */
		wp_enqueue_script( 'lb-datatable', SEMANTIC_LB_ASSETS_URL . 'vendor/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.7', true );
		wp_enqueue_script( 'lb-datatable', SEMANTIC_LB_ASSETS_URL . 'vendor/js/dataTables.tailwindcss.js', array( 'jquery' ), '1.13.7', true );
		wp_enqueue_script( 'lb-sweetalert2', SEMANTIC_LB_ASSETS_URL . 'vendor/js/sweetalert2.min.js', array( 'jquery' ), '11.4.8', true );
		wp_enqueue_script( 'select2', SEMANTIC_LB_ASSETS_URL . 'vendor/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
		wp_enqueue_script( 'lb-vendor', SEMANTIC_LB_ASSETS_URL . 'vendor/socket.io.min.js', array(), SEMANTIC_LB_VERSION, true );

		/**
		 * Main JS File
		 */
		wp_enqueue_script( 'semantic-linkboss', SEMANTIC_LB_ASSETS_URL . 'js/semantic-linkboss.js', array( 'jquery' ), SEMANTIC_LB_VERSION, true );

		/**
		 * Localize Script
		 */
		$script_config = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'linkboss_nonce' ),
			'activated' => get_option( 'linkboss_api_key', false ),
		);

		$access_token = Auth::get_access_token();

		wp_localize_script( 'semantic-linkboss', 'LinkbossConfig', $script_config );
		wp_localize_script( 'semantic-linkboss', 'LinkbossSocket', array(
			'access_token' => $access_token,
			'api_url' => esc_url( SEMANTIC_LB_REMOTE_ROOT_URL ),
		) );
	}

	/**
	 * Setup Hooks
	 *
	 * @since 0.0.0
	 */
	private function setup_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 99999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 9999 );
	}

	/**
	 * Constructor
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		$this->init();
		$this->setup_hooks();
	}
}

// kick off the plugin
if ( class_exists( 'SEMANTIC_LB\Plugin' ) ) {
	new Plugin();
}
