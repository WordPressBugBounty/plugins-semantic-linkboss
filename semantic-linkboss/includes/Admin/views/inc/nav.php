<?php

if( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_valid = get_option( 'linkboss_api_key', false );
$reports_data = $api_valid ? get_option( 'linkboss_reports', false) : false;

?>
<nav class="bg-gray-800 rounded-lg">
	<div class="mx-auto px-2 sm:px-6 lg:px-8">
		<div class="relative flex h-16 items-center justify-between">
			<div class="absolute inset-y-0 left-0 flex items-center sm:hidden">
			</div>
			<div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-start">
				<div class="flex flex-shrink-0 items-center">
					<img class="h-8 w-auto" src="<?php echo esc_url( SEMANTIC_LB_ASSETS_URL ); ?>/imgs/logo.png"
						alt="Your Company">
				</div>
			</div>
			<div class="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">
				<span id="linkboss-socket-status" class="flex items-center text-sm font-medium text-white dark:text-white me-3">
				</span>
				<div class="text-gray-300 px-3 py-2 text-sm font-medium bg-orange-700 rounded-md" title="Your Package">
					<?php
					$package = isset( $reports_data['account']['package'] ) ? esc_html( $reports_data['account']['package'] ) : '';
					echo wp_kses_post( $package );
					?>
					<i>(Credits -
						<?php
						$balance = isset( $reports_data['account']['balance'] ) ? esc_html( $reports_data['account']['balance'] ) : 0;
						$used = isset( $reports_data['account']['used'] ) ? esc_html( $reports_data['account']['used'] ) : 0;
						echo wp_kses_post( $balance - $used );
						?>
						)
					</i>
				</div>
			</div>
</nav>
