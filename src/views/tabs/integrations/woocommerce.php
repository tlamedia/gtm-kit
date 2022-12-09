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
			<?php esc_html_e( 'WooCommerce Integration', 'gtmkit' ); ?>
		</h2>
		<p><?php esc_html_e( 'The #1 open source eCommerce platform built for WordPress', 'gtmkit' ) . ': <a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'; ?></p>
		<?php if ( $woocommerce_is_inactive ): ?>
			<p>
				<span class="error"><?php esc_html_e( 'WooCommerce is not installed', 'gtmkit' ); ?></span>.
				<?php
				printf(
					__( 'You can download %s here.', 'gtmkit' ),
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
					<li><?php esc_html_e( 'Google Analytics 3 (Universal Analytics) properties will stop collecting data starting July 1, 2023. GTM Kit does not support Enhanced Ecommerce with Google Analytics 3 (Universal Analytics).', 'gtmkit' ); ?></li>
					<li><?php esc_html_e( 'It’s recommended that you create a Google Analytics 4 property instead. Note that it is possible to use GA4 events for GA3 Enhanced Ecommerce.', 'gtmkit' ); ?></li>
				</ul>
			</div>
		</div>
	</div>

	<?php
$form->setting_row(
	'checkbox-toggle',
	'woocommerce_integration',
	__( 'Track WooCommerce', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Choose this option if you would like to track e-commerce data.', 'gtmkit' )
);

$taxonomies = get_taxonomies(
	[
		'show_ui'  => true,
		'public'   => true,
		'_builtin' => false,
	],
	'object',
	'and'
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
	__( 'Brand', 'gtmkit' ),
	$field_data
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_use_sku',
	__( 'Use SKU instead of ID', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Use SKU instead of the product ID with fallback to ID if no SKU is set.', 'gtmkit' )
);

$form->setting_row(
	'select',
	'woocommerce_google_business_vertical',
	__( 'Google Business Vertical', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		],
		'options'    => [
			'retail'       => __( 'Retail' ) . ' - (retail)',
			'education'    => __( 'Education' ) . ' - (education)',
			'flights'      => __( 'Flights' ) . ' - (flights)',
			'hotel_rental' => __( 'Hotel rental' ) . ' - (hotel_rental)',
			'jobs'         => __( 'Jobs' ) . ' (jobs)',
			'local'        => __( 'Local deals' ) . ' - (local)',
			'real_estate'  => __( 'Real estate' ) . ' - (real_estate)',
			'travel'       => __( 'Travel' ) . ' - (travel)',
			'custom'       => __( 'Custom' ) . ' - (custom)',
		]
	],
	__( 'In order to use Google Ads Remarketing you must select your business type (vertical).', 'gtmkit' )
);

$form->setting_row(
	'text-input',
	'woocommerce_product_id_prefix',
	__( 'Product ID prefix', 'gtmkit' ),
	[],
	__( 'If your product feed generator is adding a prefix to the product IDs, you can add the prefix here to include it in the Data Layer.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_exclude_tax',
	__( 'Exclude tax', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Exclude tax from prices and revenue', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_exclude_shipping',
	__( 'Exclude shipping from revenue', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Exclude shipping from revenue', 'gtmkit' )
);

$field_data                = [
	'attributes' => [
		'disabled' => $woocommerce_is_inactive,
	]
];
$field_data['options']     = [
	1 => [
		'label' => __( "When the 'Place order' button is clicked", 'gtmkit' ),
	],
	2 => [
		'label' => __( "When a shipment method is selected with fallback to the 'Place order' button.", 'gtmkit' ),
	],
	0 => [
		'label' => __( "Disable the 'add_shipment_info' event.", 'gtmkit' ),
	]
];
$field_data['legend']      = __( 'When do you want to fire the "add_shipment_info" event?', 'gtmkit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_shipping_info',
	__( 'Event: add_shipping_info', 'gtmkit' ),
	$field_data
);

$field_data                = [
	'attributes' => [
		'disabled' => $woocommerce_is_inactive,
	]
];
$field_data['options']     = [
	1 => [
		'label' => __( "When the 'Place order' button is clicked", 'gtmkit' ),
	],
	2 => [
		'label' => __( "When a payment method is selected with fallback to the 'Place order' button.", 'gtmkit' ),
	],
	0 => [
		'label' => __( "Disable the 'add_payment_info' event.", 'gtmkit' ),
	]
];
$field_data['legend']      = __( 'When do you want to fire the "add_payment_info" event?', 'gtmkit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_payment_info',
	__( 'Event: add_payment_info', 'gtmkit' ),
	$field_data
);


$field_data['options']     = [
	0 => [
		'label' => __( "Only push view_item on the master product", 'gtmkit' ),
	],
	1 => [
		'label' => __( "Push view_item on master and variation products (higher number of views).", 'gtmkit' ),
	],
];
$field_data['legend']      = __( 'When do you want to fire the "view_item" event on variable products?', 'gtmkit' );
$field_data['legend_attr'] = [ 'class' => 'radiogroup' ];

$form->setting_row(
	'radio',
	'woocommerce_variable_product_tracking',
	__( 'Event: view_item', 'gtmkit' ),
	$field_data
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_include_permalink_structure',
	__( 'Include permalink structure', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option include the permalink structure of the product base, category base, tag base and attribute base.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_include_pages',
	__( 'Include pages', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option include the path of cart, checkout, order received adn my account page.', 'gtmkit' )
);

$form->setting_row(
	'checkbox-toggle',
	'woocommerce_dequeue_script',
	__( 'Dequeue Default JS', 'gtmkit' ),
	[
		'attributes' => [
			'disabled' => $woocommerce_is_inactive,
		]
	],
	__( 'Enable this option to dequeue the default JavaScript if you plan to create your own JavaScript.', 'gtmkit' )
);
