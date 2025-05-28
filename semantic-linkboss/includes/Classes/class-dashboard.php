<?php
/**
 * Dashboard Handler
 *
 * @package SEMANTIC_LB
 * @since 2.7.0
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


use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Dashboard Handler
 *
 * @since 2.7.0
 */
class Dashboard {

	use Global_Functions;

	private static $instance = null;

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
	 */
	public function __construct() {
		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'dashboard';
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		
		// Add AJAX actions for URL Extractor
		add_action( 'wp_ajax_linkboss_get_categories', array( $this, 'ajax_get_categories' ) );
		add_action( 'wp_ajax_linkboss_get_category_post_urls', array( $this, 'ajax_get_category_post_urls' ) );
		add_action( 'wp_ajax_linkboss_get_tags', array( $this, 'ajax_get_tags' ) );
		add_action( 'wp_ajax_linkboss_get_tag_post_urls', array( $this, 'ajax_get_tag_post_urls' ) );
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
				'callback'            => array( $this, 'handle_dashboard' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
	}

	/**
	 * Check the permissions for getting the settings
	 *
	 * @since 2.7.0
	 */
	public function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Set Init
	 *
	 * @since 2.7.0
	 */
	public function handle_dashboard( WP_REST_Request $request ) {
		$params = $request->get_params();
		return $this->dashboard_welcome();
	}

	/**
	 * Dashboard Welcome
	 *
	 * @return WP_REST_Response
	 */
	public function dashboard_welcome() {
		$cache_key  = 'dci_dashboard_welcome_data';
		$cache_time = 2 * MINUTE_IN_SECONDS; // Cache for 12 hours

		$data = get_transient( $cache_key );
		$data = false;

		if ( false === $data ) {
			$data = array(
				'last_30_days_sync' => $this->last_30_days_sync(),
			);

			// Set the transient
			set_transient( $cache_key, $data, $cache_time );
		}

		return new WP_REST_Response(
			array(
				'message' => 'Welcome to the Dashboard!',
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Last 30 Days Sync Data
	 */
	public function last_30_days_sync() {
		global $wpdb;

		$query = "SELECT COUNT(*) AS total_sync, DATE(sync_at) AS date
        FROM {$wpdb->prefix}linkboss_sync_batch
        WHERE sync_at >= CURDATE() - INTERVAL 29 DAY AND sync_at IS NOT NULL
        GROUP BY DATE(sync_at)";

    // phpcs:ignore
    $last_30_days_sync = $wpdb->get_results( $query );

		$date        = array();
		$date_text   = array();
		$bg          = array();
		$result_data = array();

		for ( $i = 31; $i >= 0; $i-- ) {
			$_current_date = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$current_date = gmdate( 'Y-m-d', strtotime( $_current_date . ' +1 day' ) );
			$date[]       = $current_date;
			$date_text[] = gmdate( 'M j', strtotime( $current_date ) );

			// Assign a color for each date instead of using product_id
			$bg[ $current_date ] = $this->chartjs_bg_colors( $i );

			// Initialize data array for missing dates
			$result_data[ $current_date ] = 0;
		}

		foreach ( $last_30_days_sync as $value ) {
			$result_data[ $value->date ] = (int) $value->total_sync;
		}

		$sync_data = array();
		foreach ( $date as $day ) {
			$sync_data[] = isset( $result_data[ $day ] ) ? $result_data[ $day ] : 0;
		}

		$dataset = array(
			'label'            => 'Sync Data', // Generic label since product_id is not present
			'data'             => $sync_data,
			'borderWidth'      => 2,
			'fill'             => true,
			'dash_border'      => '',
			'background_fill'  => 'yes',
			'borderDash'       => array(),
			'pointStyle'       => 'circle',
			'pointBorderWidth' => 1.5,
			'tension'          => 0.5,
			'backgroundColor'  => array_values( $bg ),
		);

		$output_data = array(
			'labels'   => $date_text,
			'datasets' => array( $dataset ),
		);

		return $output_data;
	}


	/**
	 * ChartJS BG Colors
	 */
	public function chartjs_bg_colors( $id ) {
		$bg = array(
			'rgba(255, 99, 132, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(255, 206, 86, 0.4)',
			'rgba(75, 192, 192, 0.4)',
			'rgba(153, 102, 255, 0.4)',
			'rgba(255, 159, 64, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(104, 132, 245, 0.4)',
			'rgba(255, 99, 132, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(255, 206, 86, 0.4)',
			'rgba(75, 192, 192, 0.4)',
			'rgba(153, 102, 255, 0.4)',
			'rgba(255, 159, 64, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(255, 206, 86, 0.4)',
			'rgba(75, 192, 192, 0.4)',
			'rgba(153, 102, 255, 0.4)',
			'rgba(255, 159, 64, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(255, 206, 86, 0.4)',
			'rgba(75, 192, 192, 0.4)',
			'rgba(153, 102, 255, 0.4)',
			'rgba(255, 159, 64, 0.4)',
			'rgba(54, 162, 235, 0.4)',
			'rgba(255, 206, 86, 0.4)',
			'rgba(75, 192, 192, 0.4)',
			'rgba(153, 102, 255, 0.4)',
			'rgba(255, 159, 64, 0.4)',
		);

		$bg = array_unique( $bg );

		return ( isset( $bg[ $id ] ) ) ? $bg[ $id ] : 'rgba(255, 99, 132, 0.4)';
	}

	/**
	 * AJAX handler to get all post categories.
	 *
	 * @since 2.7.5
	 */
	public function ajax_get_categories() {
		check_ajax_referer( 'linkboss_ajax_nonce', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'semantic-linkboss' ) ), 403 );
		}

		$all_formatted_categories = array();

		// 1. Get standard categories
		$std_categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $std_categories ) && ! empty( $std_categories ) ) {
			foreach ( $std_categories as $category ) {
				$all_formatted_categories[] = array(
					'id'   => 'category:' . $category->term_id, // Unique ID format: taxonomy:term_id
					'name' => sprintf( '%s: %s', __( 'Category', 'semantic-linkboss' ), $category->name ), // Descriptive name
				);
			}
		} elseif ( is_wp_error( $std_categories ) ) {
			// Optionally log or handle the error, but don't stop the process
			error_log( 'LinkBoss Error: Failed to fetch standard categories - ' . $std_categories->get_error_message() );
		}

