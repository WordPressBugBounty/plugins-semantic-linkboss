<?php
/**
 * Cron Handler
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;
use SEMANTIC_LB\Classes\Updates;
use SEMANTIC_LB\Classes\Update_Posts;
use SEMANTIC_LB\Classes\Posts;
use SEMANTIC_LB\Classes\Init;

/**
 * Description of Cron
 *
 * @since 0.0.0
 */
class Cron {
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

		register_deactivation_hook( SEMANTIC_LB__FILE__, array( $this, 'deactivate' ) );

		/**
		 * Get Access Token
		 *
		 * @since 0.0.5
		 */
		$this->create_cron_job( 'linkboss_access_token', 170 * DAY_IN_SECONDS, 'refresh_access_token' );

		/**
		 * Fetch Posts & Update to WordPress
		 *
		 * @since 0.0.5
		 */
		$this->create_cron_job( 'linkboss_fetch_and_update_posts', MINUTE_IN_SECONDS * 2, 'fetch_update_posts' );

		/**
		 * Batch Processing for Sync Posts
		 *
		 * @since 2.0.5
		 */
		$this->create_cron_job( 'linkboss_ready_batch_for_process', 4 * HOUR_IN_SECONDS, 'ready_batch_for_process' );

		/**
		 * Insert Table IDs for Batch Sync
		 *
		 * @since 0.1.0
		 */
		$this->create_cron_job( 'linkboss_init_table_ids_for_batch', 2 * HOUR_IN_SECONDS, 'init_table_ids_for_batch' );

		/**
		 * Sync Posts to LinkBoss on Post Draft Manually
		 *
		 * @since 0.1.0
		 */
		// add_action( 'draft_post', array( $this, 'sync_posts_on_post_update' ), 20, 3 );

		/**
		 * Sync Posts to LinkBoss on Post Trash Manually
		 *
		 * @since 0.1.0
		 */
		add_action( 'trash_post', array( $this, 'sync_posts_on_post_update' ), 20, 3 );

		/**
		 * Sync Posts to LinkBoss on Post Publish Manually
		 *
		 * @since 0.1.0
		 */
		add_action( 'publish_post', array( $this, 'sync_posts_on_post_update' ), 20, 3 );

		/**
		 * Get data of Custom Query Builder
		 * 
		 * @since 2.3.0
		 */
		$query_data = get_option( 'linkboss_custom_query', '' );
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array('');

		/**
		 * Remove 'page' and 'post' from post_type
		 */
		if ( in_array( 'post', $post_type ) ) {
			$key = array_search( 'post', $post_type );
			unset( $post_type[ $key ] );
		}

		if ( in_array( 'page', $post_type ) ) {
			$key = array_search( 'page', $post_type );
			unset( $post_type[ $key ] );
		}

		/**
		 * If not 'post' or 'page' then add custom post type to hook
		 * 
		 * @since 2.3.0
		 */
		if ( ! empty( $post_type ) ) {
			foreach ( $post_type as $post ) {
				add_action( 'publish_' . $post, array( $this, 'sync_posts_on_post_update' ), 20, 3 );
				add_action( 'trash_' . $post, array( $this, 'sync_posts_on_post_update' ), 20, 3 );
			}
		}

	}

	/**
	 * Create a custom cron job
	 *
	 * @param string $hook The unique hook name for this job.
	 * @param int $interval The interval in seconds.
	 * @param string $callback The name of the callback method.
	 */
	public function create_cron_job( $hook, $interval, $callback ) {
		/**
		 * Add a filter for custom schedule with the provided interval and $hook
		 */
		add_filter( 'cron_schedules', function ($schedules) use ($hook, $interval) {
			$schedules[ $hook ] = array (
				'interval' => $interval,
				'display' => esc_html__( 'Custom Schedule', 'semantic-linkboss' ),
			);
			return $schedules;
		}, 10, 1 );

		if ( ! wp_next_scheduled( $hook ) ) {
			/**
			 * Use $hook for the schedule name
			 */
			wp_schedule_event( time(), $hook, $hook );
		}

		add_action( $hook, array( $this, $callback ) );
	}

	/**
	 * Add a custom schedule
	 *
	 * @param array $schedules The existing schedules.
	 * @param int $interval The interval in seconds.
	 * @return array The modified schedules.
	 */
	public function add_custom_schedule( $schedules ) {
		$schedules['custom'] = array(
			'interval' => 60, // Change the interval as needed
			'display' => __( 'Custom Schedule', 'semantic-linkboss' ),
		);

		return $schedules;
	}

	/**
	 * Deactivate and unschedule all custom cron jobs
	 */
	public function deactivate() {
		$custom_jobs = array(
			'linkboss_access_token',
			'linkboss_fetch_and_update_posts',
			'linkboss_sync_posts_to_server',
		);

		foreach ( $custom_jobs as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	/**
	 * Sync Posts to LinkBoss on Post Update Manually
	 *
	 * @since 0.0.6
	 */
	public static function sync_posts_on_post_update( $post_id ) {

		// error_log( print_r( 'hook fired', true ) );
		
		// check which hook fired
		// error_log( print_r( current_filter(), true ) );
		// error_log( print_r( $post_id, true ) );


		$updates_obj = new Updates();
		$updates_obj->data_sync_require( $post_id );

		Posts::sync_posts_by_cron_and_hook();
	}

	/**
	 * Check if meta _elementor_data exists for a post
	 *
	 * @param int $post_id
	 * @return boolean
	 */
	public static function is_elementor_post( $post_id ) {

		$elementor_data = metadata_exists( 'post', $post_id, '_elementor_data' );

		if ( $elementor_data ) {
			return true;
		}

		return false;
	}

	/**
	 * Get Access Token
	 *
	 * @since 0.0.5
	 */
	public static function refresh_access_token() {
		Auth::refresh_access_token();
	}

	/**
	 * Fetch Posts & Update to WordPress
	 *
	 * @since 0.0.5
	 */
	public static function fetch_update_posts() {
		Update_Posts::fetch_update_posts();
	}

	/**
	 * Sync Posts to LinkBoss
	 *
	 * @since 0.0.5
	 */
	public static function sync_posts() {
		Posts::sync_posts_by_cron_and_hook();
	}

	/**
	 * Remove rows from sync table when status is D and sync_at time gone 3 days
	 *
	 * @since 0.0.7
	 */
	public static function remove_rows_from_sync_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'linkboss_sync_batch';
		$query = sprintf( 'TRUNCATE TABLE %s', $table );
		// phpcs:ignore
		$wpdb->query( $query );
	}

	/**
	 * Init Table IDs
	 *
	 * @since 0.1.0
	 */
	public static function init_table_ids_for_batch() {
		Init::init_table_ids_for_batch( true );
	}

	/**
	 * Init Table IDs
	 *
	 * @since 0.1.0
	 */
	public static function ready_batch_for_process() {
		Posts::cron_ready_batch_for_process();
		Posts::sync_posts_by_cron_and_hook();
	}

}

if ( class_exists( 'SEMANTIC_LB\Classes\Cron' ) ) {
	\SEMANTIC_LB\Classes\Cron::get_instance();
}
