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

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

use SEMANTIC_LB\Installer;

/**
 * Description of Settings
 *
 * @since 0.0.0
 */
class Settings {

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
	 * @since 0.0.0
	 */
	public function __construct() {

		$this->namespace = 'linkboss/v1';
		$this->rest_base = 'settings';
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

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
		if ( isset( $page ) && 'semantic-linkboss' === $page ) {
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
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'get_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/update',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'set_settings' ),
				'permission_callback' => array( $this, 'update_permissions_check' ),
			)
		);
	}

	/**
	 * Get the settings
	 *
	 * @since 2.7.0
	 */
	public function get_settings( WP_REST_Request $request ) {

		$params = $request->get_params();

		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : false;

		if ( ! $action ) {
			return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}

		switch ( $action ) {
			case 'api_key':
				$api_key = get_option( 'linkboss_api_key', false );

				if ( ! $api_key ) {
					return new WP_Error( 'no_api_key', esc_html__( 'Oops, API key is not found.' ), array( 'status' => 404 ) );
				}

				return rest_ensure_response(
					array(
						'status'  => 'success',
						'message' => 'API Key fetched successfully',
						'api_key' => $api_key,
					),
					200
				);

			case 'get_wp_post_types':
				$post_types = self::get_wp_post_types();

				if ( ! $post_types ) {
					return new WP_Error( 'no_post_types', esc_html__( 'Oops, Post types are not found.' ), array( 'status' => 404 ) );
				}

				return rest_ensure_response( $post_types, 200 );
			case 'get_custom_query':
				$linkboss_qb = get_option( 'linkboss_custom_query', '' );

				if ( ! $linkboss_qb ) {
					return new WP_Error( 'no_custom_query', esc_html__( 'Oops, Custom Query is not found.' ), array( 'status' => 200 ) );
				}

				/*
				return rest_ensure_response( array(
					'status' => 'success',
					'message' => 'Custom Query fetched successfully',
					'data' => $linkboss_qb,
				), 200 );
				*/

				return rest_ensure_response( $linkboss_qb, 200 );

			case 'get_cats_by_post_type':
				/**
				 * if empty post type, then check if exists in db and return
				 */
				if ( ! isset( $params['post_type'] ) || empty( $params['post_type'] ) ) {
					$linkboss_qb         = get_option( 'linkboss_custom_query', '' );
					$post_sources        = isset( $linkboss_qb['post_sources'] ) ? $linkboss_qb['post_sources'] : array();
					$params['post_type'] = $post_sources;
				}

				/**
				 * Get the categories by post type users input || default post and page
				 */
				$post_types = isset( $params['post_type'] ) && ! empty( $params['post_type'] ) ? (array) $params['post_type'] : array( 'post', 'page' );

				$categories = $this->get_cats_by_post_type( $post_types );

				if ( is_wp_error( $categories ) ) {
					return new WP_Error( 'no_categories', esc_html__( 'Oops, Categories are not found.' ), array( 'status' => 404 ) );
				}

				return $categories;

			case 'get_sync_speed':
				$sync_speed = get_option( 'linkboss_sync_speed', 512 );

				if ( ! $sync_speed ) {
					return new WP_Error( 'no_sync_speed', esc_html__( 'Oops, Sync Speed is not found.' ), array( 'status' => 404 ) );
				}

				return rest_ensure_response(
					array(
						'status'     => 'success',
						'message'    => 'Sync Speed fetched successfully',
						'sync_speed' => $sync_speed,
					),
					200
				);
			default:
				return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'status'  => 'success',
				'message' => 'Settings fetched successfully',
				'data'    => 'Settings',
			),
			200
		);
	}

	/**
	 * Set the settings
	 *
	 * @since 2.7.0
	 */
	public function set_settings( WP_REST_Request $request ) {

		$params = $request->get_params();

		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : false;

		if ( ! $action ) {
			return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}

		switch ( $action ) {
			case 'save_query':
				$linkboss_qb = isset( $params['linkboss_qb'] ) ? $params['linkboss_qb'] : false;

				if ( ! $linkboss_qb ) {
					return new WP_Error( 'no_custom_query', esc_html__( 'Oops, Custom Query is not found.' ), array( 'status' => 404 ) );
				}

				$result = $this->save_custom_query( $linkboss_qb );

				if ( ! $result ) {
					return new WP_Error( 'no_custom_query', esc_html__( 'Oops, Custom Query is not saved.' ), array( 'status' => 404 ) );
				}

				/**
				 * Reset Sync Batch Table after saving custom query
				 */
				$this->reset_sync_batch();

				return rest_ensure_response(
					array(
						'status'  => 'success',
						'message' => 'Custom Query saved successfully',
						'data'    => $linkboss_qb,
					),
					200
				);

			case 'save_sync_speed':
				$sync_speed = isset( $params['sync_speed'] ) ? sanitize_text_field( $params['sync_speed'] ) : 512;

				if ( ! $sync_speed ) {
					return new WP_Error( 'no_sync_speed', esc_html__( 'Oops, Sync Speed is not found.' ), array( 'status' => 404 ) );
				}

				update_option( 'linkboss_sync_speed', (int) $sync_speed );

				return rest_ensure_response(
					array(
						'status'  => 'success',
						'message' => 'Sync Speed saved successfully',
						'data'    => $sync_speed,
					),
					200
				);
			case 'reset_sync_batch':
				$this->reset_sync_batch();

				return rest_ensure_response(
					array(
						'status'  => 'success',
						'message' => 'Sync Batch reset successfully',
					),
					200
				);

			default:
				return new WP_Error( 'no_settings', esc_html__( 'Oops, Settings is not found.' ), array( 'status' => 404 ) );
		}
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
	 * Get all post types
	 *
	 * @since 2.7.0
	 */
	public static function get_wp_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$post_types = array_column( $post_types, 'label', 'name' );

		// also count the number of posts in each post type and add it to the label
		foreach ( $post_types as $post_type => $label ) {
			$count_posts              = wp_count_posts( $post_type );
			$post_types[ $post_type ] = $label . ' (' . $count_posts->publish . ')';
		}

		$ignore_post_types = array(
			'elementor_library' => '',
			'attachment'        => '',
		);

		$post_types = array_diff_key( $post_types, $ignore_post_types );
		return $post_types;
	}

	/**
	 * Get Categories List by Post Type
	 *
	 * @since 2.7.0
	 */
	public function get_cats_by_post_type( $post_types = array( 'post', 'page' ) ) {

		/**
		 * Remove page from custom post types
		 */
		$post_types = array_diff( $post_types, array( 'page' ) );

		$categories = array();

		foreach ( $post_types as $custom_post_type ) {
			/**
			 * Get the taxonomy associated with the custom post type
			 */
			$taxonomy = get_object_taxonomies( $custom_post_type )[0]; // Assuming only one taxonomy is associated

			/**
			 * Get the terms (categories) associated with the taxonomy
			 */
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false, // Include empty categories
				)
			);

			/**
			 * Add the terms to the categories array
			 */
			$categories = array_merge( $categories, $terms );
		}

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$categories = array_map(
				function ( $category ) {
					return array(
						'id'       => $category->term_id,
						'slug'     => $category->slug,
						'taxonomy' => $category->taxonomy,
						'name'     => $category->name,
					);
				},
				$categories
			);

			return rest_ensure_response( $categories, 200 );
		} else {
			return new WP_Error( 'no_categories', esc_html__( 'Oops, Categories are not found.' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Save Custom Query
	 *
	 * @since 2.7.0
	 */
	public function save_custom_query( $linkboss_qb ) {

		$categories   = array();
		$__categories = array();

		if ( isset( $linkboss_qb['categories'] ) && is_array( $linkboss_qb['categories'] ) ) {
			/**
			 * Get the category IDs array
			 */
			$category_ids = $linkboss_qb['categories'];
			/**
			 * Get the corresponding term objects
			 */
			$categories = array_map(
				function ( $category_id ) {
					return (array) get_term( $category_id );
				},
				$category_ids
			);

			$linkboss_qb['_categories'] = $categories;

			/**
			 * Make more easy to access
			 */

			foreach ( $categories as $category ) {
				$taxonomy       = $category['taxonomy'];
				$existing_index = array_search( $taxonomy, array_column( $__categories, 'taxonomy' ) );

				if ( false !== $existing_index ) {
					// Merge terms into existing array
					$__categories[ $existing_index ]['terms'][] = $category['term_id'];
				} else {
					// Add new array to $__categories
					$__categories[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( $category['term_id'] ),
					);
				}
			}

			$linkboss_qb['__categories'] = $__categories;
		}

		update_option( 'linkboss_custom_query', $linkboss_qb );

		if ( isset( $linkboss_qb['post_sources'] ) && empty( $linkboss_qb['post_sources'] ) ) {
			delete_option( 'linkboss_custom_query' );
		}

		return true;
	}

	/**
	 * Reset Sync Batch
	 *
	 * @since 0.0.5
	 */
	public function reset_sync_batch() {
		global $wpdb;
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

		return true;
	}
}
