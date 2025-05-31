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
use SEMANTIC_LB\Classes\Sync_Posts;
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
		$this->create_cron_job( 'linkboss_ready_batch_for_process', MINUTE_IN_SECONDS * 20, 'ready_batch_for_process' );

		/**
		 * Insert Table IDs for Batch Sync
		 *
		 * @since 0.1.0
		 */
		$this->create_cron_job( 'linkboss_init_posts_ids_batch', 2 * HOUR_IN_SECONDS, 'init_posts_ids_batch' );

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
		$post_type  = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post', 'page' );

		/**
		 * If custom post types are specified, ensure 'post' and 'page' are included if they are in the custom query
		 */
		if ( ! empty( $query_data['post_sources'] ) ) {
			if ( ! in_array( 'post', $post_type ) && in_array( 'post', $query_data['post_sources'] ) ) {
				$post_type[] = 'post';
			}

			if ( ! in_array( 'page', $post_type ) && in_array( 'page', $query_data['post_sources'] ) ) {
				$post_type[] = 'page';
			}
		}

		/**
		 * Add custom post types to hook if specified
		 *
		 * @since 2.3.0
		 */
		if ( ! empty( $post_type ) ) {
			foreach ( $post_type as $post ) {
				add_action( 'publish_' . $post, array( $this, 'sync_posts_on_post_update' ), 20, 3 );
				add_action( 'trash_' . $post, array( $this, 'sync_posts_on_post_update' ), 20, 3 );
			}
		}

		/**
		 * If no custom post types are specified, default to 'post' and 'page'
		 */
		if ( empty( $query_data['post_sources'] ) ) {
			add_action( 'publish_post', array( $this, 'sync_posts_on_post_update' ), 20, 3 );
			add_action( 'trash_post', array( $this, 'sync_posts_on_post_update' ), 20, 3 );

			add_action( 'publish_page', array( $this, 'sync_posts_on_post_update' ), 20, 3 );
			add_action( 'trash_page', array( $this, 'sync_posts_on_post_update' ), 20, 3 );
		}

		add_action( 'save_post', array( $this, 'post_update_save_trigger' ), 10, 3 );

		/**
		 * Sync Posts to LinkBoss after ACF fields are saved.
		 * Priority 20 ensures it runs after ACF's own save actions (default 10).
		 *
		 * @since 2.7.1
		 */
		add_action( 'acf/save_post', array( $this, 'trigger_sync_for_acf_post' ), 20 );

		/**
		 * Reset the processed posts tracker at the end of the request.
		 *
		 * @since 2.7.1
		 */
		// Changed target class to Sync_Posts in v2.7.1
		add_action( 'shutdown', array( 'SEMANTIC_LB\Classes\Sync_Posts', 'reset_processed_posts' ) );
	}


	public function post_update_save_trigger( $post_id, $post, $update ) {
		// Avoid infinite loops caused by auto-saves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip revisions and auto-draft posts.
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		// Skip if the post is not published.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		/**
		 * If ACF support is enabled and this post has ACF fields,
		 * let the 'acf/save_post' hook handle the sync.
		 *
		 * @since 2.7.1
		 */
		$acf_enabled = get_option( 'linkboss_acf_enabled', false );
		if ( $acf_enabled && function_exists( 'get_fields' ) && get_fields( $post_id ) ) {
			return;
		}

		// skip if post meta _elementor_data exists (and it's not an ACF post handled above)
		if ( self::is_elementor_post( $post_id ) ) {
			return;
		}

		// Perform sync for non-ACF posts or when ACF support is disabled.
		// Pass post_id to the sync function for the guard check.
		self::sync_posts_on_post_update( $post_id );
	}

	/**
	 * Trigger sync specifically for posts after ACF fields are saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @since 2.7.1
	 */
	public function trigger_sync_for_acf_post( $post_id ) {
		// Check if ACF support is enabled in settings.
		$acf_enabled = get_option( 'linkboss_acf_enabled', false );
		if ( ! $acf_enabled ) {
			return;
		}

		// Get the post object.
		$post = get_post( $post_id );

		// Basic checks similar to post_update_save_trigger.
		if ( ! $post || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Add check to prevent this hook from syncing Elementor posts v2.7.1
		if ( self::is_elementor_post( $post_id ) ) {
			return;
		}

		// Perform the sync.
		// Pass post_id to the sync function for the guard check.
		self::sync_posts_on_post_update( $post_id );
	}

	/**
	 * Create a custom cron job
	 *
	 * @param string $hook The unique hook name for this job.
	 * @param int    $interval The interval in seconds.
	 * @param string $callback The name of the callback method.
	 */
	public function create_cron_job( $hook, $interval, $callback ) {
		/**
		 * Add a filter for custom schedule with the provided interval and $hook
		 */
		add_filter(
			'cron_schedules',
			function ( $schedules ) use ( $hook, $interval ) {
				$schedules[ $hook ] = array(
					'interval' => $interval,
					'display'  => esc_html__( 'Custom Schedule', 'semantic-linkboss' ),
				);
				return $schedules;
			},
			10,
			1
		);

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
	 * @param int   $interval The interval in seconds.
	 * @return array The modified schedules.
	 */
	public function add_custom_schedule( $schedules ) {
		$schedules['custom'] = array(
			'interval' => 60, // Change the interval as needed
			'display'  => __( 'Custom Schedule', 'semantic-linkboss' ),
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
		// Add check to prevent general hooks (like publish_*) from syncing Elementor posts v2.7.1
		// The Elementor-specific hook elementor/document/after_save should handle these.
		if ( self::is_elementor_post( $post_id ) ) {
			return;
		}

		// Guard logic moved to Sync_Posts::sync_posts_by_cron_and_hook in v2.7.1

		$updates_obj = new Updates();
		$updates_obj->data_sync_require( $post_id );

		// Pass post_id to the core sync function
		Sync_Posts::sync_posts_by_cron_and_hook( $post_id );
	}

	// Removed reset_processed_posts method as it's now handled in Sync_Posts class.

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
		Sync_Posts::sync_posts_by_cron_and_hook();
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
	public static function init_posts_ids_batch() {
		Init::init_posts_ids_batch( true );
	}

	/**
	 * Init Table IDs
	 *
	 * @since 0.1.0
	 */
	public static function ready_batch_for_process() {
		Sync_Posts::cron_ready_batch_for_process();
		Sync_Posts::sync_posts_by_cron_and_hook();
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Cron' ) ) {
	\SEMANTIC_LB\Classes\Cron::get_instance();
}
