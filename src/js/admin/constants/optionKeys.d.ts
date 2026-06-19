/**
 * TypeScript definitions for option key constants
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

export declare const OPTION_GROUPS: {
	readonly GENERAL: 'general';
	readonly INTEGRATIONS: 'integrations';
	readonly PREMIUM: 'premium';
};

export declare const GENERAL_OPTIONS: {
	readonly GTM_ID: 'gtm_id';
	readonly CONTAINER_ACTIVE: 'container_active';
	readonly SGTM_DOMAIN: 'sgtm_domain';
	readonly SGTM_CONTAINER_IDENTIFIER: 'sgtm_container_identifier';
	readonly SGTM_COOKIE_KEEPER: 'sgtm_cookie_keeper';
	readonly DATALAYER_NAME: 'datalayer_name';
	readonly ANALYTICS_ACTIVE: 'analytics_active';
	readonly JUST_THE_CONTAINER: 'just_the_container';
	readonly LOAD_JS_EVENT: 'load_js_event';
	readonly GTM_AUTH: 'gtm_auth';
	readonly GTM_PREVIEW: 'gtm_preview';
	readonly EXCLUDE_USER_ROLES: 'exclude_user_roles';
	readonly SCRIPT_IMPLEMENTATION: 'script_implementation';
	readonly NOSCRIPT_IMPLEMENTATION: 'noscript_implementation';
};

export declare const INTEGRATION_OPTIONS: {
	readonly WOOCOMMERCE_INTEGRATION: 'woocommerce_integration';
	readonly WOOCOMMERCE_BRAND: 'woocommerce_brand';
	readonly WOOCOMMERCE_USE_SKU: 'woocommerce_use_sku';
	readonly WOOCOMMERCE_EXCLUDE_TAX: 'woocommerce_exclude_tax';
	readonly WOOCOMMERCE_EXCLUDE_SHIPPING: 'woocommerce_exclude_shipping';
	readonly CF7_INTEGRATION: 'cf7_integration';
	readonly EDD_INTEGRATION: 'edd_integration';
};

export declare const PREMIUM_OPTIONS: {
	readonly WOOCOMMERCE_WEBHOOKS: 'woocommerce_webhooks';
	readonly WOOCOMMERCE_PURCHASE_WEBHOOK: 'woocommerce_purchase_webhook';
	readonly WOOCOMMERCE_ORDER_PAID_WEBHOOK: 'woocommerce_order_paid_webhook';
	readonly WOOCOMMERCE_REFUND_WEBHOOK: 'woocommerce_refund_webhook';
};

export type OptionGroup = typeof OPTION_GROUPS[keyof typeof OPTION_GROUPS];
export type GeneralOptionKey = typeof GENERAL_OPTIONS[keyof typeof GENERAL_OPTIONS];
export type IntegrationOptionKey = typeof INTEGRATION_OPTIONS[keyof typeof INTEGRATION_OPTIONS];
export type PremiumOptionKey = typeof PREMIUM_OPTIONS[keyof typeof PREMIUM_OPTIONS];
export type OptionKey = GeneralOptionKey | IntegrationOptionKey | PremiumOptionKey;
