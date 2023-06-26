<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

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
			1.10: <?php esc_html_e( 'Setup wizard for new users', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'we are introducing a setup-wizard for new users that will help set up GTM Kit.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-10-setup-wizard-for-new-users/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-10">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.9: <?php esc_html_e( 'Include the customer data in the data layer', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'This release is the first of two steps to include customer data in the data layer for all events.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'You can now add the customer data to the "purchase" event.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-9-include-the-customer-data-in-the-data-layer/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-9">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.8: <?php esc_html_e( 'Support for the add_to_wishlist event', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p><?php esc_html_e( 'If you are using ‘YITH WooCommerce Wishlist’ or ‘TI WooCommerce Wishlist’ you can now track users who add a product to the wishlist. Between them theese two plugins have 1 million active installations.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'It requires no configuration and all you have to do is install and activate one of the two plugins.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-8-support-for-the-add_to_wishlist-event/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-8">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="gtmkit-my-6 gtmkit-border gtmkit-bg-white gtmkit-w-3/4 gtmkit-border-color-grey">
		<h3 class="gtmkit-font-bold gtmkit-text-lg gtmkit-px-3 gtmkit-py-2 gtmkit-border-b gtmkit-border-color-grey">
			1.7: <?php esc_html_e( 'Support for the new WooCommerce checkout block', 'gtm-kit' ); ?>
		</h3>
		<div class="gtmkit-p-3 gtmkit-space-y-1">
			<p>
				<?php esc_html_e( 'The Checkout block was introduced in WooCommerce 6.9 and until now there has been limited support for this new block type in GTM Kit, but this changes today. There is now full support for all the checkout events:', 'gtm-kit' ); ?>
				begin_checkout, add_shipping_info, add_payment_info
			</p>
			<p><?php esc_html_e( 'These events now work in same way as on the classic checkout page.', 'gtm-kit' ); ?></p>
			<p class="gtmkit-text-color-primary !gtmkit-mt-4">
				<a href="https://gtmkit.com/gtm-kit-1-7-support-for-the-new-woocommerce-checkout-block/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-7">
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
