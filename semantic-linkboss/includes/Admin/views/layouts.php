<?php
$api_key_validate = get_option( 'linkboss_api_key', false );
?>
<div class="wrap">
	<div class="linkboss linkboss-wrapper">
		<div class="flex flex-col min-h-screen">
			<?php
			require_once SEMANTIC_LB_INC_PATH . 'Admin/views/inc/nav.php';
			?>
			<?php if ( ! $api_key_validate ) : ?>
				<div
					class="flex items-center p-4 mt-10 text-sm text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800"
					role="alert">
					<svg class="flex-shrink-0 inline w-4 h-4 mr-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
						fill="currentColor" viewBox="0 0 20 20">
						<path
							d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z" />
					</svg>
					<span class="sr-only">
						<?php esc_html_e( 'Error!', 'semantic-linkboss' ); ?>
					</span>
					<div>
						<span class="font-medium">
							<?php esc_html_e( 'Ops!', 'semantic-linkboss' ); ?>
						</span>
						<?php esc_html_e( 'Your API is not connected.', 'semantic-linkboss' ); ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="">
				<?php
				require_once SEMANTIC_LB_INC_PATH . 'Admin/views/inc/report.php';
				?>
			</div>
			<?php
			require_once SEMANTIC_LB_INC_PATH . 'Admin/views/inc/footer.php';
			?>
		</div>
	</div>
</div>


<?php
$lb_nonce = wp_create_nonce( 'lb-nonce' );

if ( isset( $_GET['page'] ) && isset( $_GET['lb-debug'] ) && wp_verify_nonce( $lb_nonce, 'lb-nonce' ) ) :
	?>
	<div class="">
		<button class="button button-primary" id="lb-check">Send Data</button>
		<button class="button button-primary" id="lb-update">Update Posts</button>
	</div>
<?php endif; ?>

<script>
	// ajax request
	jQuery(document).ready(function ($) {
		$('#lb-check').click(function () {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'lb_send_posts',
				},
				success: function (response) {
					console.log(response);
				}
			});
		});
		$('#lb-update').click(function () {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'lb_fetch_update_posts',
				},
				success: function (response) {
					console.log(response);
				}
			});
		});
	});
</script>
