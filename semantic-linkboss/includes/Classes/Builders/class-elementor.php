<?php

/**
 * Elementor Handler
 *
 * @package SEMANTIC_LB
 * @since 2.1.0
 */

namespace SEMANTIC_LB\Classes\Builders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEMANTIC_LB\Classes\Sync_Posts;
use SEMANTIC_LB\Classes\Updates;

class Elementor {

	/**
	 * Class constructor
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		// Changed hook from elementor/editor/after_save to elementor/document/after_save in v2.7.1
		// This hook runs after the document's data is saved to the database.
		add_action( 'elementor/document/after_save', [ $this, 'document_saved' ], 10, 2 );
	}

	/**
	 * Document Saved - Triggered after Elementor document data is saved.
	 * Renamed from editor_saved in v2.7.1 to reflect the new hook.
	 *
	 * @since 2.1.0
	 * @param \Elementor\Core\Base\Document $document    The document instance.
	 * @param array                         $data        The saved data.
	 */
	public function document_saved( $document, $data ) {
		$post_id = $document->get_main_id(); // Get post ID from document

		if ( 'publish' === get_post_status( $post_id ) ) :

			$post_type = get_post_type( $post_id );
			$post_status = get_post_status( $post_id );

			Updates::update_sync_batch_table( $post_id, $post_type, $post_status );

			// Pass post_id for the guard check in Sync_Posts v2.7.1
			// Reverted WP-Cron scheduling attempt. Triggering directly from elementor/document/after_save.
			Sync_Posts::sync_posts_by_cron_and_hook( $post_id );

		endif;
	}
}

if ( class_exists( '\SEMANTIC_LB\Classes\Builders\Elementor' ) ) {
	new \SEMANTIC_LB\Classes\Builders\Elementor();
}
