<?php

if( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<footer class="bg-white rounded-lg shadow mt-10 dark:bg-gray-800">
	<div class="w-full p-4 md:flex md:items-center md:justify-between">
		<span class="text-sm text-gray-500 sm:text-center dark:text-gray-400">
			<?php esc_html_e( 'Thank You for Using', 'semantic-linkboss' ); ?>
			<a target="_blank" href="https://linkboss.io" class="hover:underline">LINKBOSS</a>
			v
			<?php echo esc_html( SEMANTIC_LB_VERSION ); ?>
		</span>
		<ul class="flex flex-wrap items-center mt-3 text-sm font-medium text-gray-500 dark:text-gray-400 sm:mt-0">
			<li>
				<a target="_blank" href="https://linkboss.io/about-us/" class="mr-4 hover:underline md:mr-6 ">
					<?php esc_html_e( 'About', 'semantic-linkboss' ); ?>
				</a>
			</li>
			<li>
				<a target="_blank" href="https://linkboss.io/contact/" class="mr-4 hover:underline md:mr-6">
					<?php esc_html_e( 'Contact', 'semantic-linkboss' ); ?>
				</a>
			</li>
			<li>
				<a target="_blank" href="https://linkboss.io/terms-and-conditions/" class="mr-4 hover:underline md:mr-6">
					<?php esc_html_e( 'Terms & Conditions', 'semantic-linkboss' ); ?>
				</a>
			</li>
			<li>
				<a target="_blank" href="https://linkboss.io/privacy-policy/" class="mr-4 hover:underline md:mr-6">
					<?php esc_html_e( 'Privacy Policy', 'semantic-linkboss' ); ?>
				</a>
			</li>

		</ul>
	</div>
</footer>
