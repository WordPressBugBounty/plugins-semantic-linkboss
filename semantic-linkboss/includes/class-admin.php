<?php
/**
 * Admin class
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description of Menu
 *
 * @since 0.0.0
 */

class Admin {
	/**
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$layouts = new Admin\Layouts();
		$this->dispatch_actions();

		new Admin\Menu( $layouts );
		$this->ajax_handler();
	}

	/**
	 * Dispatch Actions
	 *
	 * @since 1.0.0
	 */
	public function dispatch_actions() {
		new Classes\Auth();
		new Classes\Settings();
		new Classes\Render();

		$api_valid = get_option( 'linkboss_api_key', false );

		if ( $api_valid ) {
			new Classes\Posts();
			new Classes\Ajax_Sync();
		}
	}

	/**
	 * Ajax Handler
	 * 
	 * @since 2.2.6
	 */
	public function ajax_handler() {
		add_action( 'wp_ajax_linkboss_get_cats_by_post_type', array( $this, 'get_categories_list_by_post_type' ) );
		add_action( 'wp_ajax_linkboss_custom_query', array( $this, 'save_custom_query' ) );
		add_action( 'wp_ajax_linkboss_get_custom_query', array( $this, 'get_custom_query' ) );
	}


	/**
	 * Get Categories List by Post Type
	 */
	public function get_categories_list_by_post_type() {

		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'linkboss_nonce' ) ) {
			wp_die( 'Invalid nonce' );
		}

		$post_types = isset( $_POST['post_type'] ) && ! empty( $_POST['post_type'] ) ? (array) wp_unslash( $_POST['post_type'] ) : array( 'post', 'page' );

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
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false, // Include empty categories
			) );

			/**
			 * Add the terms to the categories array
			 */
			$categories = array_merge( $categories, $terms );
		}


		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$categories = array_map( function ($category) {
				return array(
					'id' => $category->term_id,
					'slug' => $category->slug,
					'taxonomy' => $category->taxonomy,
					'name' => $category->name,
				);
			}, $categories );

			echo wp_json_encode( $categories, true );
		} else {
			echo wp_json_encode( array(
				'status' => false,
				'msg' => esc_html__( 'No categories found.', 'semantic-linkboss' ),
			), true );
		}
		wp_die();

	}

	/**
	 * Save Custom Query
	 * 
	 * @since 2.2.6
	 */
	public function save_custom_query() {

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

		// print_r($_POST);

		if ( empty( $_POST['linkboss_qb'] ) ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html__( 'Please enter a custom query.', 'semantic-linkboss' ),
				), true
			);
			wp_die();
		}

		$linkboss_qb = $_POST['linkboss_qb'];
		$categories = array();
		$__categories = array();

		if ( isset( $linkboss_qb['categories'] ) && is_array( $linkboss_qb['categories'] ) ) {
			/**
			 * Get the category IDs array
			 */
			$category_ids = $linkboss_qb['categories']; // 

			/**
			 * Get the corresponding term objects
			 */
			$categories = array_map( function ($category_id) {
				return (array) get_term( $category_id );
			}, $category_ids );

			$linkboss_qb['_categories'] = $categories;

			/**
			 * Make more easy to access
			 */

			foreach ( $categories as $category ) {
				$taxonomy = $category['taxonomy'];
				$existing_index = array_search( $taxonomy, array_column( $__categories, 'taxonomy' ) );

				if ( $existing_index !== false ) {
					// Merge terms into existing array
					$__categories[ $existing_index ]['terms'][] = $category['term_id'];
				} else {
					// Add new array to $__categories
					$__categories[] = [ 
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => [ $category['term_id'] ],
					];
				}
			}

			$linkboss_qb['__categories'] = $__categories;
		}

		update_option( 'linkboss_custom_query', $linkboss_qb );

		echo wp_json_encode(
			array(
				'status' => 'success',
				'title' => 'Success!',
				'msg' => esc_html__( 'Saved successfully, Please wait for the page to reload after pressing OK.', 'semantic-linkboss' ),
			)
		);
		wp_die();
	}

	/**
	 * Get Custom Query
	 */
	public function get_custom_query() {
		$linkboss_qb = get_option( 'linkboss_custom_query', '' );

		echo wp_json_encode( $linkboss_qb, true );
		wp_die();
	}
}
