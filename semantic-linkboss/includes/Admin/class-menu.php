<?php
/**
 * Menu class
 *
 * @package SEMANTIC_LB\Admin
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of Menu
 *
 * @since 0.0.0
 */
class Menu {
	/**
	 * Layouts
	 *
	 * @since 0.0.0
	 */
	public $layouts;

	/**
	 * Constructor
	 *
	 * @param object $layouts Layouts.
	 * @return void
	 * @since 0.0.0
	 */
	public function __construct( $layouts ) {
		$this->layouts = $layouts;
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	/**
	 * Admin menu
	 *
	 * @since 0.0.0
	 */
	public function admin_menu() {
		$parent_slug = 'semantic-linkboss';
		$capability = 'manage_options';

		add_menu_page(
			esc_html__( 'LinkBoss', 'semantic-linkboss' ),
			esc_html__( 'LinkBoss', 'semantic-linkboss' ),
			$capability,
			$parent_slug,
			array( $this->layouts, 'plugin_layout' ),
			'dashicons-admin-links',
			99
		);

		add_submenu_page(
			$parent_slug,
			esc_html__( 'Settings', 'semantic-linkboss' ),
			esc_html__( 'Settings', 'semantic-linkboss' ),
			$capability,
			$parent_slug . '-settings',
			array( $this->layouts, 'plugin_settings' )
		);

		add_submenu_page(
			$parent_slug,
			esc_html__( 'Logs', 'semantic-linkboss' ),
			esc_html__( 'Logs', 'semantic-linkboss' ),
			$capability,
			$parent_slug . '-logs',
			array( $this->layouts, 'plugin_logs' )
		);

		add_submenu_page(
			$parent_slug,
			esc_html__( 'Upgrade', 'semantic-linkboss' ),
			esc_html__( 'Upgrade', 'semantic-linkboss' ),
			$capability,
			$parent_slug . '&linkboss_pro',
			array( $this->layouts, 'plugin_report' )
		);
	}

	public static function add_toolbar_items( \WP_Admin_Bar $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon = '<i class="dashicons dashicons-update-alt"></i> ';

		$admin_bar->add_menu( [ 
			'id' => 'semantic-linkboss',
			'title' => sprintf( '<div style="display:flex; align-items: center; gap: 6px;">LinkBoss <img style="width:20px !important;" src="%s"></div>', self::get_b64_icon() ),
			'href' => self::get_dashboard_link(),
			'meta' => [ 
				'title' => esc_html__( 'HappyAddons', 'semantic-linkboss' ),
			],
		] );

		$admin_bar->add_menu( [ 
			'id' => 'lb-sync-from-linkboss',
			'parent' => 'semantic-linkboss',
			'title' => $icon . esc_html__( 'Sync from LinkBoss', 'semantic-linkboss' ),
			'href' => 'javascript:void(0);',
			'meta' => [ 
				'class' => 'lb-sync-from-linkboss',
			],
		] );

		$admin_bar->add_menu( [ 
			'id' => 'lb-send-linkboss',
			'parent' => 'semantic-linkboss',
			'title' => $icon . esc_html__( 'Sync to LinkBoss', 'semantic-linkboss' ),
			'href' => 'javascript:void(0);',
			'meta' => [ 
				'class' => 'lb-send-linkboss',
			],
		] );
	}

	public static function get_dashboard_link( $suffix = '#' ) {
		return add_query_arg( [ 'page' => 'semantic-linkboss' . $suffix ], admin_url( 'admin.php' ) );
	}

	public static function get_b64_icon() {
		return SEMANTIC_LB_ASSETS_URL . 'imgs/linkboss-icon.png';
	}

}
