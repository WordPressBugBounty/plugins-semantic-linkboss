<?php
/**
 * Init Handler
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Description of Init
 *
 * @since 0.0.0
 */
class Init {

	use Global_Functions;

	private static $instance = null;

	/**
	 * Get Instance
	 *
	 * @since 0.0.0
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_lb_sync_batch', [ $this, 'init_table_ids_for_batch' ] );
	}

	/**
	 * Init Table IDs
	 * 
	 * It's the pair feature of class-ajax-init.php
	 */
	public static function init_table_ids_for_batch( $limit = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		/*
		$sql = "SELECT p.ID, p.post_type, p.post_content, p.post_status
				FROM {$wpdb->prefix}posts p
				LEFT JOIN {$wpdb->prefix}linkboss_sync_batch l ON p.ID = l.post_id
				WHERE l.post_id IS NULL
				AND p.post_type IN ('post', 'page')
				AND p.post_status = 'publish' LIMIT 50";
		*/

		/**
		 * Custom Query Builder
		 */
		$query_data = get_option( 'linkboss_custom_query', '' );
		$post_type_query = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post', 'page' );

		/**
		 * Escape each item and wrap it in single quotes
		 */
		$post_type_query_escaped = array_map( function ($type) {
			return "'" . esc_sql( $type ) . "'";
		}, $post_type_query );

		$post_type_query_string = implode( ', ', $post_type_query_escaped );

		$sql = "SELECT p.ID, p.post_type, p.post_content, p.post_status
				FROM {$wpdb->prefix}posts p
				LEFT JOIN {$wpdb->prefix}linkboss_sync_batch l ON p.ID = l.post_id
				WHERE l.post_id IS NULL
				AND p.post_type IN ($post_type_query_string)
				AND p.post_status = 'publish' LIMIT 100";

		/**
		 * /Custom Query Builder
		 */

		$posts = $wpdb->get_results( $sql, ARRAY_A );

		/**
		 * Split the data into batches of 200
		 */
		$batches = array_chunk( $posts, 200 );

		$totalBatches = count( $batches );
		$currentBatch = 0;

		foreach ( $batches as $batch ) {
			/**
			 * Create the SQL query for inserting
			 */
			$insertSql = "INSERT IGNORE INTO $table_name (post_id, post_type, post_status, sent_status, content_size) VALUES ";

			/**
			 * Use the array values to construct the query
			 */
			$values = [];

			foreach ( $batch as $post ) {
				$post_id      = $post['ID'];
				$post_content = $post['post_content'];
				$post_type    = $post['post_type'];
				$post_status  = $post['post_status'];
				$content_size = mb_strlen( $post_content, '8bit' ); // Calculate size in bytes
				$__sent_status = null;

				/**
				 * Custom Query Builder
				 */

				$obj = new self();
				$_posts = $obj->get_post_pages( $post_type, $post_id, 1 );

				/**
				 * /Custom Query Builder
				 */

				if ( empty( $_posts ) ) {
					$__sent_status = 'ignore';
				} else {
					$__sent_status = NULL;
				}

				/**
				 * Escape and include the post_id, post_type, and content size in the query
				 */
				if ( NULL === $__sent_status ) {
					$values[] = "($post_id, '$post_type', '$post_status', NULL, $content_size)";
				} else {
					$values[] = "($post_id, '$post_type', '$post_status', 0, $content_size)";
				}
			}

			/**
			 * Combine the values and execute the query
			 */
			$insertSql .= implode( ', ', $values );
			$wpdb->query( $insertSql );

			/**
			 * Update progress
			 */
			$currentBatch++;
		}
	}

}

if ( class_exists( 'SEMANTIC_LB\Classes\Init' ) ) {
	\SEMANTIC_LB\Classes\Init::get_instance();
}
