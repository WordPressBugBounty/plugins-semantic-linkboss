<?php
/**
 * Render Handler
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Traits\Global_Functions;

/**
 * Description of Render
 *
 * @since 0.0.0
 */
class Render {

	use Global_Functions;

	/**
	 * Construct
	 */
	public function __construct() {
		add_action( 'wp_ajax_linkbatch_batch_reports', [ $this, 'sync_batch_init_reports' ] );
		add_action( 'wp_ajax_linkboss_sync_by_id', [ $this, 'linkboss_sync_by_id' ] );
	}

	/**
	 * Sync by ID
	 *
	 * @since 2.0.3
	 */
	public function linkboss_sync_by_id() {
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		$update = $wpdb->update(
			$table_name,
			array( 'sent_status' => null ),
			array( 'post_id' => $post_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $update ) {
			echo wp_json_encode( array(
				'status' => 'success',
				'post_id' => $post_id,
				'message' => 'Post ID - ' . $post_id . ' marked for sync.',
			) );
		} else {
			echo wp_json_encode( array(
				'status' => 'error',
				'message' => 'Post ID - ' . $post_id . ' not found.',
			) );
		}
		wp_die();
	}

	public function _time_diff( $from, $to = '' ) {
		$diff = human_time_diff( $from, $to );
		$replace = array(
			' hour' => 'h',
			' hours' => 'h',
			' day' => 'd',
			' days' => 'd',
			' minute' => 'm',
			' minutes' => 'm',
			' second' => 's',
			' seconds' => 's',
		);

		return strtr( $diff, $replace );
	}

	public function linkboss_time_diff( $data_time, $format = '' ) {
		$displayAgo = esc_html__( 'ago', 'semantic-linkboss' );

		if ( $format == 'short' ) {
			$output = $this->_time_diff( strtotime( $data_time ), current_time( 'timestamp' ) );
		} else {
			$output = human_time_diff( strtotime( $data_time ), current_time( 'timestamp' ) );
		}

		$output = $output . ' ' . $displayAgo;

		return $output;
	}

	/**
	 * Render the latest updated posts
	 *
	 * @since 0.0.0
	 */
	public static function latest_updated_posts( $post_data ) {

		if ( ! $post_data ) {
			return;
		}

		foreach ( $post_data as $post ) {
			if ( empty( $post ) ) {
				return;
			}
			$post_title = $post['title'];
			$post_link = $post['url'];
			$post_update_date = $post['updatedAt'];

			$render_instance = new self();
			$converted_time = $render_instance->linkboss_time_diff( $post_update_date );

			?>
			<tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
				<th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
					<?php echo esc_html( $post_title ); ?>
					<div class="text-gray-500">
						<a target="_blank" href="<?php echo esc_url( $post_link ); ?>"
							title="Post ID - <?php echo esc_attr( $post['_postId'] ); ?>">
							<?php echo esc_url( $post_link ); ?>
						</a>
					</div>
				</th>
				<td class="px-6 py-4">
					<?php echo esc_html( $converted_time ); ?>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Posts Logs from Sync Batch Table
	 * 
	 * @since 2.5.0
	 */
	public static function posts_logs() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		// phpcs:ignore
		$sql = $wpdb->prepare(
			"SELECT l.*, p.post_title, p.post_type, p.post_status, p.post_date, p.post_modified
             FROM {$table_name} l
             INNER JOIN {$wpdb->prefix}posts p ON l.post_id = p.ID
             ORDER BY l.created_at DESC
             LIMIT %d",
			200
		);

		$logs = $wpdb->get_results( $sql, ARRAY_A );

		return $logs;
	}

	// Function to get hierarchical taxonomies for a post type
	public function get_hierarchical_taxonomies( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$hierarchical_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->hierarchical ) {
				$hierarchical_taxonomies[] = $taxonomy->name;
			}
		}

		return $hierarchical_taxonomies;
	}

	/**
	 * Total Categories
	 * 
	 * @since 2.3.0
	 */
	public function report_total_categories() {
		$query_data = get_option( 'linkboss_custom_query', '' );
		$post_type = isset( $query_data['post_sources'] ) && ! empty( $query_data['post_sources'] ) ? $query_data['post_sources'] : array( 'post' );

		// Remove 'page' from the array
		if ( in_array( 'page', $post_type ) ) {
			$key = array_search( 'page', $post_type );
			unset( $post_type[ $key ] );
		}

		// Flatten post type array if only one type is present
		if ( count( $post_type ) === 1 ) {
			$post_type = array_shift( $post_type );
		}

		// Collect all categories
		$all_categories = array();

		if ( is_array( $post_type ) ) {
			// Multiple post types
			foreach ( $post_type as $type ) {
				$taxonomies = $this->get_hierarchical_taxonomies( $type );
				foreach ( $taxonomies as $taxonomy ) {
					$categories = get_terms( array(
						'taxonomy' => $taxonomy,
						'parent' => 0,
						'hide_empty' => false,
					) );

					if ( ! is_wp_error( $categories ) ) {
						$all_categories = array_merge( $all_categories, $categories );
					}
				}
			}
		} else {
			// Single post type
			$taxonomies = $this->get_hierarchical_taxonomies( $post_type );
			foreach ( $taxonomies as $taxonomy ) {
				$categories = get_terms( array(
					'taxonomy' => $taxonomy,
					'parent' => 0,
					'hide_empty' => false,
				) );

				if ( ! is_wp_error( $categories ) ) {
					$all_categories = array_merge( $all_categories, $categories );
				}
			}
		}

		// Remove duplicate categories
		$all_categories = array_unique( $all_categories, SORT_REGULAR );
		// Count categories
		$total_cat = count( $all_categories );

		$cat = isset( $query_data['_categories'] ) ? count( $query_data['_categories'] ) : '';

		if ( $cat ) {
			$total_cat = $cat;
		}

		return $total_cat;
	}

