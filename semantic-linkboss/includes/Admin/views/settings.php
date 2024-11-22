<?php

/**
 * Settings Page
 *
 * @package SEMANTIC_LB
 * @since 0.0.0
 */
use SEMANTIC_LB\Traits\Global_Functions;

$api_key = get_option( 'linkboss_api_key', '' );

?>
<div class="wrap">
	<!-- <h1 class="wp-heading-inline"></h1> -->
	<div class="linkboss linkboss-wrapper">
		<div class="flex flex-col min-h-screen">
			<?php
			require_once SEMANTIC_LB_INC_PATH . 'Admin/views/inc/nav.php';
			?>
			<div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-9">
				<div
					class="lb-auth-form-wrapper relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700 text-center lg:col-span-2">
					<h3 class="mb-2 mt-0 text-lg font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Prepare Your Data for Syncing.', 'semantic-linkboss' ); ?>
					</h3>
					<p class="mb-6 text-gray-500 dark:text-gray-400">
						<?php esc_html_e( 'Initiate your database for seamless synchronization; a brief setup initially, followed by automatic functionality.', 'semantic-linkboss' ); ?>
					</p>
					<?php
					$reports_batch = \SEMANTIC_LB\Classes\Render::sync_batch_init_reports();
					$page = isset( $reports_batch['pages'] ) ? (int) $reports_batch['pages'] : 0;
					$post = isset( $reports_batch['posts'] ) ? (int) $reports_batch['posts'] : 0;

					$total_batch = isset( $reports_batch['total_batch'] ) ? (int) $reports_batch['total_batch'] : 0;
					$batch = isset( $reports_batch['sync_batch'] ) ? (int) $reports_batch['sync_batch'] : 0;
					$require_init = ( $page + $post ) - $total_batch;
					$require_init = ( $require_init <= 0 ) ? 0 : $require_init;
					$sync_done = isset( $reports_batch['sync_done'] ) ? $reports_batch['sync_done'] : 0;
					$content_size = isset( $reports_batch['content_size'] ) ? $reports_batch['content_size'] : 0;

					if ( ( $page + $post ) != 0 ) {
						$perc = ( $total_batch / ( $page + $post ) ) * 100;
						if ( $perc > 100 ) {
							$perc = 100;
						}
					} else {
						$perc = 0;
					}

					/**
					 * Localize for Init API request
					 */

					$script_config = array(
						'posts' => $post + $page,
						'category' => $reports_batch['total_categories'],
						'sync_done' => (int) $sync_done,
					);

					wp_localize_script( 'semantic-linkboss', 'LinkbossConfigInit', $script_config );

					?>
					<div>

						<dl class="grid max-w-screen-xl grid-cols-3 gap-8 mx-auto text-gray-900 lg:grid-cols-6 dark:text-white">
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $page ); ?>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">Pages</dd>
							</div>
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $post ); ?>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">Posts</dd>
							</div>
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $batch ); ?>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">On Batch</dd>
							</div>
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $require_init ); ?>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">Remaining for Batch</dd>
							</div>
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $sync_done ); ?>
									<sup class="font-medium text-gray-500 text-xs"> out of <?php echo esc_html( $total_batch ); ?></sup>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">Sync Done</dd>
							</div>
							<div class="flex flex-col items-center justify-center">
								<dt class="mb-2 text-3xl font-extrabold">
									<?php echo esc_html( $content_size ); ?>
								</dt>
								<dd class="text-gray-500 dark:text-gray-400">Content Size</dd>
							</div>
						</dl>
					</div>
					<div class="relative mt-5 h-[6px] w-full rounded-lg bg-[#F7B787] max-w-md"
						style="margin-left: auto; margin-right: auto;">
						<div id="lb-db-init-progress-bar"
							class="lb-db-init-progress-bar absolute left-0 right-0 h-full w-[0%] rounded-lg bg-[#6A64F1]"
							style="width:<?php echo esc_attr( $perc ); ?>%;">
						</div>
					</div>
					<div class="mt-3">
						<?php echo esc_html( round( $perc, 2 ) ); ?> of 100 %
					</div>
					<?php if ( $require_init > 0 ) : ?>
						<div class="mt-6">
							<button id="lb-init-db-btn"
								class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 inline-flex items-center gap-1">
								<?php esc_html_e( 'Prepare Data', 'semantic-linkboss' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
				<div
					class="relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700 text-left lg:col-span-1">
					<h3 class="mb-2 mt-0 text-lg font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Custom Query Builder', 'semantic-linkboss' ); ?>
					</h3>
					<div>
						<p class="mb-6 text-gray-500 dark:text-gray-400">
							<?php esc_html_e( 'Use the custom query builder to SYNC your desired Posts.', 'semantic-linkboss' ); ?>
						</p>
						<div class="linkboss-custom-query-form-response"></div>
						<form id="linkboss-custom-query-form" method="POST" class="m-0">
							<!-- Post Type -->
							<div class="mb-6">
								<label for="linkboss_post_sources" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
									<?php esc_html_e( 'Post Sources', 'semantic-linkboss' ); ?>
								</label>
								<select id="linkboss_post_sources" multiple name="linkboss_qb[post_sources][]"
									class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
									<?php
									$post_types = \SEMANTIC_LB\Classes\Render::get_group_control_query_post_types();
									foreach ( $post_types as $key => $value ) {
										echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
									}
									?>
								</select>
							</div>
							<!-- Categories  -->
							<div class="mb-6">
								<label for="linkboss_categories" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
									<?php esc_html_e( 'Categories', 'semantic-linkboss' ); ?>
								</label>
								<select id="linkboss_categories" multiple name="linkboss_qb[categories][]"
									class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
									<option value="0">
										<?php esc_html_e( 'All', 'semantic-linkboss' ); ?>
									</option>
								</select>
								<p class="mt-3 text-sm leading-6 text-gray-600">
									<?php esc_html_e( 'Leave this field empty to sync All Categories.', 'semantic-linkboss' ); ?>
								</p>
							</div>

							<input type="hidden" name="action" value="linkboss_custom_query">
							<input type="hidden" name="_wpnonce"
								value="<?php echo esc_attr( wp_create_nonce( 'linkboss_nonce' ) ); ?>">
							<button type="submit"
								class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 inline-flex items-center gap-1">
								<?php esc_html_e( 'Save Query', 'semantic-linkboss' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
			<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mt-9">
				<div
					class="lb-auth-form-wrapper relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
					<div id="lb-bg-data-loading" class="absolute w-full h-full top-0 left-0 hidden">
						<!-- Not Used -->
						<div class="absolute -translate-x-1/2 -translate-y-1/2 top-2/4 left-1/2">
							<svg style="margin: 0 auto;" aria-hidden="true"
								class="w-12 h-12 text-gray-200 animate-spin dark:text-gray-600 fill-blue-600" viewBox="0 0 100 101"
								fill="none" xmlns="http://www.w3.org/2000/svg">
								<path
									d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z"
									fill="currentColor" />
								<path
									d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z"
									fill="currentFill" />
							</svg>
							<span>Background Data Processing. Please wait...</span>
						</div>
					</div>
					<form id="linkboss-settings-form" method="POST" class="m-0">
						<div class="linkboss-settings-form-response"></div>
						<div id="lb-settings-progress-wrap" class="hidden mb-6 pt-4">
							<div class="rounded-md bg-[#F5F7FB] py-4 px-8">
								<div class="flex items-center justify-between">
									<span class="truncate pr-3 text-base font-medium text-[#07074D]">
										<?php esc_html_e( 'SYNCING...', 'semantic-linkboss' ); ?>
									</span>
								</div>
								<div class="relative mt-5 h-[6px] w-full rounded-lg bg-[#E2E5EF]">
									<div id="lb-progress-bar"
										class="lb-progress-bar absolute left-0 right-0 h-full w-[0%] rounded-lg bg-[#6A64F1]">
									</div>
								</div>
							</div>
						</div>
						<div class="mb-6">
							<label for="linkboss_api_key" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
								<?php esc_html_e( 'Your API Key', 'semantic-linkboss' ); ?>
							</label>
							<input name="linkboss_api_key" type="text" id="linkboss_api_key"
								class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
								placeholder="XXXX-XXXX-XXXX-XXXX" required value="<?php echo esc_html( $api_key ); ?>">
						</div>
						<div class="mb-6">
							<div class="flex">
								<div class="flex items-center">
									<input id="linkboss-force-sync" type="checkbox" value="yes"
										class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
								</div>
								<div class="ms-2 text-sm">
									<label for="linkboss-force-sync" class="font-medium text-gray-900 dark:text-gray-300">
										<?php esc_html_e( 'Force Sync Data', 'semantic-linkboss' ); ?>
									</label>
									<p id="linkboss-force-sync-text" class="text-xs font-normal text-gray-500 dark:text-gray-300 m-0">
										Check this box forcly update data on the Linkboss server.
									</p>
								</div>
							</div>
						</div>

						<input type="hidden" name="action" value="linkboss_save_settings">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'linkboss_nonce' ) ); ?>">
						<button type="submit"
							class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 inline-flex items-center gap-1">
							<?php esc_html_e( 'Save Settings & Sync', 'semantic-linkboss' ); ?>
						</button>
					</form>
					<?php if ( $require_init > 0 ) : ?>
						<div class="mt-6">
							<div
								class="flex items-center p-4 mb-4 text-sm text-red-800 border border-red-300 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400 dark:border-red-800"
								role="alert">
								<svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
									fill="currentColor" viewBox="0 0 20 20">
									<path
										d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z" />
								</svg>
								<span class="sr-only">Info</span>
								<div class="px-2">
									<span
										class="font-medium"><?php esc_html_e( 'Make sure all the contents(posts and pages) are prepared(On Batch) before pressing this button.	', 'semantic-linkboss' ); ?></span>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div
					class="lb-auth-form-wrapper relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">

					<h3 class="mb-2 mt-0 text-lg font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Authentication requirements:', 'semantic-linkboss' ); ?>
					</h3>
					<ul class="space-y-1 text-gray-500 list-disc list-inside dark:text-gray-400">
						<li>
							<?php esc_html_e( 'Open your linkboss account from', 'semantic-linkboss' ); ?> <a target="_blank"
								href="https://app.linkboss.io">https://app.linkboss.io</a>
						</li>
						<li>
							<?php esc_html_e( 'Add this domain to your account', 'semantic-linkboss' ); ?>
							( <strong>
								<?php
								echo esc_html( home_url() );
								?>
							</strong> )
						</li>
						<li>
							<?php esc_html_e( 'Copy the API key from site settings and paste it here', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Click "Prepare Data" to get your Posts and Pages ready to Sync', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Wait till your Pages and Posts count matches your existing site data', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Now click "Save Settings & Sync" and wait for the "All the prepared contents are now synced!" message', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'Go to the app\'s Dashboard and do a reload', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<?php esc_html_e( 'The Configure button will turn into Tools', 'semantic-linkboss' ); ?>
						</li>
						<li>
							<div class="inline-flex gap-1">
								<?php esc_html_e( 'Video Tutorial :', 'semantic-linkboss' ); ?> <a
									href="https://www.youtube.com/watch?v=rZX93rkjG2c" target="_blank" class="flex gap-1">
									<span><svg width="20px" height="20px" viewBox="0 -7 48 48" version="1.1"
											xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
											<g id="Icons" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
												<g id="Color-" transform="translate(-200.000000, -368.000000)" fill="#CE1312">
													<path
														d="M219.044,391.269916 L219.0425,377.687742 L232.0115,384.502244 L219.044,391.269916 Z M247.52,375.334163 C247.52,375.334163 247.0505,372.003199 245.612,370.536366 C243.7865,368.610299 241.7405,368.601235 240.803,368.489448 C234.086,368 224.0105,368 224.0105,368 L223.9895,368 C223.9895,368 213.914,368 207.197,368.489448 C206.258,368.601235 204.2135,368.610299 202.3865,370.536366 C200.948,372.003199 200.48,375.334163 200.48,375.334163 C200.48,375.334163 200,379.246723 200,383.157773 L200,386.82561 C200,390.73817 200.48,394.64922 200.48,394.64922 C200.48,394.64922 200.948,397.980184 202.3865,399.447016 C204.2135,401.373084 206.612,401.312658 207.68,401.513574 C211.52,401.885191 224,402 224,402 C224,402 234.086,401.984894 240.803,401.495446 C241.7405,401.382148 243.7865,401.373084 245.612,399.447016 C247.0505,397.980184 247.52,394.64922 247.52,394.64922 C247.52,394.64922 248,390.73817 248,386.82561 L248,383.157773 C248,379.246723 247.52,375.334163 247.52,375.334163 L247.52,375.334163 Z">
													</path>
												</g>
											</g>
										</svg></span>
									https://www.youtube.com/watch?v=rZX93rkjG2c
								</a>
							</div>
						</li>
					</ul>
				</div>
			</div>
			<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mt-9">
				<div
					class="lb-auth-form-wrapper relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
					<h3 class="mb-2 mt-0 text-lg font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Reset Sync Batch', 'semantic-linkboss' ); ?>
					</h3>
					<p class="mb-6 text-gray-500 dark:text-gray-400">
						<?php esc_html_e( 'If you want to reset the sync batch, click the button below.', 'semantic-linkboss' ); ?>
					</p>
					<div class="linkboss-reset-syncs-form-response"></div>

					<form id="linkboss-reset-sync-batch-form" method="POST" class="m-0">
						<input type="hidden" name="action" value="linkboss_reset_sync_batch">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'linkboss_nonce' ) ); ?>">
						<button type="submit"
							class="text-white bg-red-700 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-auto px-5 py-2.5 text-center dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-blue-800 inline-flex items-center gap-1">
							<?php esc_html_e( 'Reset Sync Batch', 'semantic-linkboss' ); ?>
						</button>
					</form>
				</div>
				<div
					class="lb-auth-form-wrapper relative p-9 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700">
					<h3 class="mb-2 mt-0 text-lg font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Sync Speed', 'semantic-linkboss' ); ?>
					</h3>
					<p class="mb-6 text-gray-500 dark:text-gray-400">
						<?php esc_html_e( 'If you encounter any difficulties, try reducing the speed and sync again.', 'semantic-linkboss' ); ?>
					</p>
					<form id="linkboss-sync-speed-form" method="POST" class="m-0">
						<input type="hidden" name="action" value="linkboss_sync_speed">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'linkboss_nonce' ) ); ?>">
						<div class="flex gap-4">
							<div class="w-40">
								<?php
								$speed = get_option( 'linkboss_sync_speed', '512' );
								?>
								<select name="linkboss_sync_speed"
									class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
									<option value="100" <?php echo $speed == '100' ? 'selected' : ''; ?>>
										<?php esc_html_e( 'Very Slow (100KB)', 'semantic-linkboss' ); ?>
									</option>
									<option value="200" <?php echo $speed == '200' ? 'selected' : ''; ?>>
										<?php esc_html_e( 'Slow (200KB)', 'semantic-linkboss' ); ?>
									</option>
									<option value="300" <?php echo $speed == '300' ? 'selected' : ''; ?>>
										<?php esc_html_e( 'Medium (300KB)', 'semantic-linkboss' ); ?>
									</option>
									<option value="512" <?php echo $speed == '512' ? 'selected' : ''; ?>>
										<?php esc_html_e( 'Fast (512)', 'semantic-linkboss' ); ?>
									</option>
								</select>
							</div>

							<div>
								<button type="submit"
									class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-auto px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800 inline-flex items-center gap-1">
									<?php esc_html_e( 'Save Speed', 'semantic-linkboss' ); ?>
								</button>
							</div>
						</div>
					</form>
					<h4 class="mt-6 text-md font-semibold text-gray-900 dark:text-white">
						<?php esc_html_e( 'Builder Detected', 'semantic-linkboss' ); ?>
					</h4>
					<div class="mt-2">
						<button type="button"
							class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
							<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
								<path
									d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
							</svg>
							Gutenberg
						</button>
						<?php
						$plugin_classic = 'classic-editor/classic-editor.php';
						if ( is_plugin_active( $plugin_classic ) ) : ?>
							<button type="button"
								class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
								<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
									<path
										d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
								</svg>
								Classic Editor
							</button>
						<?php endif; ?>
						<?php if ( did_action( 'elementor/loaded' ) ) : ?>
							<button type="button"
								class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
								<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
									<path
										d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
								</svg>
								Elementor
							</button>
						<?php endif; ?>
						<?php if ( class_exists( 'ET_Builder_Module' ) ) : ?>
							<button type="button"
								class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
								<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
									<path
										d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
								</svg>
								Divi
							</button>
						<?php endif; ?>
						<?php if ( class_exists( 'CT_Component' ) ) : ?>
							<button type="button"
								class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
								<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
									<path
										d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
								</svg>
								Oxygen
							</button>
						<?php endif; ?>
						<?php if ( class_exists( 'TCB_Post' ) ) : ?>
							<button type="button"
								class="text-gray-900 bg-white hover:bg-gray-100 border border-gray-200 focus:ring-4 focus:outline-none focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:focus:ring-gray-600 dark:bg-gray-800 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700 me-2 mb-2">
								<svg class="w-4 h-4 me-2 -ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
									<path
										d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z" />
								</svg>
								Thrive Architect
							</button>
						<?php endif; ?>

					</div>
				</div>
			</div>
			<?php
			require_once SEMANTIC_LB_INC_PATH . 'Admin/views/inc/footer.php';
			?>
		</div>
	</div>
</div>
