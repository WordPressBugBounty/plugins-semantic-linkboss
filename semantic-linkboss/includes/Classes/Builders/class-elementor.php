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

use SEMANTIC_LB\Classes\Posts;
use SEMANTIC_LB\Classes\Updates;

class Elementor {

	/**
	 * Class constructor
	 *
	 * @since 2.1.0
	 */
	public function __construct() {
		add_action( 'elementor/editor/after_save', [ $this, 'editor_saved' ], 10, 2 );
	}

	/**
	 * Editor Saved
	 *
	 * @since 2.1.0
	 * @param int   $post_id     The ID of the post.
	 * @param array $editor_data The editor data.
	 */
	public function editor_saved( $post_id, $editor_data ) {

		if ( 'publish' === get_post_status( $post_id ) ) :

			$post_type = get_post_type( $post_id );
			$post_status = get_post_status( $post_id );

			Updates::update_sync_batch_table( $post_id, $post_type, $post_status );

			Posts::sync_posts_by_cron_and_hook();

		endif;
	}

}

if ( class_exists( '\SEMANTIC_LB\Classes\Builders\Elementor' ) ) {
	new \SEMANTIC_LB\Classes\Builders\Elementor();
}
