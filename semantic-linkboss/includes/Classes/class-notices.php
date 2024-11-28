<?php

/**
 * Notice Handler
 *
 * @package SEMANTIC_LB
 * @since 2.1.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;

/**
 * Description of Notices
 *
 * @since 2.1.0
 */
class Notices {

	/**
	 * Construct
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notices' ) );
		add_action( 'wp_ajax_lbw_notices_dismiss', array( $this, 'notices_dismiss' ) );
	}

	/**
	 * Show Notices
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function show_notices() {
		if ( get_option( 'linkboss_api_key' ) == '' ) {
			$this->show_notice(
				'error',
				esc_html__( 'LinkBoss API Key is not set. Please set it from ', 'semantic-linkboss' ) . '<a href="' . admin_url( 'admin.php?page=linkboss-settings' ) . '">' . esc_html__( 'here', 'semantic-linkboss' ) . '</a>.'
			);
		}
		if ( ! Auth::get_access_token() ) {
			$this->show_notice(
				'error',
				esc_html__( 'LinkBoss Access Token is not found. Please save the settings from ', 'semantic-linkboss' ) . '<a href="' . admin_url( 'admin.php?page=linkboss-settings' ) . '">' . esc_html__( 'here', 'semantic-linkboss' ) . '</a>.'
			);
		}

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$dismissed = get_option( 'lbw_cron_notice_dismissed', false );
			if ( $dismissed ) {
				return;
			}
			$this->show_notice(
				'warning',
				esc_html__( 'LinkBoss functionality is currently disabled due to the deactivation of WordPress Cron. Please enable it to sync posts.', 'semantic-linkboss' ),
				'lbw-cron-notice'
			);

		}

	}

	/**
	 * Show Notice
	 *
	 * @since 2.1.0
	 * @param string $type
	 * @param string $message
	 * @return void
	 */
	public function show_notice( $type, $message, $class = '' ) {
		?>
		<div class="<?php echo esc_attr( $class ); ?> notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p>
				<?php echo wp_kses_post( $message ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss Notice.
	 */
	public function notices_dismiss() {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$notice = isset( $_POST['notice'] ) ? sanitize_text_field( wp_unslash( $_POST['notice'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'linkboss_nonce' ) ) {
			wp_send_json_error();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		if ( 'cron_dismissed' === $notice ) {
			update_option( 'lbw_cron_notice_dismissed', true);
			wp_send_json_success();
		}

		wp_send_json_error();

	}
}

new Notices();
