<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

/**
 * Option Schema - Defines validation rules, types, and defaults
 *
 * Single source of truth for all option metadata.
 */
final class OptionSchema {

	/**
	 * Get schema for all options
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_schema(): array {
		return [
			'general'      => self::get_general_schema(),
			'integrations' => self::get_integrations_schema(),
			'premium'      => self::get_premium_schema(),
			'misc'         => self::get_misc_schema(),
		];
	}

	/**
	 * Get schema for a specific option
	 *
	 * @param string $group Option group.
	 * @param string $key Option key.
	 * @return array<string, mixed>|null
	 */
	public static function get_option_schema( string $group, string $key ): ?array {
		$schema = self::get_schema();
		return $schema[ $group ][ $key ] ?? null;
	}

	/**
	 * General options schema
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_general_schema(): array {
		return [
			'gtm_id'                  => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_CONTAINER_ID',
				'sanitize' => 'sanitize_text_field',
				'validate' => [ self::class, 'validate_gtm_id' ],
			],
			'script_implementation'   => [
				'default'  => 0,
				'type'     => 'integer',
				'validate' => [ self::class, 'validate_in_range', 0, 2 ],
			],
			'noscript_implementation' => [
				'default'  => 0,
				'type'     => 'integer',
				'validate' => [ self::class, 'validate_in_range', 0, 2 ],
			],
			'container_active'        => [
				'default'  => true,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_CONTAINER_ACTIVE',
			],
			'sgtm_domain'             => [
				'default'  => '',
				'type'     => 'string',
				'validate' => [ self::class, 'validate_domain' ],
			],
			'console_log'             => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_CONSOLE_LOG',
			],
			'debug_log'               => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_DEBUG_LOG',
			],
			'gtm_auth'                => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_AUTH',
			],
			'gtm_preview'             => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_PREVIEW',
			],
			'datalayer_page_type'     => [
				'default' => true,
				'type'    => 'boolean',
			],
			'exclude_user_roles'      => [
				'default' => [],
				'type'    => 'array',
			],
		];
	}

	/**
	 * Integration options schema
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_integrations_schema(): array {
		$schema = [
			'woocommerce_shipping_info'             => [
				'default' => 1,
				'type'    => 'integer',
			],
			'woocommerce_payment_info'              => [
				'default' => 1,
				'type'    => 'integer',
			],
			'woocommerce_variable_product_tracking' => [
				'default' => 0,
				'type'    => 'integer',
			],
			'woocommerce_view_item_list_limit'      => [
				'default' => 0,
				'type'    => 'integer',
			],
			'woocommerce_debug_track_purchase'      => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_WC_DEBUG_TRACK_PURCHASE',
			],
			'cf7_load_js'                           => [
				'default' => 1,
				'type'    => 'integer',
			],
			'edd_debug_track_purchase'              => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_EDD_DEBUG_TRACK_PURCHASE',
			],
		];

		// Dynamic options based on active plugins.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( \is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$schema['woocommerce_integration'] = [
				'default' => true,
				'type'    => 'boolean',
			];
		}

		if ( \is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
			$schema['cf7_integration'] = [
				'default' => true,
				'type'    => 'boolean',
			];
		}

		if ( \is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || \is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ) {
			$schema['edd_integration'] = [
				'default' => true,
				'type'    => 'boolean',
			];
		}

		return $schema;
	}

	/**
	 * Premium options schema
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_premium_schema(): array {
		return [
			'addon_installed' => [
				'default' => false,
				'type'    => 'boolean',
			],
		];
	}

	/**
	 * Misc options schema
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function get_misc_schema(): array {
		return [
			'auto_update' => [
				'default' => true,
				'type'    => 'boolean',
			],
		];
	}

	// === Validation Methods ===

	/**
	 * Validate GTM ID format
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function validate_gtm_id( $value ): bool {
		return empty( $value ) || preg_match( '/^GTM-[A-Z0-9]{4,}$/', $value ) === 1;
	}

	/**
	 * Validate domain format
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function validate_domain( $value ): bool {
		if ( empty( $value ) ) {
			return true;
		}

		// If it starts with http/https, it should be a valid URL.
		if ( str_starts_with( $value, 'http://' ) || str_starts_with( $value, 'https://' ) ) {
			return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
		}

		// Otherwise, validate as domain.
		return filter_var( 'https://' . $value, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate value is in range
	 *
	 * @param mixed $value Value to validate.
	 * @param int   $min Minimum value.
	 * @param int   $max Maximum value.
	 * @return bool
	 */
	public static function validate_in_range( $value, int $min, int $max ): bool {
		return is_numeric( $value ) && $value >= $min && $value <= $max;
	}
}
