<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$edd_is_inactive = ( ! is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) && ! is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) );
?>
	<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
		<h2>
			<?php esc_html_e( 'Easy Digital Downloads Integration', 'gtm-kit' ); ?>
		</h2>
		<p><?php esc_html_e( 'Easy way to sell Digital Products With WordPress', 'gtm-kit' ) . ': <a href="https://easydigitaldownloads.com/" target="_blank">Easy Digital Downloads</a>'; ?></p>
		<?php if ( $edd_is_inactive ): ?>
			<p>
				<span class="error"><?php esc_html_e( 'Easy Digital Downloads is not installed', 'gtm-kit' ); ?></span>.
				<?php
				printf(
					__( 'You can download %s here.', 'gtm-kit' ),
					'<a href="https://wordpress.org/plugins/easy-digital-downloads/" target="_blank">Easy Digital Downloads</a>'
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<div class="gtmkit_section_message warning">
		<div class="stuffbox">
			<h3 class="hndle">Google Analytics</h3>
			<div class="inside">
				<ul>
					<li><?php esc_html_e( 'Google Analytics 3 (Universal Analytics) properties will stop collecting data starting July 1, 2023. GTM Kit does not support Enhanced Ecommerce with Google Analytics 3 (Universal Analytics).', 'gtm-kit' ); ?></li>
					<li><?php esc_html_e( 'It’s recommended that you create a Google Analytics 4 property instead. Note that it is possible to use GA4 events for GA3 Enhanced Ecommerce.', 'gtm-kit' ); ?></li>
				</ul>
				<br>
				<ul>
					<li><b><?php esc_html_e( 'The following GA4 events are supporteret:', 'gtm-kit' ); ?></b></li>
					<li>view_item</li>
					<li>add_to_cart</li>
					<li>purchase</li>
				</ul>
			</div>
		</div>
	</div>

	<?php
$form->setting_row(
	'checkbox-toggle',
	'edd_integration',
	__( 'Track Easy Digital Downloads', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $edd_is_inactive,
		]
	],
	__( 'Choose this option if you would like to track e-commerce data.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'edd_use_sku',
	__( 'Use SKU instead of ID', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $edd_is_inactive,
		]
	],
	__( 'Use SKU instead of the product ID with fallback to ID if no SKU is set.', 'gtm-kit' )
);

$form->setting_row(
	'select',
	'edd_google_business_vertical',
	__( 'Google Business Vertical', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $edd_is_inactive,
		],
		'options'    => [
			'retail'       => __( 'Retail', 'gtm-kit' ) . ' - (retail)',
			'education'    => __( 'Education', 'gtm-kit' ) . ' - (education)',
			'flights'      => __( 'Flights', 'gtm-kit' ) . ' - (flights)',
			'hotel_rental' => __( 'Hotel rental', 'gtm-kit' ) . ' - (hotel_rental)',
			'jobs'         => __( 'Jobs', 'gtm-kit' ) . ' (jobs)',
			'local'        => __( 'Local deals', 'gtm-kit' ) . ' - (local)',
			'real_estate'  => __( 'Real estate', 'gtm-kit' ) . ' - (real_estate)',
			'travel'       => __( 'Travel', 'gtm-kit' ) . ' - (travel)',
			'custom'       => __( 'Custom', 'gtm-kit' ) . ' - (custom)',
		]
	],
	__( 'In order to use Google Ads Remarketing you must select your business type (vertical).', 'gtm-kit' )
);

$form->setting_row(
	'text-input',
	'edd_product_id_prefix',
	__( 'Product ID prefix', 'gtm-kit' ),
	[],
	__( 'If your product feed generator is adding a prefix to the product IDs, you can add the prefix here to include it in the Data Layer.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'edd_exclude_tax',
	__( 'Exclude tax', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $edd_is_inactive,
		]
	],
	__( 'Exclude tax from prices and revenue', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'edd_dequeue_script',
	__( 'Dequeue Default JS', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $edd_is_inactive,
		]
	],
	__( 'Enable this option to dequeue the default JavaScript if you plan to create your own JavaScript.', 'gtm-kit' )
);

