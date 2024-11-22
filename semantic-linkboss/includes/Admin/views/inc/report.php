<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_valid = get_option( 'linkboss_api_key', false );
$reports_data = get_option( 'linkboss_reports' );

$updates_posts = isset( $reports_data['recents'] ) ? json_decode( wp_json_encode( $reports_data['recents'] ), true ) : null;

$total_links = ( isset( $reports_data['internalLinks'] ) ? $reports_data['internalLinks'] : 0 ) + ( isset( $reports_data['externalLinks'] ) ? $reports_data['externalLinks'] : 0 );

$info_data = array(
	array(
		'name' => 'Total URLS',
		'value' => $total_links,
		'svg' => SEMANTIC_LB_ASSETS_URL . '/imgs/link.svg',
	),
	array(
		'name' => 'ORPHAN PAGES',
		'value' => isset( $reports_data['orphans']['count'] ) ? $reports_data['orphans']['count'] : 0,
		'svg' => SEMANTIC_LB_ASSETS_URL . '/imgs/page-facing-up.svg',
	),
	array(
		'name' => 'INTERNAL LINKS',
		'value' => isset( $reports_data['internalLinks'] ) ? $reports_data['internalLinks'] : 0,
		'svg' => SEMANTIC_LB_ASSETS_URL . '/imgs/link-round-angle.svg',
	),
	array(
		'name' => 'EXTERNAL LINKS',
		'value' => isset( $reports_data['external'] ) ? $reports_data['external'] : 0,
		'svg' => SEMANTIC_LB_ASSETS_URL . '/imgs/link-select.svg',
	),
);

/**
 * Get user display name
 */
$user = wp_get_current_user();
$user_name = $user->display_name;
?>

<div class="grid grid-cols-12 2xl:grid-cols-12 gap-x-5">
	<div
		class="relative card max-w-none col-span-12 overflow-hidden card 2xl:col-span-12 mt-6 px-6 py-9 rounded-lg shadow bg-slate-900 dark:bg-gray-800 dark:border-gray-700">
		<div class="absolute inset-0 blur-sm">
			<img class="h-100 w-100" src="<?php echo esc_url( SEMANTIC_LB_ASSETS_URL ); ?>/imgs/bg-sprinkle.svg"
				alt="LinkBoss">
		</div>
		<div class="relative card-body">
			<div class="grid items-center grid-cols-12">
				<div class="col-span-12 lg:col-span-8 2xl:col-span-7">
					<h5 class="mb-3 mt-0 text-2xl font-normal tracking-wide text-slate-200">Welcome <strong>
							<?php echo esc_html( $user_name ); ?>
						</strong> 🎉</h5>
					<p class="mb-6 font-normal text-lg text-slate-400 dark:text-gray-400">
						Build Semantically Relevant Contextual Interlinks at Scale! Save countless work hours. Improve your site's
						SEO
						and user experience.
					</p>
					<a href="https://linkboss.io/" target="_blank"
						class="inline-flex items-center px-5 py-3 text-sm font-medium text-center text-white bg-blue-700 rounded-lg hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
						Read more
						<svg class="rtl:rotate-180 w-3.5 h-3.5 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
							fill="none" viewBox="0 0 14 10">
							<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M1 5h12m0 0L9 1m4 4L9 9" />
						</svg>
					</a>
				</div>
				<div class="hidden col-span-12 2xl:col-span-3 lg:col-span-2 lg:col-start-11 2xl:col-start-10 lg:block">
					<img src="<?php echo esc_url( SEMANTIC_LB_ASSETS_URL ); ?>/imgs/dashboard.png" alt=""
						class="h-40 ml-auto rtl:2xl:mr-auto">
				</div>
			</div>
		</div>
	</div>
	<!-- <div class="col-span-12 2xl:col-span-3 mt-6">
		?
	</div> -->
</div>

