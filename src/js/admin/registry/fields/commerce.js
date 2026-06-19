/**
 * Commerce capability field schema.
 *
 * Free WooCommerce and Easy Digital Downloads controls (the `integrations.*`
 * keys). Premium webhook, custom-CSS-selector, and disable-frontend controls
 * are intentionally deferred to runtime registration.
 */
import { __ } from '@wordpress/i18n';

const WOO_INTEGRATION = {
	plugin: 'woocommerce',
	option: 'woocommerce_integration',
};
const EDD_INTEGRATION = { plugin: 'edd', option: 'edd_integration' };

const wooToggle = ( key, section, order, label, description ) => ( {
	key: `integrations.${ key }`,
	capability: 'commerce',
	section,
	order,
	control: 'toggle',
	label,
	description,
	tier: 'free',
	integration: 'woocommerce',
	requiresIntegration: WOO_INTEGRATION,
} );

const eddToggle = ( key, section, order, label, description ) => ( {
	key: `integrations.${ key }`,
	capability: 'commerce',
	section,
	order,
	control: 'toggle',
	label,
	description,
	tier: 'free',
	integration: 'edd',
	requiresIntegration: EDD_INTEGRATION,
} );

export const COMMERCE_FIELDS = [
	// WooCommerce activation.
	{
		key: 'integrations.woocommerce_integration',
		capability: 'commerce',
		section: 'woocommerce',
		order: 10,
		control: 'toggle',
		label: __( 'Track WooCommerce', 'gtm-kit' ),
		description: __( 'Enable the WooCommerce integration.', 'gtm-kit' ),
		tier: 'free',
		integration: 'woocommerce',
		requiresPlugin: 'woocommerce',
	},

	// WooCommerce basic.
	{
		key: 'integrations.woocommerce_brand',
		capability: 'commerce',
		section: 'woo-basic',
		order: 10,
		control: 'select',
		label: __( 'Brand', 'gtm-kit' ),
		optionsSource: 'taxonomyOptions',
		notSet: true,
		help: __(
			'Select the taxonomy that is used for product brands',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	wooToggle(
		'woocommerce_use_sku',
		'woo-basic',
		20,
		__( 'Use SKU instead of ID', 'gtm-kit' ),
		__(
			'Use SKU instead of the product ID with fallback to ID if no SKU is set.',
			'gtm-kit'
		)
	),
	wooToggle(
		'woocommerce_exclude_tax',
		'woo-basic',
		30,
		__( 'Exclude tax', 'gtm-kit' ),
		__( 'Exclude tax from prices and revenue', 'gtm-kit' )
	),
	wooToggle(
		'woocommerce_exclude_shipping',
		'woo-basic',
		40,
		__( 'Exclude shipping from revenue', 'gtm-kit' ),
		__( 'Exclude shipping from revenue', 'gtm-kit' )
	),

	// WooCommerce user data.
	wooToggle(
		'woocommerce_include_customer_data',
		'woo-user-data',
		10,
		__( 'Include customer data', 'gtm-kit' ),
		__(
			'Enable this option to include customer data in the data layer on the "purchase" event.',
			'gtm-kit'
		)
	),

	// WooCommerce event customization.
	{
		key: 'integrations.woocommerce_view_item_list_limit',
		capability: 'commerce',
		section: 'woo-events',
		order: 10,
		control: 'radio',
		label: __( 'view_item_list (with product filter)', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					'Push view_item_list when the list is updated using a product filter.',
					'gtm-kit'
				),
				value: 0,
			},
			{
				label: __(
					'Only push view_item_list once per page for each list.',
					'gtm-kit'
				),
				value: 1,
			},
		],
		help: __(
			'Do you want to push the view_item_list event if the list is updated using a filter or just once per page view?',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	{
		key: 'integrations.woocommerce_variable_product_tracking',
		capability: 'commerce',
		section: 'woo-events',
		order: 20,
		control: 'radio',
		label: __( 'view_item (variable product)', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					'Only push view_item on the master product',
					'gtm-kit'
				),
				value: 0,
			},
			{
				label: __(
					'Push view_item on master and variation products (higher number of views).',
					'gtm-kit'
				),
				value: 1,
			},
			{
				label: __(
					'Only push view_item on variation products.',
					'gtm-kit'
				),
				value: 2,
			},
		],
		help: __(
			'When do you want to fire the "view_item" event on variable products?',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	{
		key: 'integrations.woocommerce_shipping_info',
		capability: 'commerce',
		section: 'woo-events',
		order: 30,
		control: 'radio',
		label: __( 'add_shipping_info', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					"When the 'Place order' button is clicked",
					'gtm-kit'
				),
				value: 1,
			},
			{
				label: __(
					"When a shipment method is selected with fallback to the 'Place order' button.",
					'gtm-kit'
				),
				value: 2,
			},
			{
				label: __(
					"Disable the 'add_shipping_info' event.",
					'gtm-kit'
				),
				value: 0,
			},
		],
		help: __(
			'When do you want to fire the "add_shipping_info" event?',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	{
		key: 'integrations.woocommerce_payment_info',
		capability: 'commerce',
		section: 'woo-events',
		order: 40,
		control: 'radio',
		label: __( 'add_payment_info', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					"When the 'Place order' button is clicked",
					'gtm-kit'
				),
				value: 1,
			},
			{
				label: __(
					"When a payment method is selected with fallback to the 'Place order' button.",
					'gtm-kit'
				),
				value: 2,
			},
			{
				label: __( "Disable the 'add_payment_info' event.", 'gtm-kit' ),
				value: 0,
			},
		],
		help: __(
			'When do you want to fire the "add_payment_info" event?',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},

	// WooCommerce Google Ads.
	{
		key: 'integrations.woocommerce_google_business_vertical',
		capability: 'commerce',
		section: 'woo-google-ads',
		order: 10,
		control: 'select',
		label: __( 'Google Business Vertical', 'gtm-kit' ),
		optionsSource: 'googleBusinessVerticals',
		notSet: true,
		help: __(
			'In order to use Google Ads Remarketing you must select your business type (vertical).',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	{
		key: 'integrations.woocommerce_product_id_prefix',
		capability: 'commerce',
		section: 'woo-google-ads',
		order: 20,
		control: 'text',
		label: __( 'Product ID prefix', 'gtm-kit' ),
		placeholder: __( 'Enter prefix', 'gtm-kit' ),
		help: __(
			'If your product feed generator is adding a prefix to the product IDs, you can add the prefix here to include it in the Data Layer.',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},

	// WooCommerce advanced.
	wooToggle(
		'woocommerce_custom_order_received_page_enabled',
		'woo-advanced',
		10,
		__( 'Custom Order Received Page', 'gtm-kit' ),
		__( 'Enable custom order received (thank you) page', 'gtm-kit' )
	),
	{
		key: 'integrations.woocommerce_custom_order_received_page',
		capability: 'commerce',
		section: 'woo-advanced',
		order: 20,
		control: 'page-select',
		label: __( 'Select Page', 'gtm-kit' ),
		optionsSource: 'pageOptions',
		notSet: true,
		help: __(
			'Select a custom page to use as the order received (thank you) page',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
		visibleWhen: {
			truthy: [
				'integrations.woocommerce_custom_order_received_page_enabled',
			],
		},
	},
	wooToggle(
		'woocommerce_dequeue_script',
		'woo-advanced',
		30,
		__( 'Dequeue the default JavaScript', 'gtm-kit' ),
		__(
			'Enable this option to dequeue the default JavaScript if you plan to create your own JavaScript.',
			'gtm-kit'
		)
	),
	// WooCommerce page data — lives under Events & data layer, since these add
	// WooCommerce URL/path information to the data layer.
	{
		key: 'integrations.woocommerce_include_permalink_structure',
		capability: 'events',
		section: 'woo-page-data',
		order: 10,
		control: 'toggle',
		label: __( 'Include WooCommerce permalink structure', 'gtm-kit' ),
		description: __(
			'Enable this option to include the permalink structure of the product base, category base, tag base and attribute base.',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},
	{
		key: 'integrations.woocommerce_include_pages',
		capability: 'events',
		section: 'woo-page-data',
		order: 20,
		control: 'toggle',
		label: __( 'Include path of WooCommerce pages', 'gtm-kit' ),
		description: __(
			'Enable this option to include the path of cart, checkout, order received and my account page.',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'woocommerce',
		requiresIntegration: WOO_INTEGRATION,
	},

	// EDD activation.
	{
		key: 'integrations.edd_integration',
		capability: 'commerce',
		section: 'edd',
		order: 10,
		control: 'toggle',
		label: __( 'Track Easy Digital Downloads', 'gtm-kit' ),
		description: __(
			'Enable the Easy Digital Downloads integration.',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'edd',
		requiresPlugin: 'edd',
	},

	// EDD basic.
	eddToggle(
		'edd_use_sku',
		'edd-basic',
		10,
		__( 'Use SKU instead of ID', 'gtm-kit' ),
		__(
			'Use SKU instead of the product ID with fallback to ID if no SKU is set.',
			'gtm-kit'
		)
	),
	eddToggle(
		'edd_exclude_tax',
		'edd-basic',
		20,
		__( 'Exclude tax', 'gtm-kit' ),
		__( 'Exclude tax from prices and revenue', 'gtm-kit' )
	),
	eddToggle(
		'edd_include_customer_data',
		'edd-basic',
		30,
		__( 'Include customer data', 'gtm-kit' ),
		__(
			'Enable this option to include customer data in the data layer on the "purchase" event.',
			'gtm-kit'
		)
	),

	// EDD Google Ads.
	{
		key: 'integrations.edd_google_business_vertical',
		capability: 'commerce',
		section: 'edd-google-ads',
		order: 10,
		control: 'select',
		label: __( 'Google Business Vertical', 'gtm-kit' ),
		optionsSource: 'googleBusinessVerticals',
		notSet: true,
		help: __(
			'In order to use Google Ads Remarketing you must select your business type (vertical).',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'edd',
		requiresIntegration: EDD_INTEGRATION,
	},
	{
		key: 'integrations.edd_product_id_prefix',
		capability: 'commerce',
		section: 'edd-google-ads',
		order: 20,
		control: 'text',
		label: __( 'Product ID prefix', 'gtm-kit' ),
		placeholder: __( 'Enter prefix', 'gtm-kit' ),
		help: __(
			'If your product feed generator is adding a prefix to the product IDs, you can add the prefix here to include it in the Data Layer.',
			'gtm-kit'
		),
		tier: 'free',
		integration: 'edd',
		requiresIntegration: EDD_INTEGRATION,
	},

	// EDD advanced.
	eddToggle(
		'edd_dequeue_script',
		'edd-advanced',
		10,
		__( 'Dequeue the default JavaScript', 'gtm-kit' ),
		__(
			'Enable this option to dequeue the default JavaScript if you plan to create your own JavaScript.',
			'gtm-kit'
		)
	),
];

export default COMMERCE_FIELDS;
