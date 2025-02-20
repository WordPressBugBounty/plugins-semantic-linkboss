<?php
/**
 * Updates Handler
 * Update or Send data to Sync Batch Table
 *
 * @package SEMANTIC_LB
 * @since 1.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Description of Updates
 *
 * @since 1.0.0
 */
class Updates {

	use Global_Functions;
	private static $instance = null;

	/**
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'delete_post', array( $this, 'delete_sync_require' ) );
	}

	/**
	 * Delete Sync Request
	 *
	 * @since 0.0.6
	 */
	public function delete_sync_require( $post_id ) {

		if ( ! $post_id ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		$post_status = get_post_status( $post_id );

		/**
		 * Mark as Delete
		 */
		self::update_sync_batch_table( $post_id, $post_type, $post_status );
	}

	/**
	 * Send WordPress Posts as JSON
	 *
	 * @since 1.0.0
	 */
	public function data_sync_require( $post_id ) {

		if ( ! $post_id ) {
			return;
		}

		/**
		 * Get post type
		 */
		$post_type = get_post_type( $post_id );
		$post_status = get_post_status( $post_id );

		self::update_sync_batch_table( $post_id, $post_type, $post_status );
	}

	/**
	 * Update or Send data to Sync Batch Table
	 *
	 * @since 0.0.5
	 */
	public static function update_sync_batch_table( $post_id, $post_type = 'post', $post_status = null ) {

		/**
		 * Get data of Custom Query Builder
		 */
		$query_data = get_option( 'linkboss_custom_query', '' );
		$post_type_db = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post', 'page' );

		/**
		 * Prevent from other post types
		 */

		if ( ! in_array( $post_type, $post_type_db ) ) {
			return;
		}

		global $wpdb;

		/**
		 * Check if the post_id already exists in the table
		 */
		// phpcs:ignore
		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}linkboss_sync_batch WHERE post_id = %d", $post_id );
		// phpcs:ignore
		$result = $wpdb->get_row( $query );

		if ( $result ) {

			/**
			 * If the post_id exists, update the status to NULL
			 * Set status to NULL
			 */
			// phpcs:ignore
			$wpdb->update(
				$wpdb->prefix . 'linkboss_sync_batch',
				array(
					'post_status' => $post_status,
					'sent_status' => null,
				),
				array( 'post_id' => $post_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

		} else {
			/**
			 * If the post_id doesn't exist, insert a new row
			 * Set status to NULL
			 */
			// phpcs:ignore
			$wpdb->insert(
				$wpdb->prefix . 'linkboss_sync_batch',
				array(
					'post_id' => $post_id,
					'post_type' => $post_type,
					'post_status' => $post_status,
					'sent_status' => null,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Get Instance
	 *
	 * @since 1.0.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Updates' ) ) {
	\SEMANTIC_LB\Classes\Updates::get_instance();
}
