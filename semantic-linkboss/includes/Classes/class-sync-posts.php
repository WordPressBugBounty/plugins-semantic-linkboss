<?php
/**
 * Sync Posts Handler
 *
 * @package LinkBoss
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Auth;
use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Description of Sync Posts
 *
 * @since 0.0.0
 */
class Sync_Posts {

	use Global_Functions;

	public static $show_msg   = false;
	public static $sync_speed = 512;
	public static $force_data = false;
	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		self::$sync_speed = get_option( 'linkboss_sync_speed', 512 );
	}

	public static function cron_ready_batch_for_process() {
		self::$show_msg = false;
		$posts          = new self();
		$posts->ready_batch_for_process();
	}

	/**
	 * Sync Posts by Cron & Hook
	 * Special Cron Job for Sync Posts to remove message
	 *
	 * @since 0.1.0
	 */
	public static function sync_posts_by_cron_and_hook() {
		self::$show_msg = false;
		$posts          = new self();
		$batches        = $posts->ready_batch_for_process();
		$posts->send_batch_of_posts( $batches );
	}

	public function send_batch_of_posts( $batches ) {

		/**
		 * Validate Access Token
		 */
		$valid_tokens = self::valid_tokens();

		if ( true === $valid_tokens ) {
			$this->send_posts_app( $batches );
		} elseif ( true === self::$show_msg ) {
				echo wp_json_encode(
					array(
						'status' => 'error',
						'title'  => 'Error!',
						'msg'    => esc_html__( 'Please Try Again.', 'semantic-linkboss' ),
					)
				);
				wp_die();
		}
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
		$current_batch      = array();
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
		// $query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}linkboss_sync_batch WHERE sent_status IS NULL && post_status = 'publish' OR post_status = 'trash' ORDER BY post_id ASC" );
    // phpcs:ignore
    $query = "SELECT * FROM {$wpdb->prefix}linkboss_sync_batch WHERE sent_status IS NULL && post_status = 'publish' OR post_status = 'trash' ORDER BY post_id ASC";
		// phpcs:ignore
		$results = $wpdb->get_results( $query );

		/**
		 * If there are no results, return an empty array.
		 */
		if ( ! $results ) {
			return array();
		}

		foreach ( $results as $row ) {
			$data_id      = $row->post_id;
			$content_size = $row->content_size;

			/**
			 * If adding this row to the current batch exceeds the threshold, start a new batch.
			 */
			if ( $current_batch_size + $content_size > $max_size_threshold ) {
				$batches[]          = $current_batch;
				$current_batch      = array();
				$current_batch_size = 0;
			}

			/**
			 * Add the post_id to the current batch.
			 */
			$current_batch[]     = $data_id;
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

	public function contains_post_content( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $this->contains_post_content( $value ) ) {
					return true;
				}
			} elseif ( 'post-content' === $value ) {
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
	public static function get_thrive_content( $post_id, $post_content ) {
		// If thrive's not active, return string
		if ( empty( get_post_meta( $post_id, 'tcb_editor_enabled', true ) ) ) {
			return '';
		}

		$content_key     = 'tve_updated_post';
		$thrive_template = get_post_meta( $post_id, 'tve_landing_page', true );

		// see if this is a thrive templated page
		if ( ! empty( $thrive_template ) ) {
			// get the template key
			$content_key = 'tve_updated_post_' . $thrive_template;
		}

		$thrive_content = get_post_meta( $post_id, $content_key, true );

		$rendered_content = ! empty( $thrive_content ) ? $thrive_content : $post_content;

		return ! ( empty( $rendered_content ) ) ? $rendered_content : '';
	}

	/**
	 * Send WordPress Posts as JSON
	 *
	 * @since 0.0.0
	 */
	public static function send_posts_app( $batches ) {

		foreach ( $batches as $batch ) :

			/**
			 * $batch is an array of post_id
			 * Example: [ 3142, 3141, 3140 ]
			 */
			$obj   = new self();
			$posts = $obj->get_post_pages( false, $batch, -1, array( 'publish', 'trash' ) );

			$prepared_posts = $obj->prepared_posts_for_sync( $posts );

			/**
			 * Remove null values and reindex the array
			 * Because of Oxygen Builder
			 */
			$prepared_posts = array_values( array_filter( $prepared_posts ) );

			/**
			 * If there are posts to send
			 */
			if ( count( $prepared_posts ) > 0 ) {
				self::send_group( $prepared_posts, $batch, false );
			}

		endforeach;
	}

	/**
	 * Prepared Posts for Sync
	 *
	 * @since 2.7.0
	 */
	public function prepared_posts_for_sync( $posts ) {
		$prepared_posts = array_map(
			function ( $post ) use ( &$batch ) {

				$builder_type   = null;
				$elementor_data = null;

				if ( defined( 'SEMANTIC_LB_CLASSIC_EDITOR' ) ) {
						$builder_type = 'classic';
				}

				if ( defined( 'SEMANTIC_LB_ELEMENTOR' ) ) {
					$elementor_check = get_post_meta( $post->ID, '_elementor_edit_mode' );

					if ( ! empty( $elementor_check ) && ! count( $elementor_check ) <= 0 ) {
						$builder_type   = 'elementor';
						$elementor_data = get_post_meta( $post->ID, '_elementor_data' );

						$rendered_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $post->ID, false );

						$rendered_content = preg_replace(
							array(
								'/<style\b[^>]*>(.*?)<\/style>/is',
								'/<div class="elementor-post__card">.*?<\/div>/is',
							),
							'',
							$rendered_content
						);

						$rendered_content = str_replace(
							array(
								'&#8211;',
								'&#8212;',
								'&#8216;',
								'&#8217;',
								'&#8220;',
								'&#8221;',
								'&#8722;',
								'&#8230;',
								'&#34;',
								'&#36;',
								'&#39;',
								'“',
								'”',
							),
							array(
								'–',
								'—',
								'‘',
								"'",
								'“',
								'”',
								'−',
								'…',
								'"',
								'$',
								"'",
								'"',
								'"',
							),
							$rendered_content
						);

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
						if ( is_array( $bricks_content ) && $this->contains_post_content( $bricks_content ) ) {
							$builder_type = 'gutenberg';
						} else {
							$builder_type = 'bricks';
						}
					}

					$rendered_content = ! empty( $post->post_content ) ? $post->post_content : '';
				}

				// if ( defined( 'CT_VERSION' ) || defined( 'SHOW_CT_BUILDER_LB' ) ) {
				// $oxygen_meta = get_post_meta( $post->ID, 'ct_builder_json', true );
				// $oxygen_meta = get_post_meta( $post->ID, '_ct_builder_json', true );
				// if ( ! empty( $oxygen_meta ) ) {
				// $builder_type = 'oxygen';
				// }
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

				if ( defined( 'TVE_IN_ARCHITECT' ) ) {
					$thrive_content = self::get_thrive_content( $post->ID, $post->post_content );
					if ( ! empty( $thrive_content ) ) {
						$builder_type     = 'thrive';
						$rendered_content = $thrive_content;
					}
				}

				/**
				 * Beaver Builder
				 */
				if ( class_exists( 'FLBuilderModel' ) && get_post_meta( $post->ID, '_fl_builder_enabled', true ) == '1' ) {
					$builder_type = 'beaver';
					$beaver_meta  = get_post_meta( $post->ID, '_fl_builder_data', true );
					$meta         = maybe_unserialize( $beaver_meta );

					if ( ! $meta ) {
						$meta = $beaver_meta;
					}

					$rendered_content = ! empty( $post->post_content ) ? $post->post_content : '';
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
							$batch = array_diff( $batch, array( $post->ID ) );

							return null;
						}
						break;

					case 'thrive':
						$meta = null;
						break;

					case 'beaver':
						$meta = $beaver_meta;
						break;

					default:
						$meta = null;
				}

				/**
			* Handle WPML & Polylang Permalinks
			*/
				$post_url = get_permalink( $post->ID );

				if ( function_exists( 'icl_object_id' ) ) {
					// WPML: Get language code and correct permalink
					$language_code = apply_filters( 'wpml_post_language_details', null, $post->ID )['language_code'];
					$post_url      = apply_filters( 'wpml_permalink', get_permalink( $post->ID ), $language_code );
				} elseif ( function_exists( 'pll_get_post' ) ) {
					// Polylang: Get the translated post ID and its permalink
					$lang               = pll_get_post_language( $post->ID );
					$translated_post_id = pll_get_post( $post->ID, $lang );

					if ( $translated_post_id ) {
						$post_url = get_permalink( $translated_post_id );
					}
				}

				return array(
					'_postId'    => $post->ID,
					'category'   => wp_json_encode( $post->post_category ),
					'title'      => $post->post_title,
					'content'    => isset( $rendered_content ) ? $rendered_content : $post->post_content,
					'postType'   => $post->post_type,
					'postStatus' => $post->post_status,
					'createdAt'  => $post->post_date,
					'updatedAt'  => $post->post_modified,
					'url'        => $post_url,
					'builder'    => ( null !== $builder_type ) ? $builder_type : 'gutenberg',
					'meta'       => $meta,
				);
			},
			$posts
		);

		return $prepared_posts;
	}

	/**
	 * Send WordPress Posts as JSON
	 *
	 * @since 0.0.0
	 */
	public static function send_group( $data, $batch, $category = false ) {
		$api_url      = ! $category ? SEMANTIC_LB_POSTS_SYNC_URL : SEMANTIC_LB_OPTIONS_URL;
		$access_token = Auth::get_access_token();

		if ( ! $access_token ) {
			return Auth::get_tokens_by_auth_code();
		}

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
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
			// 'body'    => wp_json_encode( $body, true ),
			'body'    => wp_json_encode( $body, 256 ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_body = json_decode( wp_remote_retrieve_body( $response ) );

		$res_msg = isset( $res_body->message ) ? $res_body->message : esc_html__( 'Something went wrong!', 'semantic-linkboss' );

		$res_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $res_code ) {
			return Auth::get_tokens_by_auth_code();
		}

		if ( 200 !== $res_code && 201 !== $res_code ) {
			if ( true === self::$show_msg ) {
				/**
				 * Need to update the batch status to F=Failed
				 */
				// self::batch_update( $batch, 'F' );
				return array(
					'status' => $res_code,
					'title'  => 'Error!',
					'msg'    => esc_html( $res_msg . '. Error Code-' . $res_code ),
				);
			}
		}

		/**
		 * Batch update
		 * Send Batch when the request response is 200 || 201
		 */
		if ( ( 200 === $res_code || 201 === $res_code ) && ! empty( $batch ) ) {
			self::batch_update( $batch );
		}

		if ( true === self::$show_msg ) {
			return array(
				'status' => 200,
				'title'  => 'Success!',
				'msg'    => esc_html( $res_msg ),
			);
		}
	}

	public static function batch_update( $batch_ids, $status = 1 ) {
		global $wpdb;

		$post_ids_list = implode( ',', $batch_ids );
		// todo: need to fixed - check before update / insert on batch table

		/**
		 * Get the current date and time in MySQL datetime format
		 */
		$current_time = current_time( 'mysql' );

		/**
		 * SQL query to update the sent_status and sync_at in the custom table
		 */
		if ( 1 === $status ) {
			// phpcs:ignore
			$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}linkboss_sync_batch SET sent_status = 1, sync_at = %s WHERE post_id IN ({$post_ids_list})", $current_time );
		} else {
			// phpcs:ignore
			$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}linkboss_sync_batch SET sent_status = 'F', sync_at = %s WHERE post_id IN ({$post_ids_list})", $current_time );
		}
		// phpcs:ignore
		
		// phpcs:ignore
		$wpdb->query( $query );
	}

	/**
	 * Validate Access & Refresh Token on First Request
	 * If not valid then get new tokens programmatically
	 */
	public static function valid_tokens() {
		$api_url      = SEMANTIC_LB_POSTS_SYNC_URL;
		$access_token = Auth::get_access_token();

		$headers = array(
			'Content-Type'     => 'application/json',
			'Authorization'    => "Bearer $access_token",
			'X-PLUGIN-VERSION' => SEMANTIC_LB_VERSION,
		);

		$body = array(
			'posts' => array(),
		);

		$arg = array(
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'method'  => 'POST',
		);

		$response = wp_remote_post( $api_url, $arg );
		$res_code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $res_code ) {
			return Auth::get_tokens_by_auth_code();
		}

		return true;
	}
}
