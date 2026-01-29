<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

/**
 * Option Keys - Centralized definition of all GTM Kit options
 *
 * Replaces magic strings throughout the codebase.
 * Provides IDE autocomplete and refactoring support.
 */
final class OptionKeys {

	// General Options.
	public const GENERAL_GTM_ID                  = 'general.gtm_id';
	public const GENERAL_SCRIPT_IMPLEMENTATION   = 'general.script_implementation';
	public const GENERAL_NOSCRIPT_IMPLEMENTATION = 'general.noscript_implementation';
	public const GENERAL_CONTAINER_ACTIVE        = 'general.container_active';
	public const GENERAL_SGTM_DOMAIN             = 'general.sgtm_domain';
	public const GENERAL_CONSOLE_LOG             = 'general.console_log';
	public const GENERAL_DEBUG_LOG               = 'general.debug_log';
	public const GENERAL_GTM_AUTH                = 'general.gtm_auth';
	public const GENERAL_GTM_PREVIEW             = 'general.gtm_preview';
	public const GENERAL_DATALAYER_PAGE_TYPE     = 'general.datalayer_page_type';
	public const GENERAL_EXCLUDE_USER_ROLES      = 'general.exclude_user_roles';

	// Integration Options.
	public const INTEGRATIONS_WOOCOMMERCE_INTEGRATION               = 'integrations.woocommerce_integration';
	public const INTEGRATIONS_WOOCOMMERCE_SHIPPING_INFO             = 'integrations.woocommerce_shipping_info';
	public const INTEGRATIONS_WOOCOMMERCE_PAYMENT_INFO              = 'integrations.woocommerce_payment_info';
	public const INTEGRATIONS_WOOCOMMERCE_VARIABLE_PRODUCT_TRACKING = 'integrations.woocommerce_variable_product_tracking';
	public const INTEGRATIONS_WOOCOMMERCE_VIEW_ITEM_LIST_LIMIT      = 'integrations.woocommerce_view_item_list_limit';
	public const INTEGRATIONS_WOOCOMMERCE_DEBUG_TRACK_PURCHASE      = 'integrations.woocommerce_debug_track_purchase';
	public const INTEGRATIONS_CF7_LOAD_JS                           = 'integrations.cf7_load_js';
	public const INTEGRATIONS_CF7_INTEGRATION                       = 'integrations.cf7_integration';
	public const INTEGRATIONS_EDD_DEBUG_TRACK_PURCHASE              = 'integrations.edd_debug_track_purchase';
	public const INTEGRATIONS_EDD_INTEGRATION                       = 'integrations.edd_integration';

	// Premium Options.
	public const PREMIUM_ADDON_INSTALLED     = 'premium.addon_installed';
	public const PREMIUM_PREMIUM_INSTALLED   = 'premium.premium_installed';
	public const PREMIUM_WOO_ADDON_INSTALLED = 'premium.woo_addon_installed';

	// Misc Options.
	public const MISC_AUTO_UPDATE = 'misc.auto_update';

	/**
	 * Parse option key into group and key parts
	 *
	 * @param string $option_key Full option key (e.g., 'general.gtm_id').
	 * @return array{group: string, key: string}
	 */
	public static function parse( string $option_key ): array {
		$parts = explode( '.', $option_key, 2 );
		return [
			'group' => $parts[0],
			'key'   => $parts[1] ?? '',
		];
	}

	/**
	 * Get all option keys
	 *
	 * @return array<string>
	 */
	public static function get_all(): array {
		$reflection = new \ReflectionClass( self::class );
		return array_values( $reflection->getConstants() );
	}

	/**
	 * Check if an option key exists
	 *
	 * @param string $option_key Full option key to check.
	 * @return bool
	 */
	public static function exists( string $option_key ): bool {
		return in_array( $option_key, self::get_all(), true );
	}
}
