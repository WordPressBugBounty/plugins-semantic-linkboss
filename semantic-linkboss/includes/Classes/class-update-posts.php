<?php

/**
 * Update Posts Handler
 * Fetch Posts from LinkBoss and Update to WordPress
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Updates;
use SEMANTIC_LB\Classes\Auth;

/**
 * Description of Update Posts
 *
 * @since 0.0.0
 */
class Update_Posts {
	private static $instance = null;

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_lb_fetch_update_posts', [ $this, 'fetch_update_posts' ] );
	}

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
	 * Fetch Posts by PUT request
	 */
	public static function fetch_update_posts() {

		$sync_status = get_transient( 'linkboss_sync_ongoing' );

		if ( 'yes' === $sync_status ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error!',
					'msg' => esc_html__( 'Sync Ongoing and that\'s why Post Update Blocked.', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		$api_url = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$arg = array(
			'headers' => $headers,
			'method' => 'GET',
		);

		$response = wp_remote_get( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
		$res_body = isset( $res_body['posts'] ) ? $res_body['posts'] : array();

		$status = wp_remote_retrieve_response_code( $response );

		if ( 401 === $status ) {
			return Auth::get_tokens_by_auth_code();
		}

		if ( 200 !== $status ) {
			echo wp_json_encode(
				array(
					'status' => 'error',
					'title' => 'Error! ' . $status,
					'msg' => esc_html__( 'Response Error!', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		if ( empty( $res_body ) ) {

			echo wp_json_encode(
				array(
					'status' => 'success',
					'title' => 'Success!',
					'msg' => esc_html__( 'No data to Update', 'semantic-linkboss' ),
				)
			);
			wp_die();
		}

		self::update_posts( $res_body );
	}

	public static function thrive_extract_content_before_more( $content, $post_id ) {
		// Pattern to match everything before the "More Tag"
		$pattern = '/(.*?)(<p class="tve_more_tag" id="more-' . $post_id . '">.*?<\/p>)/s';

		// Check if there's a "More Tag" in the content
		if ( preg_match( $pattern, $content, $matches ) ) {
			// Return content before "More Tag"
			return $matches[1]; // Content before "More Tag"
		}

		// If no "More Tag" is found, return the full content
		return $content;
	}

	/**
	 * Update Thrive data in WordPress.
	 *
	 * @param int $post_id The ID of the post to update.
	 * @param array $data The data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function updateThriveData( $post_id, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$content_key = 'tve_updated_post';
		$thrive_template = get_post_meta( $post_id, 'tve_landing_page', true );

		// see if this is a thrive templated page
		if ( ! empty( $thrive_template ) ) {
			// get the template key
			$content_key = 'tve_updated_post_' . $thrive_template;
			$before_more_key = 'tve_content_before_more_' . $thrive_template;
		} else {
			$content_key = 'tve_updated_post';
			$before_more_key = 'tve_content_before_more';
		}

		update_post_meta( $post_id, $content_key, $data );

		// Ensure $data['content'] is set and is a string
		$content = isset( $data['content'] ) && is_string( $data['content'] ) ? $data['content'] : $data;

		// Extract content before "More Tag" and update tve_content_before_more
		// $before_more_content = self::thrive_extract_content_before_more( $content, $post_id );
		// update_post_meta( $post_id, $before_more_key, $before_more_content );

		return true;
	}

	/**
	 * Server Response to Update Posts
	 *
	 * @return void
	 */
	public static function update_posts( $data ) {

		/**
		 * Store the post IDs that were updated successfully
		 * id: "post_id"
		 */
		$updated_posts = array();

		// Assuming $data is the array containing post data
		foreach ( $data as $post_data ) {
			// Prepare the post data for updating
			$post_id = $post_data['_postId'];
			$post_content = isset( $post_data['content'] ) && ! empty( $post_data['content'] ) ? $post_data['content'] : '';
			$post_modified = $post_data['updatedAt'];

			$timestamp = strtotime( $post_modified );
			$date = gmdate( 'Y-m-d H:i:s', $timestamp );

			/**
			 * Update the Elementor data First
			 * 
			 * @since 2.2.0
			 * Solved by @sabbir
			 */
			if ( isset( $post_data['builder'] ) && 'elementor' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				// Use wp_json_encode to properly encode the JSON data
				$encoded_meta = addslashes( wp_json_encode( $post_data['meta'] ) );
				// Update the post meta with properly encoded JSON
				update_post_meta( $post_id, '_elementor_data', $encoded_meta );
			}

			/**
			 * Update the Bricks
			 * 
			 * @since 2.5.0
			 */
			if ( isset( $post_data['builder'] ) && 'bricks' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				update_post_meta( $post_id, '_bricks_page_content_2', wp_slash( $post_data['meta'] ) );
			}

			/**
			 * Update the Oxygen
			 * 
			 * @since 2.5.0
			 */
			if ( isset( $post_data['builder'] ) && 'oxygen' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				/**
				 * Check if data is array & Old Oxygen version
				 */
				$meta_exists = get_post_meta( $post_id, '_ct_builder_json', true );
				$meta_value = is_array( $post_data['meta'] ) ? wp_slash( wp_json_encode( $post_data['meta'] ) ) : $post_data['meta'];
				$meta_key = $meta_exists ? '_ct_builder_json' : 'ct_builder_json';

				update_post_meta( $post_id, $meta_key, $meta_value );
			}

			/**
			 * Update the Thrive Content Builder
			 * 
			 * @since 2.5.0
			 */
			if ( isset( $post_data['builder'] ) && 'thrive' === $post_data['builder'] && isset( $post_data['meta'] ) ) {
				self::updateThriveData( $post_id, $post_content );
			}

			/**
			 * Update the post
			 */
			if ( ! empty( $post_content ) ) {
				global $wpdb;
				// phpcs:ignore
				$post_updated = $wpdb->update(
					$wpdb->posts,
					array(
						'post_content' => $post_content,
						'post_modified' => $date,
						'post_modified_gmt' => $date,
					),
					array( 'ID' => $post_id )
				);

				/**
				 * Flush object cache for this post
				 */
				if ( $post_updated ) {
					wp_cache_delete( $post_id, 'posts' ); // Clear the object cache for the post
					clean_post_cache( $post_id ); // Additional function to clear all caches related to the post
				}

			}

			/**
			 * Get the post title
			 */
			$post_title = get_the_title( $post_id );
			$post_title = mb_strimwidth( $post_title, 0, 100, '...' );

			/**
			 * Check if the post was updated successfully
			 */
			// if ( ! $post_updated ) {
			if ( is_wp_error( $post_updated ) ) {
				/**
				 * Handle any errors if needed
				 * Need to trigger socket fire event
				 * request @server
				 * $msg = 'Failed to update post ' . $post_id;
				 */
				echo wp_json_encode(
					array(
						'status' => 'error',
						'title' => 'Failed to update!',
						'msg' => esc_html( $post_title ),
					)
				);

			} else {
				/*
				 * Post updated successfully
				 */
				/**
				 * Update for new sync batch
				 */

				/**
				 * Store the post ID in the array
				 */
				array_push( $updated_posts, array( 'post_id' => $post_id ) );

				/**
				 * You can add further actions if needed
				 * $post_id
				 */
				echo wp_json_encode(
					array(
						'status' => 'success',
						'title' => 'Post updated.',
						'msg' => esc_html( $post_title ),
					)
				);

				/**
				 * Need to trigger socket fire event = working
				 * request @server
				 */

				/**
				 * Send updated Post IDs to LinkBoss
				 */
				if ( ! empty( $updated_posts ) ) {
					self::send_updated_posts_ids( $updated_posts );
				}
			}
		}
		wp_die();
	}

	/**
	 * Send updated Post IDs to LinkBoss
	 * PATCH /api/plugin/sync : BODY - { posts: [{id: "post_id"}, {id: "post_id"}] }
	 */
	public static function send_updated_posts_ids( $updated_posts ) {
		$api_url = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts' => $updated_posts,
		);

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body, true ),
			'method' => 'PATCH',
		);

		$res = wp_remote_request( $api_url, $arg );

		$res_body = json_decode( wp_remote_retrieve_body( $res ) );
		$res_code = wp_remote_retrieve_response_code( $res );
	}
}

if ( class_exists( 'SEMANTIC_LB\Classes\Update_Posts' ) ) {
	\SEMANTIC_LB\Classes\Update_Posts::get_instance();
}