<div class="mt-6">
	<button id="lb-sync-report-manually" type="button"
		class="text-white bg-[#3b5998] hover:bg-[#3b5998]/90 focus:ring-4 focus:outline-none focus:ring-[#3b5998]/50 font-medium rounded-lg text-sm py-4 px-5 text-center inline-flex items-center dark:focus:ring-[#3b5998]/55 mr-2 mb-2 gap-1">
		<svg class="w-4 h-4 mr-2" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
			<path
				d="M105.1 202.6c7.7-21.8 20.2-42.3 37.8-59.8c62.5-62.5 163.8-62.5 226.3 0L386.3 160H336c-17.7 0-32 14.3-32 32s14.3 32 32 32H463.5c0 0 0 0 0 0h.4c17.7 0 32-14.3 32-32V64c0-17.7-14.3-32-32-32s-32 14.3-32 32v51.2L414.4 97.6c-87.5-87.5-229.3-87.5-316.8 0C73.2 122 55.6 150.7 44.8 181.4c-5.9 16.7 2.9 34.9 19.5 40.8s34.9-2.9 40.8-19.5zM39 289.3c-5 1.5-9.8 4.2-13.7 8.2c-4 4-6.7 8.8-8.1 14c-.3 1.2-.6 2.5-.8 3.8c-.3 1.7-.4 3.4-.4 5.1V448c0 17.7 14.3 32 32 32s32-14.3 32-32V396.9l17.6 17.5 0 0c87.5 87.4 229.3 87.4 316.7 0c24.4-24.4 42.1-53.1 52.9-83.7c5.9-16.7-2.9-34.9-19.5-40.8s-34.9 2.9-40.8 19.5c-7.7 21.8-20.2 42.3-37.8 59.8c-62.5 62.5-163.8 62.5-226.3 0l-.1-.1L125.6 352H176c17.7 0 32-14.3 32-32s-14.3-32-32-32H48.4c-1.6 0-3.2 .1-4.8 .3s-3.1 .5-4.6 1z" />
		</svg>
		<span class="lb-text">
			<?php esc_html_e( 'Sync Data', 'semantic-linkboss' ); ?>
		</span>
		</span>
		<span class="lb-progress-perc"></span>
	</button>
</div>
<div class="lb-reposts-one-grid mt-6">

	<?php foreach ( $info_data as $data ) : ?>
		<div class="flex justify-center text-center p-9 bg-white rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700">
			<div>
				<div
					class="lb-icon-wrapper mb-5 inline-flex items-center justify-center mx-auto rounded-full size-14 bg-custom-100 text-custom-500 dark:bg-custom-500/20">
					<img width="24" height="24" src="<?php echo esc_url( $data['svg'] ); ?>" alt="LinkBoss">
				</div>
				<h4 class="mb-3 mt-0 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
					<?php echo esc_attr( $data['value'] ); ?>
				</h4>
				<h5 class="mb-0 mt-0 text-sm font-semibold tracking-tight text-gray-500 dark:text-white uppercase">
					<?php echo esc_html( $data['name'] ); ?>
				</h5>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="mt-10">
	<div class="relative overflow-x-auto shadow-md sm:rounded-lg">
		<table id="lb-posts-table" class="lb-posts-table display w-full text-sm text-left text-gray-500 dark:text-gray-400">
			<caption class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">
				<?php esc_html_e( 'Latest Updated Posts & Pages.', 'semantic-linkboss' ); ?>
				<p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
					<?php esc_html_e( 'The following posts and pages have been updated recently.', 'semantic-linkboss' ); ?>
				</p>
			</caption>
			<thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
				<tr>
					<th scope="col" class="px-6 py-3">
						<?php esc_html_e( 'Pages & Posts', 'semantic-linkboss' ); ?>
					</th>
					<th scope="col" class="px-6 py-3">
						<?php esc_html_e( 'Updated At', 'semantic-linkboss' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if ( $api_valid ) {
					\SEMANTIC_LB\Classes\Render::latest_updated_posts( $updates_posts );
				}
				?>
			</tbody>
		</table>
	</div>

</div>
