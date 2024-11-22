<?php
/**
 * Ajax Sync Class
 *
 * @package SEMANTIC_LB
 * @since 2.0.3
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;
use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Ajax Sync Class
 *
 * @since 2.0.3
 */
class Ajax_Sync {

	use Global_Functions;

	public static $force_data = false;
	public static $sync_speed = 512;
	/**
	 * Class constructor
	 *
	 * @since 2.0.3
	 */
	public function __construct() {
		add_action( 'wp_ajax_linkboss_get_batch_process', array( $this, 'get_batch_process' ) );
		add_action( 'wp_ajax_linkboss_prepare_batch_data', array( $this, 'prepare_batch_for_sync' ) );
		add_action( 'wp_ajax_linkboss_category_sync_process', array( $this, 'ready_wp_categories_for_sync' ) );
	}

	/**
	 * Create Batches for Sync
	 *
	 * @return array
	 */
	public function ready_batch_for_process() {
		/**
		 * Set the maximum size threshold in bytes (512KB).
		 */
		$max_size_threshold = 1024 * self::$sync_speed;

		/**
		 * Initialize an array to store batches of post_id values.
		 */
		$batches = array();

		/**
		 * Initialize variables to keep track of the current batch.
		 */
		$current_batch = array();
		$current_batch_size = 0;

		/**
		 * Query the database for post_id and content_size ordered by content_size.
		 */
		global $wpdb;

		if ( true === self::$force_data ) {
			// phpcs:ignore
			$wpdb->query( "UPDATE {$wpdb->prefix}linkboss_sync_batch SET sent_status = NULL WHERE sent_status = 1" );
		}

		// phpcs:ignore
		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}linkboss_sync_batch WHERE sent_status IS NULL ORDER BY post_id ASC" );
		// phpcs:ignore
		$results = $wpdb->get_results( $query );

		if ( ! $results ) {
			return [];
		}

		foreach ( $results as $row ) {
			$data_id = $row->post_id;
			$content_size = $row->content_size;

			/**
			 * If adding this row to the current batch exceeds the threshold, start a new batch.
			 */
			if ( $current_batch_size + $content_size > $max_size_threshold ) {
				$batches[] = $current_batch;
				$current_batch = array();
				$current_batch_size = 0;
			}

			/**
			 * Add the post_id to the current batch.
			 */
			$current_batch[] = $data_id;
			$current_batch_size += $content_size;
		}

		/**
		 * Add any remaining data to the last batch.
		 */
		if ( ! empty( $current_batch ) ) {
			$batches[] = $current_batch;
		}

