<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */ // phpcs:ignore

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}
?>
<h2 class="gtmkit-text-2xl gtmkit-font-bold gtmkit-text-color-heading gtmkit-mb-8">
	<?php esc_html_e( "What's new", 'gtm-kit' ); ?>
</h2>

<div class="gtmkit-text-sm">

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.13: <?php esc_html_e( 'Google Consent Mode default settings for sites not using a Consent Management Platform', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'Not all sites are using Consent Management Platform to handle user consent , and we now allow those sites to set the default Consent Mode settings.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'We have put a lot of work in to improving the code quality and improving the compliance with WordPress Coding Standards.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-13/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-12">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.12: <?php esc_html_e( 'Improved support for the WooCommerce cart and checkout blocks', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'The new WooCommerce blocks evolve with every release of WooCommerce, and we are thrilled to announce that GTM Kit now support the cart and checkout blocks using the native WooCommerce events.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'Using the native JavaScript events is a much more reliable method than trying to parse the HTML which is subject to design changes.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-12/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-12">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.11: <?php esc_html_e( 'GTM container import files', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'We have added a help section with Google Tag Manager import files that will help you configure GTM quickly.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'The first import files cover Google Analytics 4 but more will follow.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-11/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-11">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.10: <?php esc_html_e( 'Setup wizard for new users', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'We are introducing a setup-wizard for new users that will help set up GTM Kit.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-10-setup-wizard-for-new-users/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-10">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			<?php esc_html_e( 'Older releases', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3">
			<p class="gtmkit-text-color-primary">
				<a href="https://gtmkit.com/changelog/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=whtats-new" target="_blank">
					<?php esc_html_e( 'Changelog', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-mt-16 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey gtmkit-flex gtmkit-items-center">
			<?php esc_html_e( 'About GTM Kit', 'gtm-kit' ); ?>
			<span class="gtmkit-text-sm gtmkit-text-color-grey gtmkit-font-light gtmkit-ml-2">(<?php esc_html_e( 'Version:', 'gtm-kit' ); ?> <?php echo esc_html( GTMKIT_VERSION ); ?>)</span>
		</h3>
		<div class="gtmkit-p-3">
			<p>
				<?php esc_html_e( 'The plugin is open source (GPL v3 license) and contributions are welcome:', 'gtm-kit' ); ?>
				<a class="gtmkit-text-color-primary" href="https://github.com/tlamedia/gtm-kit" target="_blank"><?php esc_attr_e( 'GTM Kit Repository', 'gtm-kit' ); ?></a>
			</p>
		</div>
	</div>

</div>
