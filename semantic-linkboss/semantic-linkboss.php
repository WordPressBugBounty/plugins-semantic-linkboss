<?php
/*
 * Plugin Name: LinkBoss - Semantic Internal Linking
 * Plugin URI: https://linkboss.io
 * Description: NLP, AI, and Machine Learning-powered semantic interlinking tool. Supports manual incoming/outgoing, SILO, and bulk auto internal links.
 * Version: 2.7.6
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: ZVENTURES LLC
 * Author URI: https://linkboss.io
 * License: GPL-3.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: semantic-linkboss
 * Domain Path: /languages
 *
 * @package SEMANTIC_LB
 * @author LinkBoss <hi@zventures.io>
 * @license           GPL-3.0-or-later
 *
 */

/**
 * Prevent direct access
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEMANTIC_LB_VERSION', '2.7.6' );
define( 'SEMANTIC_LB__FILE__', __FILE__ );
define( 'SEMANTIC_LB_PATH', plugin_dir_path( SEMANTIC_LB__FILE__ ) );
define( 'SEMANTIC_LB_URL', plugins_url( '/', SEMANTIC_LB__FILE__ ) );
define( 'SEMANTIC_LB_INC_PATH', SEMANTIC_LB_PATH . 'includes/' );
define( 'SEMANTIC_LB_ASSETS_URL', SEMANTIC_LB_URL . 'assets/' );

if ( ! defined( 'SEMANTIC_LB_REMOTE_ROOT_URL' ) ) {
	define( 'SEMANTIC_LB_REMOTE_ROOT_URL', 'https://api.linkboss.io' );
	define( 'SEMANTIC_LB_STAGING', false );
}

define( 'SEMANTIC_LB_REMOTE_URL', SEMANTIC_LB_REMOTE_ROOT_URL . '/api/v2/wp/' );
define( 'SEMANTIC_LB_AUTH_URL', SEMANTIC_LB_REMOTE_ROOT_URL . '/api/v2/auth/' );
define( 'SEMANTIC_LB_FETCH_REPORT_URL', SEMANTIC_LB_REMOTE_URL . 'options' );
define( 'SEMANTIC_LB_POSTS_SYNC_URL', SEMANTIC_LB_REMOTE_URL . 'sync' );
define( 'SEMANTIC_LB_OPTIONS_URL', SEMANTIC_LB_REMOTE_URL . 'options' );
define( 'SEMANTIC_LB_SYNC_INIT', SEMANTIC_LB_REMOTE_URL . 'sync/init' );
define( 'SEMANTIC_LB_SYNC_FINISH', SEMANTIC_LB_REMOTE_URL . 'sync/fin' );

add_action( 'init', 'semantic_lb_i18n' );

function semantic_lb_i18n() {
	load_plugin_textdomain( 'semantic-linkboss', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Installer
 *
 * @since 1.0.0
 */
require_once SEMANTIC_LB_PATH . 'includes/class-installer.php';

/**
 * The main function responsible for returning the one true LinkBoss instance to functions everywhere.
 *
 * @since 0.0.0
 */

if ( ! function_exists( 'semantic_lb' ) ) {

	/**
	 * Load gettext translate for our text domain.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function semantic_lb_elementor_load_plugin() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		define( 'SEMANTIC_LB_ELEMENTOR', true );
	}

	add_action( 'plugins_loaded', 'semantic_lb_elementor_load_plugin' );

	function semantic_lb_classic_editor_plugin_state() {
		/**
		 * Ensure the function is available
		 */
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin = 'classic-editor/classic-editor.php';

		if ( is_plugin_active( $plugin ) ) {
			define( 'SEMANTIC_LB_CLASSIC_EDITOR', true );
		}
	}

	add_action( 'plugins_loaded', 'semantic_lb_classic_editor_plugin_state' );

	function semantic_lb_divi_builder_loaded() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			return;
		}
	}

	add_action( 'et_builder_ready', 'semantic_lb_divi_builder_loaded' );

	add_action(
		'plugins_loaded',
		function () {
			if ( class_exists( 'CT_Component' ) ) {
				if ( ! defined( 'SHOW_CT_BUILDER_LB' ) ) {
					define( 'SHOW_CT_BUILDER_LB', true );
				}
			}
		}
	);

	function semantic_lb() {
		require_once __DIR__ . '/class-core.php';
		\SEMANTIC_LB\Core::instance();

		require_once __DIR__ . '/plugin.php';

		require_once SEMANTIC_LB_INC_PATH . 'class-admin.php';
		new \SEMANTIC_LB\Admin();
	}

	function semantic_lb_activate() {
		$installer = new SEMANTIC_LB\Installer();
		$installer->run();
	}

	/**
	 * Creating tables for all blogs in a WordPress Multisite installation
	 *
	 * @since 2.2.4
	 */
	function semantic_lb_on_activate( $network_wide ) {
		if ( is_admin() && is_multisite() && $network_wide ) {
			global $wpdb;
			/**
			 * Get all blogs in the network and activate plugin on each one
			 */
			// phpcs:ignore
			$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				semantic_lb_activate();
				restore_current_blog();
			}
		} else {
			semantic_lb_activate();
		}
	}

	/**
	 * Register activation hook for multisite
	 */
	register_activation_hook( __FILE__, 'semantic_lb_on_activate' );

	add_action( 'init', 'semantic_lb' );
}



