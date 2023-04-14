<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\OptionsForm;

/** @var OptionsForm $form */

if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

$woocommerce_is_inactive = ! is_plugin_active( 'woocommerce/woocommerce.php' );
?>
	<div class="gtmkit-setting-row gtmkit-setting-row-heading gtmkit-clear">
		<h2>
			<?php esc_html_e( 'WooCommerce Integration', 'gtm-kit' ); ?>
		</h2>
		<p><?php esc_html_e( 'The #1 open source eCommerce platform built for WordPress', 'gtm-kit' ) . ': <a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'; ?></p>
		<?php if ( $woocommerce_is_inactive ): ?>
			<p>
				<span class="error"><?php esc_html_e( 'WooCommerce is not installed', 'gtm-kit' ); ?></span>.
				<?php
				printf(
					__( 'You can download %s here.', 'gtm-kit' ),
					'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
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
					<li><?php esc_html_e( 'Google Analytics 3 (Universal Analytics) properties will stop collecting data starting July 1, 2023 and therefor a Google Analytics 4 property is required.', 'gtm-kit' ); ?></li>
					<li>
						<?php esc_html_e( 'GTM Kit is only supporting GA4 events but you can use GA4 events in Universal Analytics Enhanced Ecommerce.', 'gtm-kit' ); ?>
						<a href="https://gtmkit.com/guides/use-ga4-ecommerce-events-in-universal-analytics/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=woocommerce-integration&utm_content=help-tutorials" target="_blank">
							<?php esc_html_e( 'Read more', 'gtm-kit' ); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>

	<?php
$form->setting_row(
	'checkbox-toggle',
	'woocommerce_integration',
	__( 'Track WooCommerce', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Choose this option if you would like to track e-commerce data.', 'gtm-kit' )
);

$taxonomies = get_taxonomies(
	[
		'show_ui'  => true,
		'public'   => true,
		'_builtin' => false,
	],
	'object'
);

$field_data            = [
	'attributes' => [
		'disabled' => $woocommerce_is_inactive,
	]
];
$field_data['options'] = [];

foreach ( $taxonomies as $taxonomy ) {
	$field_data['options'][ $taxonomy->name ] = $taxonomy->label;
}

$form->setting_row(
	'select',
	'woocommerce_brand',
	__( 'Brand', 'gtm-kit' ),
	$field_data
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_use_sku',
	__( 'Use SKU instead of ID', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Use SKU instead of the product ID with fallback to ID if no SKU is set.', 'gtm-kit' )
);

$form->setting_row(
	'select',
	'woocommerce_google_business_vertical',
	__( 'Google Business Vertical', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
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
	'woocommerce_product_id_prefix',
	__( 'Product ID prefix', 'gtm-kit' ),
	[],
	__( 'If your product feed generator is adding a prefix to the product IDs, you can add the prefix here to include it in the Data Layer.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_exclude_tax',
	__( 'Exclude tax', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Exclude tax from prices and revenue', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_exclude_shipping',
	__( 'Exclude shipping from revenue', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Exclude shipping from revenue', 'gtm-kit' )
);

$field_data                = [
	'attributes' => [
		'disabled' => $woocommerce_is_inactive,
	]
];
$field_data['options']     = [
	1 => [
		'label' => __( "When the 'Place order' button is clicked", 'gtm-kit' ),
	],
	2 => [
		'label' => __( "When a shipment method is selected with fallback to the 'Place order' button.", 'gtm-kit' ),
	],
	0 => [
		'label' => __( "Disable the 'add_shipment_info' event.", 'gtm-kit' ),
	]
];
$field_data['legend']      = __( 'When do you want to fire the "add_shipment_info" event?', 'gtm-kit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_shipping_info',
	__( 'Event: add_shipping_info', 'gtm-kit' ),
	$field_data
);

$field_data                = [
	'attributes' => [
		'disabled' => $woocommerce_is_inactive,
	]
];
$field_data['options']     = [
	1 => [
		'label' => __( "When the 'Place order' button is clicked", 'gtm-kit' ),
	],
	2 => [
		'label' => __( "When a payment method is selected with fallback to the 'Place order' button.", 'gtm-kit' ),
	],
	0 => [
		'label' => __( "Disable the 'add_payment_info' event.", 'gtm-kit' ),
	]
];
$field_data['legend']      = __( 'When do you want to fire the "add_payment_info" event?', 'gtm-kit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_payment_info',
	__( 'Event: add_payment_info', 'gtm-kit' ),
	$field_data
);


$field_data['options']     = [
	0 => [
		'label' => __( "Only push view_item on the master product", 'gtm-kit' ),
	],
	1 => [
		'label' => __( "Push view_item on master and variation products (higher number of views).", 'gtm-kit' ),
	],
	2 => [
		'label' => __( "Only push view_item on variation products.", 'gtm-kit' ),
	],
];
$field_data['legend']      = __( 'When do you want to fire the "view_item" event on variable products?', 'gtm-kit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_variable_product_tracking',
	__( 'Event: view_item', 'gtm-kit' ),
	$field_data
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_include_permalink_structure',
	__( 'Include permalink structure', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option to include the permalink structure of the product base, category base, tag base and attribute base.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_include_pages',
	__( 'Include pages', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option to include the path of cart, checkout, order received adn my account page.', 'gtm-kit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_include_customer_data',
	__( 'Include customer data', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option to include customer data in the data layer on the "purchase" event.', 'gtm-kit' )
);


$form->setting_row(
	'checkbox-toggle',
	'woocommerce_dequeue_script',
	__( 'Dequeue Default JS', 'gtm-kit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option to dequeue the default JavaScript if you plan to create your own JavaScript.', 'gtm-kit' )
);
