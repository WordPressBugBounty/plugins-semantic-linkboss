<?php
/**
 * Settings Handler
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Installer;

/**
 * Description of Settings
 *
 * @since 0.0.0
 */
class Settings {

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_linkboss_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_linkboss_reset_sync_batch', array( $this, 'reset_sync_batch' ) );
		add_action( 'wp_ajax_linkboss_sync_speed', array( $this, 'sync_speed' ) );

		/**
		 * Create Table Hook for MultiSite Network make sure the table is created
		 * 
		 * @since 2.2.4
		 */
		$lb_nonce = wp_create_nonce( 'lb-nonce' );
		if ( ! wp_verify_nonce( $lb_nonce, 'lb-nonce' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( isset( $page ) && 'semantic-linkboss' === $page || 'linkboss-settings' === $page ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'linkboss_sync_batch';

			/**
			 * Check if the table exists
			 */
			// phpcs:ignore
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
				$installer = new Installer();
				$installer->create_tables();
			}
		}
	}

	/**
	 * Register settings
	 *
	 * @since 0.0.0
	 */
	public function save_settings() {

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html__( 'Failed to insert data. Please refresh your browser. If that doesn\'t work, please contact support team.', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key = sanitize_text_field( $_POST['linkboss_api_key'] );
		update_option( 'linkboss_api_key', $api_key );

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html__( 'Congrats, the API Key was saved successfully.', 'semantic-linkboss' ),
			)
		);
		wp_die();
	}

	/**
	 * Reset Sync Batch
	 *
	 * @since 0.0.5
	 */
	public function reset_sync_batch() {
		global $wpdb;

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html__( 'Failed to insert data. Please refresh your browser. If that doesn\'t work, please contact support team.', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/**
		 * drop table
		 */
		// phpcs:ignore
		$wpdb->query( "DROP TABLE {$wpdb->prefix}linkboss_sync_batch" );
		/**
		 * create table
		 */
		$installer = new Installer();
		$installer->create_tables();

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html__( 'Congrats, the sync batch was reset successfully.', 'semantic-linkboss' ),
			)
		);
		wp_die();
	}

	/**
	 * Sync Speed
	 * 
	 * @since 2.5.0
	 */
	public function sync_speed() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html__( 'Failed to insert data. Please refresh your browser. If that doesn\'t work, please contact support team.', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sync_speed = sanitize_text_field( $_POST['linkboss_sync_speed'] );
		update_option( 'linkboss_sync_speed', (int) $sync_speed );

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html__( 'Congrats, the sync speed was saved successfully.', 'semantic-linkboss' ),
			)
		);
		wp_die();
	}
}
