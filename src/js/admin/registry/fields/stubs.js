/**
 * Upsell stub manifest.
 *
 * Core ships a stub for every add-on field a user can be upsold: label,
 * section, order, tier and control shape only, with `stub: true` and no
 * behaviour. When the add-on is installed it registers the real field at
 * runtime and that supersedes the stub by `key` (see registry/assemble.js).
 * When the add-on is absent the stub renders greyed with its tier badge and the
 * shell's remote upsell slot.
 *
 * Stubs carry no marketing copy; labels name the feature neutrally and the
 * "unlock" pitch is served remotely, never bundled. This manifest mirrors the
 * add-on field schema and is intentionally diffable so a change to the upsell
 * surface shows up in review.
 */
import { __ } from '@wordpress/i18n';

const WOO_INTEGRATION = {
	plugin: 'woocommerce',
	option: 'woocommerce_integration',
};

const stub = ( key, section, order, control, label, extra = {} ) => ( {
	key: `premium.${ key }`,
	capability: 'commerce',
	section,
	order,
	control,
	label,
	tier: 'woo',
	integration: 'woocommerce',
	requiresIntegration: WOO_INTEGRATION,
	stub: true,
	...extra,
} );

// WooCommerce Subscriptions tracking is Premium-only (not part of the Woo
// add-on), so its stubs upsell the Premium tier rather than the Woo add-on.
const SUBSCRIPTION_STUB = { requiresPlugin: 'subscriptions', tier: 'premium' };

export const STUB_FIELDS = [
	// Server-side webhooks.
	stub(
		'woocommerce_webhooks',
		'woo-webhooks',
		10,
		'toggle',
		__( 'Send webhooks to server GTM container', 'gtm-kit' )
	),
	stub(
		'woocommerce_webhook_queue',
		'woo-webhooks',
		20,
		'toggle',
		__( 'Send webhooks from a background queue', 'gtm-kit' )
	),
	stub(
		'woocommerce_purchase_webhook',
		'woo-webhooks',
		30,
		'toggle',
		__( 'Send the purchase event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_purchase_webhook_trigger',
		'woo-webhooks',
		40,
		'radio',
		__( 'Purchase webhook trigger', 'gtm-kit' ),
		{
			valueType: 'integer',
			options: [
				{
					label: __( 'When an order is created.', 'gtm-kit' ),
					value: 0,
				},
				{
					label: __(
						'When the order is paid and status is Processing.',
						'gtm-kit'
					),
					value: 1,
				},
			],
		}
	),
	stub(
		'woocommerce_order_paid_webhook',
		'woo-webhooks',
		50,
		'toggle',
		__( 'Send the order_paid event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_refund_webhook',
		'woo-webhooks',
		60,
		'toggle',
		__( 'Send the refund event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_order_processing_webhook',
		'woo-webhooks',
		62,
		'toggle',
		__( 'Send the order_processing event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_order_completed_webhook',
		'woo-webhooks',
		64,
		'toggle',
		__( 'Send the order_completed event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_order_refunded_webhook',
		'woo-webhooks',
		66,
		'toggle',
		__( 'Send the order_refunded event server-side', 'gtm-kit' )
	),
	stub(
		'woocommerce_subscription_started_webhook',
		'woo-webhooks',
		70,
		'toggle',
		__( 'Send the subscription_started event server-side', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),
	stub(
		'woocommerce_subscription_renewed_webhook',
		'woo-webhooks',
		80,
		'toggle',
		__( 'Send the subscription_renewed event server-side', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),
	stub(
		'woocommerce_subscription_cancelled_webhook',
		'woo-webhooks',
		90,
		'toggle',
		__( 'Send the subscription_cancelled event server-side', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),
	stub(
		'woocommerce_subscription_expired_webhook',
		'woo-webhooks',
		100,
		'toggle',
		__( 'Send the subscription_expired event server-side', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),
	stub(
		'woocommerce_subscription_reactivated_webhook',
		'woo-webhooks',
		110,
		'toggle',
		__( 'Send the subscription_reactivated event server-side', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),

	// Server-side consent gating is Premium-only (not part of the Woo
	// add-on), so these stubs upsell the Premium tier.
	stub(
		'woocommerce_webhook_consent_mode',
		'woo-webhooks',
		120,
		'radio',
		__( 'Consent gating of webhooks', 'gtm-kit' ),
		{
			tier: 'premium',
			valueType: 'string',
			defaultValue: 'off',
			options: [
				{
					label: __(
						'Off: send webhooks regardless of the consent given at checkout.',
						'gtm-kit'
					),
					value: 'off',
				},
				{
					label: __(
						'Suppress: strip identifiers the customer did not consent to, and do not send the webhook at all when both marketing and analytics consent were denied.',
						'gtm-kit'
					),
					value: 'suppress',
				},
				{
					label: __(
						'Anonymize: strip identifiers the customer did not consent to, but always send the webhook so aggregate conversion data is preserved.',
						'gtm-kit'
					),
					value: 'anonymize',
				},
			],
		}
	),
	stub(
		'woocommerce_webhook_consent_unknown',
		'woo-webhooks',
		130,
		'radio',
		__( 'Unknown consent', 'gtm-kit' ),
		{
			tier: 'premium',
			valueType: 'string',
			defaultValue: 'allow',
			options: [
				{
					label: __(
						'Treat unknown consent as granted and send the webhook unchanged.',
						'gtm-kit'
					),
					value: 'allow',
				},
				{
					label: __( 'Treat unknown consent as denied.', 'gtm-kit' ),
					value: 'deny',
				},
			],
		}
	),

	// Frontend event suppression (rely on the server-side webhook instead).
	stub(
		'woocommerce_disable_frontend_purchase_event',
		'woo-events',
		60,
		'toggle',
		__( 'Disable the frontend purchase event', 'gtm-kit' )
	),
	stub(
		'woocommerce_disable_frontend_subscription_started',
		'woo-events',
		70,
		'toggle',
		__( 'Disable the frontend subscription_started event', 'gtm-kit' ),
		SUBSCRIPTION_STUB
	),

	// Custom CSS selectors.
	stub(
		'woocommerce_single_product_add_to_wishlist',
		'woo-css-selectors',
		10,
		'text',
		__( 'Single Product (add_to_wishlist)', 'gtm-kit' )
	),
	stub(
		'woocommerce_product_list_item_selector',
		'woo-css-selectors',
		20,
		'text',
		__( 'Product List (select_item)', 'gtm-kit' )
	),
	stub(
		'woocommerce_product_list_add_to_wishlist',
		'woo-css-selectors',
		30,
		'text',
		__( 'Product List (add_to_wishlist)', 'gtm-kit' )
	),

	// Consent: hold ecommerce events until consent is granted.
	{
		key: 'premium.event_deferral_queue',
		capability: 'consent',
		section: 'event-deferral',
		order: 10,
		control: 'toggle',
		label: __( 'Defer events until consent is granted', 'gtm-kit' ),
		tier: 'premium',
		integration: null,
		stub: true,
	},

	// Tools: frontend event inspector.
	{
		key: 'general.event_inspector',
		capability: 'tools',
		section: 'logging',
		order: 100,
		control: 'toggle',
		label: __( 'Event Inspector', 'gtm-kit' ),
		tier: 'woo',
		integration: null,
		stub: true,
	},
];

export default STUB_FIELDS;
