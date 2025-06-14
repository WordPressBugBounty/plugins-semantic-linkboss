<?php
/**
 * Trait Global Functions
 *
 * @package SEMANTIC_LB
 * @since 1.0.0
 */

namespace SEMANTIC_LB\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;

/**
 * Global Functions
 *
 * @since 1.0.0
 */
trait Global_Functions {

	/**
	 * Get Post & Pages
	 *
	 * @since 2.3.0
	 */
	public function get_post_pages( $_post_type = false, $post__in = false, $number_posts = -1, $post_status = array( 'publish' ) ) {
		
		// Check if we're using URL-based syncing
		$query_data = get_option('linkboss_custom_query', '');
		$is_url_sync = isset($query_data['sync_by']) && 'urls' === $query_data['sync_by'];
		
		// Special handling for URL-based syncing with specific post IDs
		if ($is_url_sync && false !== $post__in) {
			
			// For URL-based syncing, we need to get the actual post type of each post
			// rather than using the default post types from the query builder
			$all_posts = array();
			
			$post_ids = is_array($post__in) ? $post__in : array($post__in);
			
			foreach ($post_ids as $post_id) {
				$post = get_post($post_id);
				if ($post) {
					$all_posts[] = $post;
				}
			}
			
			return $all_posts;
		}
		
		/**
		 * Get data of Custom Query Builder
		 */
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post', 'page' );

		$is_page = in_array( 'page', $post_type ) || ( 'page' === $_post_type ) ? true : false;

		$__post_type = $post_type;
		// remove 'page' from the array
		if ( false === $_post_type ) {
			$__post_type = array_diff( $post_type, array( 'page' ) );
		}

		if ( 'page' === $_post_type ) {
			$__post_type = array_diff( $post_type, array( 'page' ) );
		}

		$posts_args = array(
			'numberposts' => $number_posts,
			'post_type' => $__post_type,
			'post_status' => $post_status,
			'tax_query' => array(
				'relation' => 'OR',
			),
		);

		/**
		 * Add categories to the query if available
		 */
		$cat = isset( $query_data['__categories'] ) ? $query_data['__categories'] : '';
		if ( ! empty( $cat ) ) {
			$posts_args['tax_query'] = array_merge( $posts_args['tax_query'], $cat );
		}

		/**
		 * Filter by post__in
		 * post_id or array of post_ids
		 */
		if ( false !== $post__in ) {
			$posts_args['post__in'] = is_array( $post__in ) ? $post__in : array( $post__in );
		}

		/**
		 * Get Posts
		 */
		$posts = get_posts( $posts_args );
		
		$pages = array();

		/**
		 * Get Pages if post_type is page
		 * and post__in is not set, because we are already getting the post by post__in
		 */
		$page_yes = false;
		if ( 'page' === $_post_type && false !== $post__in ) {
			/**
			 * This is for class-ajax-init.php
			 */
			$page_args = array(
				'post_type' => 'page',
				'posts_per_page' => $number_posts,
				'post_status' => $post_status,
			);
			if ( false !== $post__in ) {
				$page_args['post__in'] = is_array( $post__in ) ? $post__in : array( $post__in );
			}

			$pages = get_posts( $page_args );
			$page_yes = true;
			
		}

		/**
		 * Get Pages if post_type is not set that means we are getting all posts and pages
		 * and post__in is not set, because we are already getting the post by post__in
		 * Will be used everywhere except class-ajax-init.php
		 */
		if ( false === $_post_type ) {
			$page_args = array(
				'post_type' => 'page',
				'posts_per_page' => $number_posts,
				'post_status' => $post_status,
			);

			if ( false !== $post__in ) {
				$page_args['post__in'] = is_array( $post__in ) ? $post__in : array( $post__in );
			}

			if ( false === $page_yes ) {
				$pages = get_posts( $page_args );
				
			}
		}

		/**
		 * If post_type is page & ignored the 'post', then set posts to empty array
		 */
		if ( count( $post_type ) === 1 && in_array( 'page', $post_type ) ) {
			$posts = array();
		}

		/**
		 * If post_type is post & ignored the 'page', then set pages to empty array
		 */
		if ( count( $post_type ) === 1 && in_array( 'post', $post_type ) ) {
			$pages = array();
		}

		if ( false === $is_page ) {
			$pages = array();
		}

		/**
		 * Merge posts and pages
		 */
		$all_posts = array_merge( $posts, $pages );
		
		return $all_posts;
	}

	/**
	 * Posts Count
	 * For the report
	 */
	public function report_posts_count() {
		// return;
		/**
		 * Get data of Custom Query Builder
		 */
		$query_data = get_option( 'linkboss_custom_query', false );
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post' );

		/**
		 * Remove 'page' from the array
		 */
		$post_type = array_diff( $post_type, array( 'page' ) );

		/**
		 * Add categories to the query if available
		 */
		$cat = isset( $query_data['__categories'] ) ? $query_data['__categories'] : '';

		$post_counts = array();
		foreach ( $post_type as $type ) {
			$args = array(
				'post_type' => $type,
				'post_status' => array( 'publish' ),
				'posts_per_page' => 1,
				'tax_query' => array(
					'relation' => 'OR',
				),
			);

			if ( ! empty( $cat ) ) {
				$args['tax_query'] = array_merge( $args['tax_query'], $cat );
			}

			$query = new \WP_Query( $args );

			$post_counts[ $type ] = array(
				'publish' => $query->found_posts,
				'total' => $query->found_posts,
			);
		}

		$total_count = array_reduce(
			$post_counts,
			function ( $carry, $counts ) {
				return $carry + $counts['total'];
			},
			0
		);

		// Now $total_count holds the total number of posts across the specified post types and statuses, filtered by categories if specified

		return $total_count;
	}

	/**
	 * Report Pages
	 */
	public function report_pages_count() {
		/**
		 * Get data of Custom Query Builder
		 */

		$pages = wp_count_posts( 'page' )->publish;

		$query_data = get_option( 'linkboss_custom_query', false );
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( '' );

		if ( false !== $query_data && ! in_array( 'page', $post_type ) ) {
			$pages = 0;
		}

		return $pages;
	}
}
