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

		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		// Only hook into term edit if WooCommerce category sync is enabled
		if ( $woo_enabled ) {
			$this->hook_into_term_edit();
		}

		// Removed hook for scheduled Elementor sync v2.7.1
		// add_action( 'linkboss_schedule_elementor_sync', array( $this, 'handle_scheduled_elementor_sync' ), 10, 1 );
	}

	/**
	 * Hook into term edit to capture changes and sync for specified taxonomies.
	 */
	public function hook_into_term_edit() {
		add_action( 'edited_term', array( $this, 'on_term_edit' ), 10, 3 );
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
			error_log( 'Failed to retrieve term: ' . $term->get_error_message() );
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
		// Accept post_id if passed
		// Guard: Check if this post ID has already been processed in this request.
		// Use the passed $post_id if available, otherwise, this might be a general batch sync.
		// Note: This guard primarily works for single post updates triggered by hooks.
		if ( $post_id && isset( self::$processed_posts_in_request[ $post_id ] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LinkBoss Sync (Sync_Posts): Guard prevented duplicate sync for post ID: ' . $post_id );
			return; // Already processed, exit.
		}

		// Mark this post ID as processed for this request if applicable.
		if ( $post_id ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LinkBoss Sync (Sync_Posts): Proceeding with sync for post ID: ' . $post_id );
			self::$processed_posts_in_request[ $post_id ] = true;
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'LinkBoss Sync (Sync_Posts): Proceeding with general batch sync (no specific post ID).' );
		}

		self::$show_msg = false;
		$posts          = new self();
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
		error_log( 'TRIGERRED: ready_batch_for_process' );
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
			$data_id = $row->post_id;

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
	 * Get posts and pages based on criteria
	 *
	 * @param int|bool $post_id Single post ID to retrieve
	 * @param array $post_ids Array of post IDs to retrieve
	 * @param int $posts_per_page Number of posts to retrieve
	 * @param array $post_status Post statuses to include
	 * @return array Array of post objects
	 */
	public function get_post_pages( $post_id = false, $post_ids = array(), $posts_per_page = 10, $post_status = array( 'publish' ) ) {
		global $wpdb;

		$linkboss_qb = get_option( 'linkboss_custom_query', '' );

		$post_sources = isset( $linkboss_qb['post_sources'] ) ? $linkboss_qb['post_sources'] : array();
		$categories   = isset( $linkboss_qb['categories'] ) ? $linkboss_qb['categories'] : array();
		$__categories = isset( $linkboss_qb['__categories'] ) ? $linkboss_qb['__categories'] : array();

		// Check if WooCommerce category sync is enabled
		$woo_enabled = get_option( 'linkboss_woo_enabled', false );

		// If WooCommerce is enabled and 'product' is selected in post types, ensure it's included
		if ( $woo_enabled && ! empty( $post_sources ) ) {
			// Check if 'product' is already in post_sources
			if ( ! in_array( 'product', $post_sources ) ) {
				// Check if we have any product categories specifically selected
				$has_product_categories = false;

				if ( ! empty( $__categories ) ) {
					foreach ( $__categories as $tax_query ) {
						if ( isset( $tax_query['taxonomy'] ) && $tax_query['taxonomy'] === 'product_cat' ) {
							$has_product_categories = true;
							break;
						}
					}
				}

				// Include 'product' if:
				// 1. Specific product categories are selected, OR
				// 2. No categories are selected at all, OR
				// 3. Only non-product categories are selected (we still want to include all products)
				$post_sources[] = 'product';
			}
		}

		// Prepare the base query arguments
		$args = array(
			'post_type'      => ! empty( $post_sources ) ? $post_sources : array( 'post', 'page' ),
			'post_status'    => $post_status,
			'posts_per_page' => $posts_per_page,
		);

		// Check if we need to handle WooCommerce products specially
		$has_product = in_array( 'product', $args['post_type'] );
		$has_product_categories = false;

		// Check if any product categories are selected
		if ( ! empty( $__categories ) ) {
			foreach ( $__categories as $tax_query ) {
				if ( isset( $tax_query['taxonomy'] ) && $tax_query['taxonomy'] === 'product_cat' ) {
					$has_product_categories = true;
					break;
				}
			}
		}

		// Special handling for WooCommerce products when categories are selected
		if ( $woo_enabled && $has_product && ! empty( $__categories ) ) {
			// Check if we need special handling for products
			$needs_special_handling = true;

			// If there are product categories selected, we don't need special handling
			if ( $has_product_categories ) {
				$needs_special_handling = false;
			}

			if ( $needs_special_handling ) {
				// We need to run two separate queries and merge the results
				// First, get posts based on the selected categories
				$post_args = $args;
				$post_args['post_type'] = array_diff( $args['post_type'], array( 'product' ) );
				$post_args['tax_query'] = $__categories;
				$post_query = new \WP_Query( $post_args );

				// Then, get all products
				$product_args = $args;
				$product_args['post_type'] = array( 'product' );
				// No tax_query for products to get all of them
				$product_query = new \WP_Query( $product_args );

				// Merge the results
				$posts = array_merge( $post_query->posts, $product_query->posts );

				// Return the merged results
				return $posts;
			}
		}

		// Standard query handling
		if ( ! empty( $__categories ) ) {
			$args['tax_query'] = $__categories;
		}

		if ( $post_id ) {
			$args['p'] = $post_id;
		}

		if ( ! empty( $post_ids ) ) {
			$args['post__in'] = $post_ids;
		}

		$query = new \WP_Query( $args );

		return $query->posts;
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

	public function prepared_posts_for_sync( $posts ) {
		// Check if ACF support is enabled
		$acf_enabled = get_option( 'linkboss_acf_enabled', false );

		$prepared_posts = array_map(
			function ( $post ) use ( &$batch, $acf_enabled ) {

				$builder_type   = null;
				$elementor_data = null;
				$acf_json_structure = array();
				$original_post_content = $post->post_content;
				$post_type = $post->post_type;
				$acf_fields_present = false; // Initialize variable
				$meta = null;

				// First, check for all builder types
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
								// $matches[0] is the full match, e.g., <span style="color: red">
								// $matches[1] is '<span style="'
								// $matches[2] is 'color: red'
								// $matches[3] is '"'

								$style_content = $matches[2];
								// Check if the content, ignoring trailing whitespace, ends with ;
								if ( ! empty( $style_content ) && substr( rtrim( $style_content ), -1 ) !== ';' ) {
									// If not, add the semicolon
									return $matches[1] . $style_content . ';' . $matches[3];
								} else {
									// Otherwise, return the original match
									return $matches[0];
								}
							},
							$rendered_content
						);

						$meta = $elementor_data[0];
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
							( is_string( $elementor_data[0] ) ? json_decode( $elementor_data[0], true ) : $elementor_data[0] ) ?: array()
						);
						$acf_content = $this->generate_html_from_meta( array(
							'original_post_content' => '',
							'acf_builder'           => $acf_json_structure,
						) );
						$rendered_content .= $acf_content;
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
						if ( ! $acf_fields_present ) {
							$meta = $elementor_data[0];
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

				return array(
					'_postId'    => $post->ID,
					'category'   => wp_json_encode( $post->post_category ),
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
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		// Ensure batch_ids is an array and not empty
		if ( ! is_array( $batch_ids ) || empty( $batch_ids ) ) {
			error_log( 'LinkBoss Sync (batch_update): Received empty or invalid batch_ids.' );
			return;
		}

		// Sanitize IDs to ensure they are integers
		$sanitized_ids = array_map( 'intval', $batch_ids );
		$ids_placeholder = implode( ',', array_fill( 0, count( $sanitized_ids ), '%d' ) );

		// Get the current date and time in MySQL datetime format
		$current_time = current_time( 'mysql' );

		// Handle failed status first
		if ( 1 !== $status ) {
			$failed_ids_list = implode( ',', $sanitized_ids );
			error_log( 'LinkBoss Sync (batch_update): Marking posts as Failed (F): ' . $failed_ids_list );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$query = $wpdb->prepare( "UPDATE {$table_name} SET sent_status = 'F', sync_at = %s WHERE post_id IN ({$ids_placeholder})", array_merge( array( $current_time ), $sanitized_ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $query );
			return; // Exit after handling failure
		}

		// Handle successful status (status = 1)
		// Find out which posts in the batch are 'trash' and which are 'publish' (or other non-trash statuses)
		$trashed_ids   = array();
		$published_ids = array();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, post_status FROM {$table_name} WHERE post_id IN ({$ids_placeholder})", $sanitized_ids ), ARRAY_A );

		if ( $results ) {
			foreach ( $results as $row ) {
				if ( 'trash' === $row['post_status'] ) {
					$trashed_ids[] = (int) $row['post_id'];
				} else {
					$published_ids[] = (int) $row['post_id'];
				}
			}
		} else {
			error_log( 'LinkBoss Sync (batch_update): Could not retrieve post statuses for batch IDs: ' . implode( ',', $sanitized_ids ) );
			// Decide if we should proceed or return. For now, let's assume they were published if not found.
			$published_ids = $sanitized_ids;
		}

		// Process published posts (Update status to 1)
		if ( ! empty( $published_ids ) ) {
			$published_ids_list = implode( ',', $published_ids );
			$published_ids_placeholder = implode( ',', array_fill( 0, count( $published_ids ), '%d' ) );
			error_log( 'LinkBoss Sync (batch_update): Marking posts as Synced (1): ' . $published_ids_list );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$update_query = $wpdb->prepare( "UPDATE {$table_name} SET sent_status = 1, sync_at = %s WHERE post_id IN ({$published_ids_placeholder})", array_merge( array( $current_time ), $published_ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $update_query );
		}

		// Process trashed posts (Delete from batch table)
		if ( ! empty( $trashed_ids ) ) {
			$trashed_ids_list = implode( ',', $trashed_ids );
			$trashed_ids_placeholder = implode( ',', array_fill( 0, count( $trashed_ids ), '%d' ) );
			error_log( 'LinkBoss Sync (batch_update): Deleting successfully synced trashed posts from batch table: ' . $trashed_ids_list );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$delete_query = $wpdb->prepare( "DELETE FROM {$table_name} WHERE post_id IN ({$trashed_ids_placeholder})", $trashed_ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $delete_query );
		}
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

	// Removed handle_scheduled_elementor_sync method in v2.7.1 as scheduling was reverted.
}
