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
	public static $sync_speed = 10;
	public static $force_data = false;

	/**
	   * Tracks post IDs processed during a single request to prevent duplicate syncs.
	   * Moved here from Cron class in v2.7.1 to handle multiple trigger points.
	   *
	   * @var array
	   * @since 2.7.1
	   */
	private static $processed_posts_in_request = array();

	/**
	 * Construct
	 *
	 * @since 0.0.0
	 */
	public function __construct() {
		self::$sync_speed = get_option( 'linkboss_sync_speed', 10 );

		//woocom
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'edited_term', array( $this, 'on_term_edit' ), 10, 3 );
		}
	}

	/**
	   * Handle the term edit process for specified taxonomies.
	   *
	   * @param int $term_id The term ID.
	   * @param int $tt_id The term taxonomy ID.
	   * @param string $taxonomy The taxonomy slug.
	   */
	public function on_term_edit( $term_id, $tt_id, $taxonomy ) {
		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		if ( ! $woo_enabled ) {
			return;
		}

		// List the taxonomies you want to capture
		$allowed_taxonomies = array( 'category', 'product_cat' );

		if ( ! in_array( $taxonomy, $allowed_taxonomies ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return;
		}

		// Prepare term data in the specified format.
		$term_data = array(
			'_postId'    => $term->term_id,
			'category'   => wp_json_encode( array( $term->term_id ) ),
			'title'      => $term->name,
			'content'    => $term->description,
			'postType'   => 'Category Archive',
			'postStatus' => 'publish',
			'createdAt'  => current_time( 'mysql' ),
			'updatedAt'  => current_time( 'mysql' ),
			'url'        => get_term_link( $term ),
			'builder'    => 'classic',
			'meta'       => null,
		);

		// Send the term data.
		self::send_group( array( $term_data ), null, false );
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
	public static function sync_posts_by_cron_and_hook( $post_id = null ) {
		// Guard: Check if this post ID has already been processed in this request.
		// Use the passed $post_id if available, otherwise, this might be a general batch sync.
		// Note: This guard primarily works for single post updates triggered by hooks.
		if ( $post_id && isset( self::$processed_posts_in_request[ $post_id ] ) ) {
			return; // Already processed, exit.
		}

		// Mark this post ID as processed for this request if applicable.
		if ( $post_id ) {
			self::$processed_posts_in_request[ $post_id ] = true;
		}

		self::$show_msg = false;
		$posts          = new self();
		// $batches        = $posts->ready_batch_for_process();

		// If a specific post_id is given, we only need the batch containing that ID.
		// Otherwise, process all batches (original behavior).
		$batches = $post_id ? $posts->get_batch_for_post( $post_id ) : $posts->ready_batch_for_process();

		// Ensure batches is always an array, even if get_batch_for_post returns null or false
		if ( ! is_array( $batches ) ) {
			$batches = array();
		}

		$posts->send_batch_of_posts( $batches );
	}

	/**
	   * Get the specific batch containing a given post ID.
	   *
	   * @param int $post_id The post ID to find.
	   * @return array|null The batch array containing the post ID, or null if not found.
	   * @since 2.7.1
	   */
	public function get_batch_for_post( $post_id ) {
		$all_batches = $this->ready_batch_for_process();
		foreach ( $all_batches as $batch ) {
			if ( in_array( $post_id, $batch ) ) {
				return array( $batch ); // Return as an array containing the single batch
			}
		}
		return null; // Post ID not found in any batch needing sync
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
		// Check if we're syncing by URLs
		$linkboss_qb = get_option('linkboss_custom_query', '');
		if (isset($linkboss_qb['sync_by']) && 'urls' === $linkboss_qb['sync_by'] && !empty($linkboss_qb['url_list'])) {
			return $this->create_batches_from_urls($linkboss_qb['url_list']);
		}
		
		/**
		 * Set the maximum number of posts per batch (e.g., 5 or 10).
		 */
		$max_posts_per_batch = self::$sync_speed; // Change this value as needed.

		/**
		 * Initialize an array to store batches of post_id values.
		 */
		$batches = array();

		/**
		 * Initialize variables to keep track of the current batch.
		 */
		$current_batch = array();

		/**
		 * Query the database for post_id and content_size ordered by content_size.
		 */
		global $wpdb;

		if ( true === self::$force_data ) {
			// phpcs:ignore
			$wpdb->query( "UPDATE {$wpdb->prefix}linkboss_sync_batch SET sent_status = NULL WHERE sent_status = 1" );
		}

		// Improved query with proper parentheses for the WHERE clause
		// phpcs:ignore
		$query = "SELECT * FROM {$wpdb->prefix}linkboss_sync_batch WHERE (sent_status IS NULL AND (post_status = 'publish' OR post_status = 'trash')) ORDER BY post_id ASC";
		
		// phpcs:ignore
		$results = $wpdb->get_results( $query );

		/**
		 * If there are no results, return an empty array.
		 */
		if ( ! $results ) {
			return array();
		}

		foreach ( $results as $row ) {
			$data_id = $row->post_id;
			$post_type = isset($row->post_type) ? $row->post_type : 'unknown';

			/**
			 * If adding this row to the current batch exceeds the post limit, start a new batch.
			 */
			if ( count( $current_batch ) >= $max_posts_per_batch ) {
				$batches[]     = $current_batch;
				$current_batch = array();
			}

			/**
			 * Add the post_id to the current batch.
			 */
			$current_batch[] = $data_id;
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
	 * Enhanced function to convert URL to post ID with support for custom post types
	 *
	 * @param string $url The URL to convert
	 * @return int The post ID or 0 if not found
	 * @since 2.7.6
	 */
	public function enhanced_url_to_postid($url) {
		// First try the native WordPress function
		$post_id = url_to_postid($url);
		
		// If the native function worked, return the result
		if ($post_id > 0) {
			$post = get_post($post_id);
			return $post_id;
		}
		
		// If the native function failed, try our enhanced approach
		// Parse the URL to get the path
		$url_parts = parse_url($url);
		if (!isset($url_parts['path'])) {
			return 0;
		}
		
		// Get the path and remove trailing slash
		$path = untrailingslashit($url_parts['path']);
		
		// Try to find the post by path
		global $wpdb;
		
		// Get all registered post types
		$post_types = get_post_types(['public' => true], 'names');
		$post_types_str = "'" . implode("','", $post_types) . "'";
		
		// Query for posts with matching path
		$sql = $wpdb->prepare(
			"SELECT ID, post_type FROM $wpdb->posts 
			WHERE post_status = 'publish' 
			AND post_type IN ($post_types_str) 
			AND (
				%s LIKE CONCAT('%%/', post_name) 
				OR %s LIKE CONCAT('%%/', post_name, '/') 
				OR guid = %s
			)
			ORDER BY post_type = 'post' DESC, post_type = 'page' DESC, ID DESC 
			LIMIT 1",
			$path, $path, $url
		);
		
		$result = $wpdb->get_row($sql);
		
		if ($result) {
			return (int)$result->ID;
		} else {
			
			// Try one more approach - check if this might be a custom post type archive or taxonomy
			// This is a simplified approach and might need to be expanded based on your site structure
			foreach ($post_types as $post_type) {
				$post_type_obj = get_post_type_object($post_type);
				if ($post_type_obj && !empty($post_type_obj->rewrite['slug'])) {
					$post_type_slug = $post_type_obj->rewrite['slug'];
					if (strpos($path, '/' . $post_type_slug . '/') !== false) {
						// This might be a custom post type URL
						// Extract the slug after the post type
						$slug_parts = explode('/' . $post_type_slug . '/', $path);
						if (isset($slug_parts[1])) {
							$post_slug = trim($slug_parts[1], '/');
							
							// Look up the post by slug and type
							$post = get_page_by_path($post_slug, OBJECT, $post_type);
							if ($post) {
								return $post->ID;
							}
						}
					}
				}
			}
			
			return 0;
		}
	}

	/**
	 * Create batches from a list of URLs
	 *
	 * @param string $url_list Newline-separated list of URLs
	 * @return array Array of batches containing post IDs
	 * @since 2.7.5
	 */
	public function create_batches_from_urls($url_list) {
		if (empty($url_list)) {
			return array();
		}
		
		$max_posts_per_batch = self::$sync_speed;
		$urls = explode("\n", $url_list);
		$urls = array_map('trim', $urls);
		$urls = array_filter($urls);
		
		$post_ids = array();
		$batches = array();
		$current_batch = array();
		
		// Get post IDs from URLs
		foreach ($urls as $url) {
			// Use our enhanced function instead of url_to_postid
			$post_id = $this->enhanced_url_to_postid($url);
			
			if ($post_id > 0) {
				$post_ids[] = $post_id;
				
				// Add to batch
				if (count($current_batch) >= $max_posts_per_batch) {
					$batches[] = $current_batch;
					$current_batch = array();
				}
				
				$current_batch[] = $post_id;
				
				// Make sure the post is in the sync_batch table
				$this->ensure_post_in_sync_batch($post_id);
				
			}
		}
		
		// Add any remaining posts to the last batch
		if (!empty($current_batch)) {
			$batches[] = $current_batch;
		}
		
		return $batches;
	}
	
	/**
	 * Ensure a post is in the sync_batch table
	 *
	 * @param int $post_id The post ID to add to the sync batch
	 * @since 2.7.5
	 */
	private function ensure_post_in_sync_batch($post_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';
		
		// Check if post already exists in the table
		// phpcs:ignore
		$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE post_id = %d", $post_id));
		
		if (!$exists) {
			$post = get_post($post_id);
			if ($post) {
				// Get post content size
				$content_size = mb_strlen($post->post_content, '8bit');
				
				// Insert the post into the sync_batch table
				// phpcs:ignore
				$result = $wpdb->insert(
					$table_name,
					array(
						'post_id' => $post_id,
						'post_type' => $post->post_type, // Add post_type to the insert
						'post_status' => $post->post_status,
						'sent_status' => null,
						'content_size' => $content_size, // Add content_size to the insert
					),
					array('%d', '%s', '%s', '%s', '%d')
				);
			}
		} else if (true === self::$force_data) {
			// If force data is true, reset the sent_status
			// phpcs:ignore
			$wpdb->update(
				$table_name,
				array('sent_status' => null),
				array('post_id' => $post_id),
				array('%s'),
				array('%d')
			);
		}
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
	   * Check if the ACF field is of a type we want to process.
	   *
	   * @param string $field_type The type of the field.
	   * @return bool True if it's a processable field, false otherwise.
	   */
	private function is_processable_acf_field( $field_type ) {
		$processable_types = array( 'wysiwyg' );
		return in_array( $field_type, $processable_types, true );
	}

	/**
	 * Generate HTML from the meta structure in the expected format.
	 *
	 * @param array $meta The meta array containing original_post_content and acf_builder.
	 * @return string The generated HTML string.
	 */
	private function generate_html_from_meta( $meta ) {
		$html = '<div class="acf-classic-builder-content">';

		// Add Classic post content only if present
		if ( ! empty( $meta['original_post_content'] ) ) {
			$html .= '<div class="classic-post-content">';
			$html .= $meta['original_post_content'];
			$html .= '</div>';
		}

		// Add ACF builder items
		if ( ! empty( $meta['acf_builder'] ) ) {
			$html .= '<div class="acf-builder-items">';
			foreach ( $meta['acf_builder'] as $item ) {
				$html .= sprintf(
					'<div class="acf-builder-item" data-type="%s" data-custom-field-name="%s">',
					esc_attr( $item['type'] ),
					esc_attr( $item['custom_field_name'] )
				);

				$content = maybe_unserialize( $item['custom_field_content'] );

				if ( $item['type'] === 'wysiwyg' ) {
					$html .= wp_kses_post( $content );
				} else {
					$tag     = 'p';
					$content = esc_html( $content );
					$html .= sprintf(
						'<%s class="custom-field-content">%s</%s>',
						$tag,
						$content,
						$tag
					);
				}

				$html .= '</div>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
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
				$result = self::send_group( $prepared_posts, $batch, false );
			}

		endforeach;
	}

	/**
	 * Prepared Posts for Sync
	 *
	 * @since 2.7.0
	 */
	public function prepared_posts_for_sync( $posts ) {
		// Check if ACF support is enabled
		$acf_enabled = get_option( 'linkboss_acf_enabled', false );

		$prepared_posts = array_map(
			function ( $post ) use ( &$batch, $acf_enabled ) {

				$builder_type   = null;
				$elementor_data = null;
				$post_type = $post->post_type;
				$acf_fields_present = false; // Initialize variable
				$meta = null;

				$acf_json_structure = array();
				$original_post_content = $post->post_content;

				if ( defined( 'SEMANTIC_LB_CLASSIC_EDITOR' ) ) {
						$builder_type = 'classic';
				}

				if ( defined( 'SEMANTIC_LB_ELEMENTOR' ) ) {
					// Check 1: _elementor_edit_mode meta key must exist.
					// get_post_meta without a third 'true' argument returns an array of values.
					// If the key exists, the array won't be empty.
					$elementor_edit_mode_values = get_post_meta( $post->ID, '_elementor_edit_mode' );

					if ( ! empty( $elementor_edit_mode_values ) ) { // True if _elementor_edit_mode key exists
						// Check 2: _elementor_data meta key must exist and contain actual, non-empty JSON data.
						$elementor_data_values = get_post_meta( $post->ID, '_elementor_data' );

						if ( ! empty( $elementor_data_values ) && isset( $elementor_data_values[0] ) ) {
							$elementor_data_json = $elementor_data_values[0]; // This is the JSON string

							// Ensure the JSON string is not empty and not just an empty array '[]'
							if ( is_string($elementor_data_json) && ! empty( trim( $elementor_data_json ) ) && $elementor_data_json !== '[]' ) {
								// Further validation: try to decode and check if it's a non-empty array
								$decoded_data = json_decode( $elementor_data_json, true );
								if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
									// All checks passed, this is likely a genuine Elementor post
									$builder_type = 'elementor';
									$meta         = $elementor_data_json; // Store the raw JSON string as meta

									// Original Elementor content processing logic starts here
									$rendered_content = \Elementor\Plugin::$instance->frontend->get_builder_content( $post->ID, false );

									$rendered_content = preg_replace(
										array(
											'/<style\b[^>]*>(.*?)<\/style>/is',
											'/<div class="elementor-post__card">.*?<\/div>/is',
											'/<div\s+[^>]*class="[^"]*\belementor-widget-posts\b[^"]*"[^>]*>(?:.|\s)*?<\/div>\s*<\/div>\s*<\/div>/is',
											'/<div\s[^>]*class="[^"]*\belementor-shortcode\b[^"]*"[^>]*>.*?<\/div>/is',
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
											'"',
											'"',
										),
										array(
											'–',
											'—',
											'\'',
											'\'',
											'"',
											'"',
											'−',
											'…',
											'"',
											'$',
											'\'',
											'"',
											'"',
										),
										$rendered_content
									);

									// Ensure span style attributes end with a semicolon.
									$rendered_content = preg_replace_callback(
										'/(<span\s+style=")([^"]*)(")/i',
										function( $matches ) {
											$style_content = $matches[2];
											if ( ! empty( $style_content ) && substr( rtrim( $style_content ), -1 ) !== ';' ) {
												return $matches[1] . $style_content . ';' . $matches[3];
											} else {
												return $matches[0];
											}
										},
										$rendered_content
									);
									// $meta was already set to $elementor_data_json
								}
							}
						}
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

				// Only check for ACF fields if ACF support is enabled and we're using Classic Editor, Elementor, or Gutenberg (no builder detected)
				if ( $acf_enabled && function_exists( 'get_fields' ) && get_fields( $post->ID ) ) {
					$acf_fields_present = true;
					$acf_fields = get_fields( $post->ID );

					foreach ( $acf_fields as $key => $value ) {
						$field_info = get_field_object( $key, $post->ID );
						if ( $this->is_processable_acf_field( $field_info['type'] ) ) {
							$acf_json_structure[] = array(
								'custom_field_name' => $field_info['name'],
								'type'              => $field_info['type'],
								'custom_field_content' => maybe_serialize( $value ),
							);
						}
					}

					// Only apply ACF processing for supported builders (Classic, Elementor) or no builder (Gutenberg)
					if ( $builder_type === 'classic' ) {
						// Handle Classic Editor with ACF
						$meta = array(
							'original_post_content' => $original_post_content,
							'acf_builder'           => $acf_json_structure,
						);
						$rendered_content = $this->generate_html_from_meta( $meta );
						$meta = null;
						$post_type = 'acf-classic';
					} elseif ( $builder_type === 'elementor' ) {
						// Handle Elementor with ACF
						$post_type = 'acf-elementor';
						$meta = array_merge(
							array_map( function ( $item ) {
								return array(
									'id'               => 'acf' . substr( md5( $item['custom_field_name'] ), 0, 5 ),
									'type'             => $item['type'],
									'elType'           => 'widget',
									'elements'         => array(),
									'settings'         => array( 'editor' => $item['custom_field_content'] ),
									'widgetType'       => 'text-editor',
									'custom_field_name' => $item['custom_field_name'],
								);
							}, $acf_json_structure ),
							( is_string( $elementor_data_json ) ? json_decode( $elementor_data_json, true ) : json_decode( $elementor_data_json, true ) ) ?: array()
						);
						$acf_content = $this->generate_html_from_meta( array(
							'original_post_content' => '',
							'acf_builder'           => $acf_json_structure,
						) );
						$rendered_content .= $acf_content;
                        $rendered_content = str_replace('&#039;', '\'', $rendered_content);
					} elseif ( ! $builder_type && $acf_fields_present ) { // Check if ACF fields were actually processed
						// Handle Gutenberg with ACF (no builder detected)
						$builder_type = 'classic'; // Treat Gutenberg with ACF as Classic
						$post_type = 'acf-classic';
						$meta = array(
							'original_post_content' => $original_post_content,
							'acf_builder'           => $acf_json_structure,
						);
						$rendered_content = $this->generate_html_from_meta( $meta );
						$meta = null;
					}
				} elseif ( ! $builder_type ) {
					// If no builder detected and no ACF fields, treat as Gutenberg
					$builder_type = 'gutenberg';
				}

				switch ( $builder_type ) {
					case 'elementor':
						// Check if this is ACF-Elementor combination
						if ( $post_type === 'acf-elementor' && ! empty( $acf_json_structure ) ) {
							// Keep the merged meta structure for ACF-Elementor
							// $meta already contains the merged ACF+Elementor data from above
						} else {
							// Regular Elementor without ACF
							$meta = $elementor_data_json;
						}
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
				
				// Get categories based on post type
				$category_ids = array();
				
				if ( $post->post_type === 'product' ) {
					// Get WooCommerce product categories
					$product_categories = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'ids' ) );
					$category_ids = is_wp_error( $product_categories ) ? array() : $product_categories;
				} elseif ( $post->post_type === 'post' ) {
					// Use regular post categories
					$category_ids = is_array( $post->post_category ) ? $post->post_category : array();
				} else {
					// For other post types, try to get categories from 'category' taxonomy
					$post_categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
					$category_ids = is_wp_error( $post_categories ) ? array() : $post_categories;
				}

				return array(
					'_postId'    => $post->ID,
					'category'   => wp_json_encode( $category_ids ),
					'title'      => $post->post_title,
					'content'    => isset( $rendered_content ) ? $rendered_content : $post->post_content,
					'postType'   => $post_type,
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

	/**
	   * Resets the processed posts tracker array.
	   * Should be called via the 'shutdown' action.
	   *
	   * @since 2.7.1
	   */
	public static function reset_processed_posts() {
		self::$processed_posts_in_request = array();
	}
}