		return $batches;
	}

	/**
	 * Call Batch Process for Sync
	 */
	public function get_batch_process() {

		self::$sync_speed = get_option( 'linkboss_sync_speed', 512 );

		if ( isset( $_POST['forceData'] ) && ( 'yes' == sanitize_text_field( wp_unslash( $_POST['forceData'] ) ) ) ) {
			self::$force_data = true;
		}

		$batches = $this->ready_batch_for_process();

		$response = array(
			'status' => 'success',
			'batches' => $batches,
		);

		update_option( 'linkboss_sync_batch', $batches );

		echo wp_json_encode( $response, true );
		wp_die();
	}

	public function prepare_batch_for_sync() {

		/**
		 * Data Idea
		 * $batches = [ [ 1, 2, 3, 4 ], [ 5, 6, 7, 8 ], [ 9, 10, 11, 12 ] ];
		 * $batches = [ [ 5, 6, 7, 8 ], [ 9, 10, 11, 12 ] ];
		 * $batches    = [ [ 9, 10, 11, 12 ] ];
		 */
		$batches = get_option( 'linkboss_sync_batch', [] );

		$sent_batch = isset( $batches[0] ) ? $batches[0] : [];
		$next_batch = array_slice( $batches, 1 );

		$force_data = isset( $_POST['forceData'] ) ? sanitize_text_field( wp_unslash( $_POST['forceData'] ) ) : '';

		if ( isset( $force_data ) && 'yes' === $force_data ) {
			self::$force_data = true;
		}

		$this->ready_wp_posts_for_sync( $sent_batch );

		$response = array(
			'status' => 'success',
			'sent_batch' => $sent_batch,
			'next_batches' => count( $next_batch ) > 0 ? $next_batch : false,
			'batch_length' => count( $next_batch ),
			'has_batch' => count( $next_batch ) > 0 ? 'yes' : false,
		);

		update_option( 'linkboss_sync_batch', $next_batch );

		echo wp_json_encode( $response, true );
		wp_die();
	}

	public function containsPostContent( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $this->containsPostContent( $value ) ) {
					return true;
				}
			} elseif ( $value === 'post-content' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Gets the Thrive content for diagnostic purposes.
	 * Not intended for adding links to this content!
	 *
	 * @param $post_id
	 */
	public static function getThriveContent( $post_id, $post_content ) {
		// If thrive's not active, return string
		if ( empty( get_post_meta( $post_id, 'tcb_editor_enabled', true ) ) ) {
			return "";
		}

		$content_key = 'tve_updated_post';
		$thrive_template = get_post_meta( $post_id, 'tve_landing_page', true );

		// see if this is a thrive templated page
		if ( ! empty( $thrive_template ) ) {
			// get the template key
			$content_key = 'tve_updated_post_' . $thrive_template;
		}

		$thrive_content = get_post_meta( $post_id, $content_key, true );

		$rendered_content = ! empty($thrive_content) ? $thrive_content : $post_content;

		return ! ( empty( $rendered_content ) ) ? $rendered_content : "";
	}

	/**
	 * Ready WordPress Posts as JSON
	 * Ready posts by Batch
	 *
	 * @since 2.0.3
	 */
	public function ready_wp_posts_for_sync( $batch ) {
		/**
		 * $batch is an array of post_id
		 * Example: [ 3142, 3141, 3140 ]
		 * $batch = [ 3653, 4025, 4047 ];
		 */

		$posts = $this->get_post_pages( false, $batch );

		$prepared_posts = array_map( function ($post) use (&$batch) {

			$builder_type = null;
			$elementor_data = null;

			if ( defined( 'SEMANTIC_LB_CLASSIC_EDITOR' ) ) {
				$builder_type = 'classic';
			}

			if ( defined( 'SEMANTIC_LB_ELEMENTOR' ) ) {
				$elementor_check = get_post_meta( $post->ID, '_elementor_edit_mode' );

				if ( ! empty( $elementor_check ) && ! count( $elementor_check ) <= 0 ) {
					$builder_type = 'elementor';
					$elementor_data = get_post_meta( $post->ID, '_elementor_data' );

					$rendered_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID, false );
					$rendered_content = str_replace( "&#8217;", "'", $rendered_content );
					$rendered_content = preg_replace( '/<style\b[^>]>(.?)<\/style>/is', "", $rendered_content );
					$rendered_content = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', "", $rendered_content );
					$rendered_content = preg_replace( '/<div class="elementor-post__card">.*?<\/div>/is', "", $rendered_content );

				}
			}

			if ( class_exists( 'ET_Builder_Module' ) ) {
				$is_builder_used = get_post_meta( $post->ID, '_et_pb_use_builder', true ) === 'on';
				if ( $is_builder_used ) {
					$builder_type = 'divi';
				}
			}

			if ( defined( 'BRICKS_VERSION' ) ) {
				// Fetch the _bricks_page_content_2 meta as a single value
				$bricks_meta = get_post_meta( $post->ID, '_bricks_page_content_2', true );

				if ( ! empty( $bricks_meta ) ) {

					// Attempt to unserialize the content to inspect its structure
					$bricks_content = maybe_unserialize( $bricks_meta );

					// Check if the unserialized content contains "post-content"
					if ( is_array( $bricks_content ) && $this->containsPostContent( $bricks_content ) ) {
						$builder_type = 'gutenberg';
					} else {
						$builder_type = 'bricks';
					}
				}

				$rendered_content = ! empty( $post->post_content ) ? $post->post_content : '';
			}


			// if ( defined( 'CT_VERSION' ) || defined( 'SHOW_CT_BUILDER_LB' ) ) {
			// 	$oxygen_meta = get_post_meta( $post->ID, 'ct_builder_json', true );
			// 	$oxygen_meta = get_post_meta( $post->ID, '_ct_builder_json', true );
			// 	if ( ! empty( $oxygen_meta ) ) {
			// 		$builder_type = 'oxygen';
			// 	}
			// }
			if ( defined( 'CT_VERSION' ) || defined( 'SHOW_CT_BUILDER_LB' ) ) {
				$oxygen_meta = get_post_meta( $post->ID, 'ct_builder_json', true );

				if ( empty( $oxygen_meta ) ) {
					$oxygen_meta = get_post_meta( $post->ID, '_ct_builder_json', true );
				}

				if ( ! empty( $oxygen_meta ) ) {
					$builder_type = 'oxygen';
				}
			}

			if( defined( 'TVE_IN_ARCHITECT' ) ) {
				$thrive_content = self::getThriveContent( $post->ID, $post->post_content );
				if ( ! empty( $thrive_content ) ) {
					$builder_type = 'thrive';
					$rendered_content = $thrive_content;
				}
			}

			switch ( $builder_type ) {
				case 'elementor':
					$meta = $elementor_data[0];
					break;
				case 'bricks':
					$meta = isset( $bricks_meta ) ? $bricks_meta : null;
					break;
				case 'oxygen':
					$meta = isset( $oxygen_meta ) ? $oxygen_meta : null;
					/**
					 * We are skiped the serialized data
					 * 
					 * sabbir 2.5.0
					 */
					if ( is_serialized( $oxygen_meta ) ) {
						$meta = null;
						/**
						 * Exclude post if oxygen_meta is serialized
						 */
						/**
						 * Update the batch status to I=Ignore
						 */
						global $wpdb;
						// phpcs:ignore
						$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}linkboss_sync_batch SET sent_status = 'F' WHERE post_id = %d", $post->ID );
						// phpcs:ignore
						$wpdb->query( $query );
						/**
						 * Remove the post ID from the batch
						 */
						$batch = array_diff( $batch, [ $post->ID ] );


						return null;
					}
					break;
				
				case 'thrive':
					$meta = null;
					break;
				
				default:
					$meta = null;
			}

			return array(
				'_postId' => $post->ID,
				'category' => wp_json_encode( $post->post_category ),
				'title' => $post->post_title,
				'content' => isset( $rendered_content ) ? $rendered_content : $post->post_content,
				'postType' => $post->post_type,
				'postStatus' => $post->post_status,
				'createdAt' => $post->post_date,
				'updatedAt' => $post->post_modified,
				'url' => get_permalink( $post->ID ),
				'builder' => ( null !== $builder_type ) ? $builder_type : 'gutenberg',
				'meta' => $meta,
			);
		}, $posts );

		/**
		 * Remove null values and reindex the array
		 * Because of Oxygen Builder
		 */
		$prepared_posts = array_values( array_filter( $prepared_posts ) );

		if ( count( $prepared_posts ) <= 0 ) {
			return array(
				'status' => 200,
				'title' => 'Success',
				'msg' => esc_html__( 'Posts are up to date.', 'semantic-linkboss' ),
			);
		}

		self::send_group( $prepared_posts, $batch, false );

	}

	/**
	 * Send Categories as JSON on last batch
	 *
	 * @since 2.0.3
	 */
	public function ready_wp_categories_for_sync() {

		$categories = get_categories( array(
			'orderby' => 'name',
			'order' => 'ASC',
		) );

		$categories_data = array_map( function ($category) {
			return array(
				'categoryId' => $category->term_id,
				'name' => $category->name,
				'slug' => $category->slug,
			);
		}, $categories );

		$response = $this->send_group( $categories_data, '', true );

		if ( 200 === $response['status'] ) {
			echo wp_json_encode( array(
				'status' => 'success',
			), true );
		} else {
			echo wp_json_encode( array(
				'status' => 'error',
			), true );
		}
		wp_die();
	}

	/**
	 * Send WordPress Posts as JSON
	 *
	 * @since 2.0.3
	 */
	public static function send_group( $data, $batch, $category = false ) {
		$api_url = ! $category ? SEMANTIC_LB_POSTS_SYNC_URL : SEMANTIC_LB_OPTIONS_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'Authorization' => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts' => ! $category ? $data : array(),
		);

		if ( $category ) {
			$body = array(
				'categories' => $data,
			);
		}

		if ( true === self::$force_data ) {
			$body['force'] = true;
		}

		$arg = array(
			'headers' => $headers,
			'body' => wp_json_encode( $body, 256 ),
			'method' => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		$res_msg = isset( $res_body->message ) ? $res_body->message : esc_html__( 'Something went wrong!', 'semantic-linkboss' );

		$res_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $res_code ) {
			return Auth::get_tokens_by_auth_code();
		}

		if ( 200 !== $res_code && 201 !== $res_code ) {
			return array(
				'status' => $res_code,
				'title' => 'Error!',
				'msg' => esc_html( $res_msg . '. Error Code-' . $res_code ),
			);
		}

		/**
		 * Batch update
		 * Send Batch when the request response is 200 || 201
		 */
		if ( ( 200 === $res_code || 201 === $res_code ) && ! empty( $batch ) ) {
			self::batch_update( $batch );
		}

		return array(
			'status' => 200,
			'title' => 'Success!',
			'msg' => esc_html( $res_msg ),
		);
	}

	public static function batch_update( $batch_ids ) {
		global $wpdb;

		$post_ids_list = implode( ',', $batch_ids );
		$current_time = current_time( 'mysql' );

		// phpcs:ignore
		$query = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}linkboss_sync_batch 
     SET sent_status = 1, sync_at = %s 
     WHERE post_id IN ({$post_ids_list})",
			$current_time
		);
		// phpcs:ignore
		$wpdb->query( $query );
	}

}