		// 2. Get WooCommerce Product Categories (if WooCommerce is active)
		if ( class_exists( 'WooCommerce' ) ) {
			$wc_categories = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $wc_categories ) && ! empty( $wc_categories ) ) {
				foreach ( $wc_categories as $category ) {
					// Skip 'Uncategorized' if it exists, as it might be confusing
					// Use term_id check as slug might not be reliable for default 'Uncategorized' across languages
					$default_product_cat_id = get_option( 'default_product_cat', 0 );
					if ( $category->term_id === $default_product_cat_id ) {
						continue;
					}
					$all_formatted_categories[] = array(
						'id'   => 'product_cat:' . $category->term_id, // Unique ID format
						'name' => sprintf( '%s: %s', __( 'Product Category', 'semantic-linkboss' ), $category->name ), // Descriptive name
					);
				}
			} elseif ( is_wp_error( $wc_categories ) ) {
				// Optionally log or handle the error
				error_log( 'LinkBoss Error: Failed to fetch WooCommerce categories - ' . $wc_categories->get_error_message() );
			}
		}

		// Sort categories alphabetically by name for better UX
		usort(
			$all_formatted_categories,
			function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] ); // Natural case-insensitive sort
			}
		);

		wp_send_json_success( $all_formatted_categories );
	}

	/**
	 * AJAX handler to get published post URLs for a specific category/term.
	 * Handles combined 'taxonomy:term_id' identifier.
	 *
	 * @since 2.7.5
	 */
	public function ajax_get_category_post_urls() {
		check_ajax_referer( 'linkboss_ajax_nonce', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'semantic-linkboss' ) ), 403 );
		}

		$combined_id = isset( $_POST['category_id'] ) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '';

		if ( empty( $combined_id ) || strpos( $combined_id, ':' ) === false ) {
			wp_send_json_error( array( 'message' => __( 'Invalid category identifier.', 'semantic-linkboss' ) ), 400 );
		}

		list( $taxonomy, $term_id ) = explode( ':', $combined_id, 2 );
		$term_id = absint( $term_id );

		if ( empty( $taxonomy ) || empty( $term_id ) || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid taxonomy or term ID.', 'semantic-linkboss' ) ), 400 );
		}

		// Determine post type based on taxonomy
		$post_type = 'post'; // Default for 'category' taxonomy
		if ( 'product_cat' === $taxonomy && post_type_exists( 'product' ) ) {
			$post_type = 'product';
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1, // Get all posts
			'tax_query'      => array( // Use tax_query instead of 'cat'
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
			'fields'         => 'ids', // Only fetch post IDs for performance
		);

		$post_ids = get_posts( $args );

		if ( is_wp_error( $post_ids ) ) {
			wp_send_json_error( array( 'message' => $post_ids->get_error_message() ), 500 );
		}

		$urls = array();
		if ( ! empty( $post_ids ) ) {
			// Use array_map with a closure for better compatibility if needed,
			// but direct function name usually works.
			$urls = array_map( 'get_permalink', $post_ids );
		}

		wp_send_json_success( $urls );
	}

	/**
	 * AJAX handler to get all post tags.
	 *
	 * @since 2.7.5
	 */
	public function ajax_get_tags() {
		check_ajax_referer( 'linkboss_ajax_nonce', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'semantic-linkboss' ) ), 403 );
		}

		$all_formatted_tags = array();

		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$all_formatted_tags[] = array(
					'id'   => 'post_tag:' . $tag->term_id, // Unique ID format: taxonomy:term_id
					'name' => sprintf( '%s: %s', __( 'Tag', 'semantic-linkboss' ), $tag->name ), // Descriptive name
				);
			}
		} elseif ( is_wp_error( $tags ) ) {
			error_log( 'LinkBoss Error: Failed to fetch post tags - ' . $tags->get_error_message() );
			// Send error if needed, or just return empty array
			// wp_send_json_error( array( 'message' => __( 'Failed to fetch tags.', 'semantic-linkboss' ) ), 500 );
		}

		// Sort tags alphabetically by name
		usort(
			$all_formatted_tags,
			function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		wp_send_json_success( $all_formatted_tags );
	}

	/**
	 * AJAX handler to get published post URLs for a specific tag.
	 * Handles 'post_tag:term_id' identifier.
	 *
	 * @since 2.7.5
	 */
	public function ajax_get_tag_post_urls() {
		check_ajax_referer( 'linkboss_ajax_nonce', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'semantic-linkboss' ) ), 403 );
		}

		$combined_id = isset( $_POST['tag_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_id'] ) ) : '';

		if ( empty( $combined_id ) || strpos( $combined_id, ':' ) === false ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tag identifier.', 'semantic-linkboss' ) ), 400 );
		}

		list( $taxonomy, $term_id ) = explode( ':', $combined_id, 2 );
		$term_id = absint( $term_id );

		// Specifically check for 'post_tag' taxonomy
		if ( 'post_tag' !== $taxonomy || empty( $term_id ) || ! term_exists( $term_id, 'post_tag' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid taxonomy or term ID for tag.', 'semantic-linkboss' ) ), 400 );
		}

		$args = array(
			'post_type'      => 'post', // Tags are typically associated with 'post' post type
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
			'fields'         => 'ids',
		);

		$post_ids = get_posts( $args );

		if ( is_wp_error( $post_ids ) ) {
			wp_send_json_error( array( 'message' => $post_ids->get_error_message() ), 500 );
		}

		$urls = array();
		if ( ! empty( $post_ids ) ) {
			$urls = array_map( 'get_permalink', $post_ids );
		}

		wp_send_json_success( $urls );
	}
}
