<?php

/**
 * Installer class
 * 
 * @package SEMANTIC_LB
 * @since 0.0.5
 */

namespace SEMANTIC_LB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer of Menu
 * 
 * @since 0.0.5
 */
class Installer {

	/**
	 * Runt the installer
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function run() {
		$this->add_version();
		$this->create_tables();
	}

	public function delete_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		/**
		 * Check if the table exists
		 */
		// phpcs:ignore
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $table_name ) ) ) == $table_name ) {
			// phpcs:ignore
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $table_name ) );
		}

	}

	/**
	 * Add plugin version to database
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function add_version() {
		$installed = get_option( 'linkboss_installed' );

		if ( ! $installed ) {
			update_option( 'linkboss_installed', time() );
		}

		update_option( 'linkboss_version', SEMANTIC_LB_VERSION );
	}

	/**
	 * Create nessary database tables
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		/**
		 * Create Sync Batch Table
		 *
		 * @since 0.0.5
		 */

		$schema_sync_batch = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}linkboss_sync_batch (
			`id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`post_id` BIGINT(20) UNSIGNED NOT NULL UNIQUE,
			`content_size` INT(11) NULL DEFAULT NULL,
			`post_type` VARCHAR(50) DEFAULT 'post' COMMENT 'post, page, attachment',
			`post_status` VARCHAR(50) DEFAULT 'publish' COMMENT 'publish, draft, trash',
			`sent_status` VARCHAR(20) NULL DEFAULT NULL COMMENT 'bool(1=Sent, NULL=Not Sent)',
			`page_builder` VARCHAR(255) DEFAULT NULL COMMENT 'Elementor, Gutenberg etc',
			`others_data` LONGTEXT DEFAULT NULL COMMENT 'Other Data',
			`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data DB Created Server Time',
			`sync_at` TIMESTAMP NULL COMMENT 'Last Activity of APPS',
			INDEX idx_post_id (post_id)
		) $charset_collate";

		/**
		 * status column type change to VARCHAR(1) from TINYINT(1) and also change the name to sent_status
		 * 
		 * @since 2.2.0
		 */

		$table_schema = esc_sql( DB_NAME );
		// phpcs:ignore
		$column = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
			$table_schema,
			$wpdb->prefix . 'linkboss_sync_batch',
			'status'
		) );

		if ( ! empty( $column ) ) {
			// phpcs:ignore
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}linkboss_sync_batch CHANGE COLUMN status sent_status VARCHAR(20) NULL DEFAULT NULL COMMENT 'bool(1=Sent, NULL=Not Sent)'" );

		}


		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$result = dbDelta( $schema_sync_batch );

		if ( $result === false ) {
			set_transient( 'linkboss_sync_batch_table_error', 'Failed to create sync batch table', 7 * DAY_IN_SECONDS );
		}
	}
}
