<?php
/**
 * Sync Exclusions
 * One source of truth for the "Excluded from sync" list
 *
 * The list itself stays in the `linkboss_removed_urls` option (URLs typed
 * by the user). This class resolves it to post IDs, matches URLs loosely
 * (scheme, www, trailing slash, query) and keeps excluded posts in the
 * sync batch table with sent_status = 'X', so no sync path sends them and
 * no report counts them as waiting.
 *
 * @package SEMANTIC_LB
 * @since 2.8.7
 */

namespace SEMANTIC_LB\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sync_Exclusions
 *
 * @since 2.8.7
 */
class Sync_Exclusions {

	/**
	 * Cached resolution of the removed URLs: { ids: int[], urls: string[] }
	 */
	const CACHE_OPTION = 'linkboss_removed_post_ids';

	/**
	 * sent_status value for excluded rows in the sync batch table
	 */
	const STATUS = 'X';

	/**
	 * Per-request cache
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Normalize a URL for comparison
	 *
	 * Lowercase host, no scheme, no "www.", no query/fragment,
	 * no trailing slash.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) ) {
			return untrailingslashit( strtolower( $url ) );
		}

		$host = preg_replace( '/^www\./', '', strtolower( $parts['host'] ) );
		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';

		return $host . $port . $path;
	}

	/**
	 * The resolved exclusion cache, rebuilt on first use
	 *
	 * @return array { ids: int[], urls: string[] }
	 */
	private static function cache() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$cached = get_option( self::CACHE_OPTION, false );

		if ( is_array( $cached ) && isset( $cached['ids'], $cached['urls'] ) ) {
			self::$cache = $cached;
			return self::$cache;
		}

		/**
		 * First use (e.g. right after updating): resolve the list and
		 * tag rows already in the batch table
		 */
		self::rebuild();

		return self::$cache;
	}

	/**
	 * Resolve the removed URLs to post IDs & store the cache
	 *
	 * Call after every change to `linkboss_removed_urls`.
	 *
	 * @param bool $mark_rows Also tag the excluded rows in the batch table.
	 * @return void
	 */
	public static function rebuild( $mark_rows = true ) {
		$urls = get_option( 'linkboss_removed_urls', array() );

		if ( ! is_array( $urls ) ) {
			$urls = array();
		}

		$ids        = array();
		$normalized = array();
		$resolver   = null;

		foreach ( $urls as $url ) {
			$url = trim( (string) $url );

			if ( '' === $url ) {
				continue;
			}

			$normalized[] = self::normalize_url( $url );

			if ( null === $resolver ) {
				$resolver = new Sync_Posts();
			}

			$post_id = (int) $resolver->enhanced_url_to_postid( $url );

			if ( $post_id > 0 ) {
				$ids[] = $post_id;
			}
		}

		self::$cache = array(
			'ids'  => array_values( array_unique( $ids ) ),
			'urls' => array_values( array_unique( array_filter( $normalized ) ) ),
		);

		update_option( self::CACHE_OPTION, self::$cache, false );

		if ( $mark_rows ) {
			self::mark_excluded_rows();
		}
	}

	/**
	 * Excluded post IDs
	 *
	 * @return int[]
	 */
	public static function get_excluded_post_ids() {
		return array_map( 'intval', self::cache()['ids'] );
	}

	/**
	 * Is this URL on the exclusion list (loose match)?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_excluded_url( $url ) {
		$urls = self::cache()['urls'];

		return ! empty( $urls ) && in_array( self::normalize_url( $url ), $urls, true );
	}

	/**
	 * Is this post excluded from sync?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_excluded( $post_id ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return false;
		}

		$cache = self::cache();

		if ( empty( $cache['ids'] ) && empty( $cache['urls'] ) ) {
			return false;
		}

		if ( in_array( $post_id, array_map( 'intval', $cache['ids'] ), true ) ) {
			return true;
		}

		$permalink = get_permalink( $post_id );

		return $permalink ? self::is_excluded_url( $permalink ) : false;
	}

	/**
	 * Tag excluded posts in the sync batch table (sent_status = 'X')
	 *
	 * Rows that already exist are updated (queued ones stop being sent);
	 * missing rows are inserted so the queue preparation doesn't keep
	 * picking the excluded posts up as "new".
	 *
	 * @return void
	 */
	public static function mark_excluded_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'linkboss_sync_batch';

		// phpcs:ignore
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		foreach ( self::get_excluded_post_ids() as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			// phpcs:ignore
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id ) );

			if ( $exists ) {
				// phpcs:ignore
				$wpdb->update( $table, array( 'sent_status' => self::STATUS ), array( 'post_id' => $post_id ), array( '%s' ), array( '%d' ) );
			} else {
				// phpcs:ignore
				$wpdb->insert(
					$table,
					array(
						'post_id'      => $post_id,
						'post_type'    => $post->post_type,
						'post_status'  => $post->post_status,
						'sent_status'  => self::STATUS,
						'content_size' => mb_strlen( $post->post_content, '8bit' ),
					),
					array( '%d', '%s', '%s', '%s', '%d' )
				);
			}
		}
	}
}
