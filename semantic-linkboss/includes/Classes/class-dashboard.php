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
}
