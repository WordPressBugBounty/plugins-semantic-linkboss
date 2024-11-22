<?php
/**
 * Layouts Handler
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
class Layouts {

	/**
	 * Plugin Layouts
	 *
	 * @return void
	 * @since 0.0.0
	 */
	public function plugin_layout() {
		$template = __DIR__ . '/views/layouts.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Layouts
	 *
	 * @return void
	 * @since 0.0.0
	 */
	public function plugin_settings() {
		$template = __DIR__ . '/views/settings.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Logs
	 *
	 * @return void
	 * @since 0.0.0
	 */
	public function plugin_logs() {
		$template = __DIR__ . '/views/logs.php';

		if ( file_exists( $template ) ) {
			include $template;
		}
	}

}
