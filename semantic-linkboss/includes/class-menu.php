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
	 * Constructor
	 *
	 * @param object $layouts Layouts.
	 * @return void
	 * @since 0.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 * @since 2.6.5
	 */
	public function admin_menu() {
		$parent_slug = 'semantic-linkboss';
		$capability  = 'manage_options';
		add_menu_page( esc_html__( 'LinkBoss', 'semantic-linkboss' ), esc_html__( 'LinkBoss', 'semantic-linkboss' ), $capability, $parent_slug, array( $this, 'plugin_layout' ), $this->get_b64_icon(), 56 );
	}

	/**
	 * Plugin Layout
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function plugin_layout() {
		echo '<div id="semantic-linkboss" class="wrap semantic-linkboss"> <h2>Loading...</h2> </div>';
	}

	public static function get_dashboard_link( $suffix = '#' ) {
		return add_query_arg( array( 'page' => 'semantic-linkboss' . $suffix ), admin_url( 'admin.php' ) );
	}

	public static function get_b64_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( SEMANTIC_LB_PATH . 'assets/imgs/linkboss-icon.svg' ) );
	}
}