	/**
	 * Sync Batch Init Reports
	 *
	 * @since 2.0.7
	 */
	public static function sync_batch_init_reports() {

		$reports = array();

		/**
		 * Get WordPress total page count
		 */
		$obj = new self();
		$reports['pages'] = $obj->report_pages_count();
		$reports['posts'] = $obj->report_posts_count();

		/**
		 * Get total sync batch count from wp_linkboss_sync_batch table
		 */
		global $wpdb;
		$table_name = $wpdb->prefix . 'linkboss_sync_batch';

		// phpcs:ignore
		$total_batch_query = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status IS NULL OR sent_status = '1' OR sent_status = 'F'";
		// phpcs:ignore
		$reports['total_batch'] = $wpdb->get_var( $total_batch_query );

		// phpcs:ignore
		$sql_sync = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status IS NULL";
		// phpcs:ignore
		$reports['sync_batch'] = $wpdb->get_var( $sql_sync );

		// phpcs:ignore
		$sql_sync_done = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status = '1'";
		// phpcs:ignore
		$reports['sync_done'] = $wpdb->get_var( $sql_sync_done );

		// phpcs:ignore
		$sql_need_sync = "SELECT COUNT(*) FROM {$table_name} WHERE sent_status IS NULL";
		// phpcs:ignore
		$reports['require_sync'] = $wpdb->get_var( $sql_need_sync );

		// Content size
		// phpcs:ignore
		$sql_content_size = "SELECT SUM(content_size) FROM {$table_name} WHERE sent_status = '1' OR sent_status IS NULL";
		// phpcs:ignore
		$content_size = $wpdb->get_var( $sql_content_size );
		$reports['content_size'] = self::bytes_to_size( $content_size );

		/**
		 * Elementor data
		 */
		// phpcs:ignore
		$sql_elementor_data = "SELECT COUNT(*)
                FROM {$wpdb->prefix}postmeta pm
                LEFT JOIN {$wpdb->prefix}linkboss_sync_batch l ON pm.meta_id = l.post_id
                WHERE pm.meta_key = '_elementor_data'";
		// phpcs:ignore
		$reports['elementor_data'] = (int) $wpdb->get_var( $sql_elementor_data );

		$reports['total_categories'] = $obj->report_total_categories();

		return $reports;
	}

	/**
	 * Bytes to Size
	 */
	public static function bytes_to_size( $bytes, $precision = 2 ) {
		$kilobyte = 1024;
		$megabyte = $kilobyte * 1024;
		$gigabyte = $megabyte * 1024;
		$terabyte = $gigabyte * 1024;

		if ( ( $bytes >= 0 ) && ( $bytes < $kilobyte ) ) {
			return $bytes . ' B';
		} elseif ( ( $bytes >= $kilobyte ) && ( $bytes < $megabyte ) ) {
			return round( $bytes / $kilobyte, $precision ) . ' KB';
		} elseif ( ( $bytes >= $megabyte ) && ( $bytes < $gigabyte ) ) {
			return round( $bytes / $megabyte, $precision ) . ' MB';
		} elseif ( ( $bytes >= $gigabyte ) && ( $bytes < $terabyte ) ) {
			return round( $bytes / $gigabyte, $precision ) . ' GB';
		} elseif ( $bytes >= $terabyte ) {
			return round( $bytes / $terabyte, $precision ) . ' TB';
		} else {
			return $bytes . ' B';
		}
	}

	/**
	 * Function to get all post types
	 */
	public static function get_group_control_query_post_types() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$post_types = array_column( $post_types, 'label', 'name' );

		// also count the number of posts in each post type and add it to the label
		foreach ( $post_types as $post_type => $label ) {
			$count_posts = wp_count_posts( $post_type );
			$post_types[ $post_type ] = $label . ' (' . $count_posts->publish . ')';
		}

		$ignorePostTypes = [ 
			'elementor_library' => '',
			'attachment' => '',
		];

		$post_types = array_diff_key( $post_types, $ignorePostTypes );

		return $post_types;
	}

}