/**
 * SDK Integration
 */

if ( ! function_exists( 'dci_plugin_semantic_lb' ) ) {
	function dci_plugin_semantic_lb() {

		require_once __DIR__ . '/dci/start.php';

		wp_register_style( 'dci-sdk-linkboss', SEMANTIC_LB_URL . 'dci/assets/css/dci.css', array(), '1.3.0', 'all' );
		wp_enqueue_style( 'dci-sdk-linkboss' );

		dci_dynamic_init(
			array(
				'sdk_version'          => '1.2.1',
				'product_id'           => 1,
				'plugin_name'          => 'LinkBoss', // make simple, must not empty
				'plugin_title'         => 'Love using LinkBoss? Congrats 🎉  ( Never miss an Important Update )',
				'plugin_icon'          => SEMANTIC_LB_ASSETS_URL . 'imgs/linkboss-icon.png',
				'api_endpoint'         => 'https://plugin.linkboss.io/wp-json/dci/v1/data-insights',
				'slug'                 => 'semantic-linkboss',
				'core_file'            => 'semantic-linkboss',
				'menu'                 => array(
					'slug' => 'semantic-linkboss',
				),
				'public_key'           => 'pk_YBHttccXCO43DOSBEurwdrHkeEjlxpED',
				'is_premium'           => true,
				'popup_notice'         => false,
				'deactivate_feedback'  => true,
				'plugin_deactivate_id' => 'semantic-linkboss',
				'text_domain'          => 'semantic-linkboss',
				'plugin_msg'           => '<p>Be Top-contributor by sharing non-sensitive plugin data and create an impact to the global WordPress community today! You can receive valuable emails periodically.</p>',
			)
		);
	}
	add_action( 'admin_init', 'dci_plugin_semantic_lb' );
}

/**
 * Review Automation Integration
 */

if ( ! function_exists( 'semantic_lb_rc_plugin' ) ) {
	function semantic_lb_rc_plugin() {

		require_once SEMANTIC_LB_PATH . 'feedbacks/start.php';

		rc_dynamic_init(
			array(
				'sdk_version'  => '1.0.0',
				'plugin_name'  => 'LinkBoss',
				'plugin_icon'  => SEMANTIC_LB_ASSETS_URL . 'imgs/linkboss-icon.png',
				'slug'         => 'semantic-linkboss',
				'menu'         => array(
					'slug' => 'semantic-linkboss',
				),
				'review_url'   => 'https://wordpress.org/plugins/semantic-linkboss/',
				'plugin_title' => 'Yay! Great that you\'re using LinkBoss',
				'plugin_msg'   => '<p>Loved using LinkBoss on your website? Share your experience in a review and help us spread the love to everyone right now. Good words will help the community.</p>',
			)
		);
	}
	add_action( 'admin_init', 'semantic_lb_rc_plugin' );
}

/**
 * Redirect to the welcome page
 *
 * @since 2.7.0
 */
if ( ! function_exists( 'semantic_lb_activation_redirect' ) ) {
	function semantic_lb_activation_redirect( $plugin ) {
		if ( plugin_basename( __FILE__ ) === $plugin ) {
			$setup_wizard = get_option( 'linkboss_setup_wizard' );

			if ( ! $setup_wizard ) {
				update_option( 'linkboss_setup_wizard', true );
				wp_redirect( admin_url( 'admin.php?page=semantic-linkboss&tab=setupWizard' ) );
				exit;
			} else {
				wp_redirect( esc_url( admin_url( 'admin.php?page=semantic-linkboss' ) ) );
				exit;
			}
		}
	}
}

add_action( 'activated_plugin', 'semantic_lb_activation_redirect', 20 );
