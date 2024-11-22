<?php

/**
 * Settings Page
 *
 * @package SEMANTIC_LB
 * @since 2.5.0
 */


?>
<div class="wrap">
	<!-- <h1 class="wp-heading-inline"></h1> -->
	<div class="linkboss linkboss-wrapper">
		<div class="mt-10">
			<div class="relative overflow-x-auto shadow-md sm:rounded-lg">
				<?php
				$posts = \SEMANTIC_LB\Classes\Render::posts_logs();
				if ( ! empty( $posts ) ) : ?>
					<table id="lb-posts-table"
						class="lb-posts-table display w-full text-sm text-left text-gray-500 dark:text-gray-400">
						<caption class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">
							<?php esc_html_e( 'Latest Updated Posts & Pages Logs.', 'semantic-linkboss' ); ?>
							<p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
								<?php esc_html_e( 'The most recent 200 posts & pages.', 'semantic-linkboss' ); ?>
							</p>
							<p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">
								<?php //esc_html_e( 'Next Schedules Cron Job - ', 'semantic-linkboss' ); ?>
								<strong><?php //echo esc_html( $lb_sync_timestamp ); ?></strong>
							</p>
						</caption>
						<thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
							<tr>
								<th scope="col" class="px-6 py-3">
									<?php esc_html_e( 'Pages & Posts', 'semantic-linkboss' ); ?>
								</th>
								<th scope="col" class="px-6 py-3">
									<?php esc_html_e( 'Time', 'semantic-linkboss' ); ?>
								</th>
								<th scope="col" class="px-6 py-3">
									<?php esc_html_e( 'Status', 'semantic-linkboss' ); ?>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $posts as $post ) : ?>
								<tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
									<th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
										<div title="Post ID - <?php echo esc_attr( $post['post_id'] ); ?>">
											<?php echo esc_html( $post['post_title'] ); ?>
										</div>
									</th>
									<td class="px-6 py-4">
										<strong>Created - </strong> <?php echo esc_html( $post['created_at'] ); ?>
										<br>
										<?php if ( isset( $post['sync_at'] ) ) : ?>
											<div class="mt-2">
												<strong>Sync - </strong> <?php echo esc_html( $post['sync_at'] ); ?>
											</div>
										<?php endif; ?>
									</td>
									<td class="px-6 py-4">
										<?php
										if ( $post['sent_status'] == 1 ) {
											echo '<span class="bg-green-100 text-green-800 text-sm font-medium me-2 px-2.5 py-2.5 rounded dark:bg-green-900 dark:text-green-300">Sent</span>';
										} else if( $post['sent_status'] == 'F' ) {
											echo '<span class="bg-red-100 text-red-800 text-sm font-medium me-2 px-2.5 py-2.5 rounded dark:bg-red-900 dark:text-red-300">Failed</span>';
										} else {
											echo '<span class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-2.5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">In Queue</span>';
										}
										?>
										<?php if ( isset( $post['sent_status'] ) && $post['sent_status'] !== false ) : ?>
											<button data-post-id="<?php echo esc_attr( $post['post_id'] ); ?>" type="button"
												class="lb-sync-again-by-id text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-2.5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800"
												title="Sync Again">
												<svg fill="#fff" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
													<path
														d="M386.3 160L336 160c-17.7 0-32 14.3-32 32s14.3 32 32 32l128 0c17.7 0 32-14.3 32-32l0-128c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 51.2L414.4 97.6c-87.5-87.5-229.3-87.5-316.8 0s-87.5 229.3 0 316.8s229.3 87.5 316.8 0c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0c-62.5 62.5-163.8 62.5-226.3 0s-62.5-163.8 0-226.3s163.8-62.5 226.3 0L386.3 160z" />
												</svg>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="p-5 text-lg font-semibold text-left text-gray-900 bg-white dark:text-white dark:bg-gray-800">
						<?php esc_html_e( 'No logs available.', 'semantic-linkboss' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
