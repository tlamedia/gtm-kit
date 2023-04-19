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
<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
	<h2>
		<?php esc_html_e( "What's new", 'gtm-kit' ); ?>
	</h2>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle">1.8: <?php esc_html_e( 'Include the customer data in the data layer', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<p><?php esc_html_e( 'This release is the first of two steps to include customer data in the data layer for all events.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'You can now add the customer data to the "purchase" event.', 'gtm-kit' ); ?></p>
			<p>
				<a href="https://gtmkit.com/gtm-kit-1-9-include-the-customer-data-in-the-data-layer/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-9">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle">1.8: <?php esc_html_e( 'Support for the add_to_wishlist event', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<p><?php esc_html_e( 'If you are using ‘YITH WooCommerce Wishlist’ or ‘TI WooCommerce Wishlist’ you can now track users who add a product to the wishlist. Between them theese two plugins have 1 million active installations.', 'gtm-kit' ); ?></p>
			<p><?php esc_html_e( 'It requires no configuration and all you have to do is install and activate one of the two plugins.', 'gtm-kit' ); ?></p>
			<p>
				<a href="https://gtmkit.com/gtm-kit-1-8-support-for-the-add_to_wishlist-event/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-8">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle">1.7: <?php esc_html_e( 'Support for the new WooCommerce checkout block', 'gtm-kit' ); ?></h3>
		<div class="inside">
			<p>
				<?php esc_html_e( 'The Checkout block was introduced in WooCommerce 6.9 and until now there has been limited support for this new block type in GTM Kit, but this changes today. There is now full support for all the checkout events:', 'gtm-kit' ); ?>
				begin_checkout, add_shipping_info, add_payment_info
			</p>
			<p><?php esc_html_e( 'These events now work in same way as on the classic checkout page.', 'gtm-kit' ); ?></p>
			<p>
				<a href="https://gtmkit.com/gtm-kit-1-7-support-for-the-new-woocommerce-checkout-block/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=release-notes-1-7">
					<?php esc_html_e( 'Read about all the other change in the release Notes', 'gtm-kit' ); ?>
				</a>
			</p>
		</div>
	</div>
</div>

<div class="gtmkit_section_message">
	<div class="stuffbox">
		<h3 class="hndle">
			<?php esc_html_e( 'Older releases', 'gtm-kit' ); ?>
		</h3>
		<div class="inside">
			<ul>
				<li>
					<a href="https://gtmkit.com/changelog/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=changelog&utm_content=whtats-new"
					   target="_blank">Changelog</a></li>
			</ul>
		</div>
	</div>
</div>
