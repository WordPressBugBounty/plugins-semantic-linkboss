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

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use SEMANTIC_LB\Classes\Auth;

/**
 * Description of Notices
 *
 * @since 2.1.0
 */
class Notices {

	/**
	 * Namespace
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Rest Base
	 *
	 * @var string
	 */

	protected $rest_base;

	/**
	 * Construct
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_notices' ) );

		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'notices';
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register the routes
	 *
	 * @since 2.7.0
	 */
	public function register_rest_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
	}

	/**
	 * Check the permissions for getting the settings
	 *
	 * @since 2.7.0
	 */
	public function get_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check the permissions for updating the settings
	 *
	 * @since 2.7.0
	 */
	public function update_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle Request
	 *
	 * @since 2.7.0
	 */
	public function handle_request( WP_REST_Request $request ) {
		$params = $request->get_params();

		$action = isset( $params['action'] ) ? $params['action'] : false;

		if ( $action ) {
			return $this->notices_dismiss();
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => 'Init Reports fetched successfully',
				'data'    => array(
					'notices' => $this->get_notices( $params ),
				),
			)
		);
	}

	/**
	 * Get Notices
	 *
	 * @since 2.7.0
	 */
	public function get_notices( $params ) {
		$notices = array();

		if ( defined( 'DISABLE_WP_CRON' ) ) {
			$dismissed = get_option( 'lbw_cron_notice_dismissed', false );
			if ( $dismissed ) {
				return;
			}
			$notices[] = array(
				'type'    => 'warning',
				'message' => esc_html__( 'LinkBoss functionality may not work due to the deactivation of WordPress Cron. Please enable it to sync posts. ( Ignore/Close Notice if you use server cron )', 'semantic-linkboss' ),
			);
		}

		return $notices;
	}

	/**
	 * Show Notices
	 *
	 * @since 2.1.0
	 * @return void
	 */
	public function show_notices() {
		if ( '' === get_option( 'linkboss_api_key' ) ) {
			$this->show_notice(
				'error',
				esc_html__( 'LinkBoss API Key is not set. Please connect your WordPress site with LinkBoss app by adding the API key from ', 'semantic-linkboss' ) . '<a href="' . admin_url( 'admin.php?page=semantic-linkboss' ) . '">' . esc_html__( 'Settings Tab', 'semantic-linkboss' ) . '</a>.'
			);
		}
		if ( ! Auth::get_access_token() ) {
			$this->show_notice(
				'error',
				esc_html__( 'LinkBoss Access Token is not found. Please connect your WordPress site with LinkBoss app by adding the API key from ', 'semantic-linkboss' ) . '<a href="' . admin_url( 'admin.php?page=semantic-linkboss' ) . '">' . esc_html__( 'Settings Tab', 'semantic-linkboss' ) . '</a>.'
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
		update_option( 'lbw_cron_notice_dismissed', true );
		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => 'Notice dismissed successfully',
			)
		);
	}
}

new Notices();
