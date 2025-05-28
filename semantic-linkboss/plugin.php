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

	/**
	 * Init Plugin
	 *
	 * @since 0.0.0
	 * @return void
	 */
	public function init() {
	}

	/**
	 * Admind Styles
	 *
	 * @since 2.6.5
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_semantic-linkboss' !== $hook_suffix && 'semantic-linkboss_page_semantic-linkboss-get-pro' !== $hook_suffix ) {
			return;
		}
		$direction_suffix = is_rtl() ? '.rtl' : '';
		wp_enqueue_style( 'wp-components' );
		wp_register_style( 'semantic-linkboss', SEMANTIC_LB_URL . 'build/index.css', array(), SEMANTIC_LB_VERSION );
		wp_enqueue_style( 'semantic-linkboss' );
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @since 2.6.5
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {

		wp_register_script( 'linkboss-socket', SEMANTIC_LB_ASSETS_URL . 'vendor/socket.io.min.js', array(), 'v4.8.1', true );
		wp_enqueue_script( 'linkboss-socket' );

		$access_token = Auth::get_access_token();
		wp_localize_script(
			'linkboss-socket',
			'LinkBossSocket',
			array(
				'access_token' => $access_token,
				'api_url'      => esc_url( SEMANTIC_LB_REMOTE_ROOT_URL ),
				'assets_url'   => SEMANTIC_LB_ASSETS_URL,
				'nonce'        => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_register_script( 'semantic-linkboss-admin', SEMANTIC_LB_ASSETS_URL . 'js/semantic-linkboss-admin.js', array( 'jquery', 'linkboss-socket' ), SEMANTIC_LB_VERSION, true );
		wp_enqueue_script( 'semantic-linkboss-admin' );

		if ( 'toplevel_page_semantic-linkboss' !== $hook_suffix ) {
			return;
		}

		$asset_file = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		// wp_register_script( 'semantic-linkboss-sweetalert2', SEMANTIC_LB_ASSETS_URL . 'vendor/sweetalert2.min.js', array( 'jquery' ), '11.4.8', true );
		wp_register_script( 'semantic-linkboss', SEMANTIC_LB_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_script( 'semantic-linkboss' );
		// wp_enqueue_script( 'semantic-linkboss-sweetalert2' );

		/**
		 * Localize Script
		 */
		$script_config = array(
			'rest_url'     => esc_url( get_rest_url() ),
			'app_api'      => esc_url( SEMANTIC_LB_REMOTE_URL ),
			'staging'      => ! defined( 'SEMANTIC_LB_STAGING' ) ? true : false,
			'version'      => SEMANTIC_LB_VERSION,
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'assets_url'   => SEMANTIC_LB_ASSETS_URL,
			'logo'         => SEMANTIC_LB_ASSETS_URL . 'imgs/logo.png',
			'root_url'     => SEMANTIC_LB_URL,
			'current_user' => array(
				'domain'       => esc_url( home_url() ),
				'display_name' => wp_get_current_user()->display_name,
				'email'        => wp_get_current_user()->user_email,
				'id'           => wp_get_current_user()->ID,
				'avatar'       => get_avatar_url( wp_get_current_user()->ID ),
			),
		);

		wp_localize_script( 'semantic-linkboss', 'LinkBossConfig', $script_config );
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

	/**
	 * Setup Hooks
	 *
	 * @since 0.0.0
	 */
	private function setup_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ), 99999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99999 );
	}
}

// kick off the plugin
if ( class_exists( 'SEMANTIC_LB\Plugin' ) ) {
	new Plugin();
}
