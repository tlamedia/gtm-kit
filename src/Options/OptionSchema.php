<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

use TLA_Media\GTM_Kit\Common\Util;

/**
 * Option Schema - Defines validation rules, types, and defaults
 *
 * Single source of truth for all option metadata.
 */
final class OptionSchema {

	/**
	 * Script gating mode: GTM loads on every page (current behavior).
	 *
	 * @var string
	 */
	public const GATING_MODE_ALWAYS_LOAD = 'always_load';

	/**
	 * Script gating mode: GTM loads but starts in denied state via Consent Mode.
	 * Same on-page emission as always_load; future event-deferral features may
	 * change runtime behavior under this mode.
	 *
	 * @var string
	 */
	public const GATING_MODE_WEAK_BLOCK = 'weak_block';

	/**
	 * Script gating mode: GTM is masked as text/plain and only executes after
	 * consent for the required categories has been granted.
	 *
	 * @var string
	 */
	public const GATING_MODE_STRONG_BLOCK = 'strong_block';

	/**
	 * All valid values for the script gating mode option, in canonical order.
	 *
	 * @var array<int, string>
	 */
	public const GATING_MODES = [
		self::GATING_MODE_ALWAYS_LOAD,
		self::GATING_MODE_WEAK_BLOCK,
		self::GATING_MODE_STRONG_BLOCK,
	];

	/**
	 * Allowed-character class for the custom CMP attribute name. HTML5
	 * permits a wider set, but real-world CMP-blocking attributes use
	 * `data-*` patterns with this safe subset; staying restrictive avoids
	 * escaping bugs.
	 *
	 * @var string
	 */
	public const CMP_CUSTOM_NAME_PATTERN = '/[^a-zA-Z0-9_-]/';

	/**
	 * URL-exclusion pattern mode: shell-style wildcards. `*` matches any
	 * run of characters including `/`; `?` matches a single character;
	 * all other regex metacharacters are escaped.
	 *
	 * @var string
	 */
	public const URL_EXCLUSION_MODE_GLOB = 'glob';

	/**
	 * URL-exclusion pattern mode: raw PCRE regular expression. Run with a
	 * `~` delimiter and the `i` flag; bad patterns fail open (no match).
	 *
	 * @var string
	 */
	public const URL_EXCLUSION_MODE_REGEX = 'regex';

	/**
	 * Hard cap on the number of URL-exclusion patterns stored. Caps the
	 * worst-case per-request matching cost.
	 *
	 * @var int
	 */
	public const URL_EXCLUSION_MAX_PATTERNS = 100;

	/**
	 * Hard cap on the length of a single URL-exclusion pattern. Long
	 * patterns are a poor signal and amplify the ReDoS surface even with
	 * PHP's backtrack limit in place.
	 *
	 * @var int
	 */
	public const URL_EXCLUSION_MAX_PATTERN_LENGTH = 500;

	/**
	 * Default value for the cmp_script_attributes option on fresh installs
	 * with no detected CMP. Activation overrides for fresh installs where
	 * a CMP plugin is detected; the upgrade routine overrides to keep
	 * Cookiebot on for upgraders to preserve the previously hardcoded
	 * behavior.
	 *
	 * @var array<string, mixed>
	 */
	public const CMP_SCRIPT_ATTRIBUTES_DEFAULT = [
		'cookiebot' => false,
		'iubenda'   => false,
		'cookieyes' => false,
		'custom'    => [
			'name'  => '',
			'value' => '',
		],
	];

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
			'gtm_id'                      => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_CONTAINER_ID',
				'sanitize' => 'sanitize_text_field',
				'validate' => [ self::class, 'validate_gtm_id' ],
			],
			'script_implementation'       => [
				'default'  => 0,
				'type'     => 'integer',
				'validate' => [ self::class, 'validate_in_range', 0, 2 ],
			],
			'noscript_implementation'     => [
				'default'  => 0,
				'type'     => 'integer',
				'validate' => [ self::class, 'validate_in_range', 0, 2 ],
			],
			'container_active'            => [
				'default'  => true,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_CONTAINER_ACTIVE',
			],
			'excluded_url_patterns'       => [
				'default'  => [],
				'type'     => 'array',
				'sanitize' => [ self::class, 'sanitize_excluded_url_patterns' ],
				'validate' => [ self::class, 'validate_excluded_url_patterns' ],
			],
			'sgtm_domain'                 => [
				'default'  => '',
				'type'     => 'string',
				'validate' => [ self::class, 'validate_domain' ],
			],
			'console_log'                 => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_CONSOLE_LOG',
			],
			'debug_log'                   => [
				'default'  => false,
				'type'     => 'boolean',
				'constant' => 'GTMKIT_DEBUG_LOG',
			],
			'gtm_auth'                    => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_AUTH',
			],
			'gtm_preview'                 => [
				'default'  => '',
				'type'     => 'string',
				'constant' => 'GTMKIT_PREVIEW',
			],
			'datalayer_page_type'         => [
				'default' => true,
				'type'    => 'boolean',
			],
			'exclude_user_roles'          => [
				'default' => [],
				'type'    => 'array',
			],

			// Google Consent Mode v2 defaults.
			'gcm_default_settings'        => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_ad_personalization'      => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_ad_storage'              => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_ad_user_data'            => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_analytics_storage'       => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_personalization_storage' => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_functionality_storage'   => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_security_storage'        => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_ads_data_redaction'      => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_url_passthrough'         => [
				'default' => false,
				'type'    => 'boolean',
			],
			'gcm_wait_for_update'         => [
				'default'  => 500,
				'type'     => 'integer',
				'validate' => [ self::class, 'validate_in_range', 0, 30000 ],
			],
			'gcm_region'                  => [
				'default'  => [],
				'type'     => 'array',
				'sanitize' => [ self::class, 'sanitize_region_codes' ],
				'validate' => [ self::class, 'validate_region_codes' ],
			],

			// Script gating mode (always_load | weak_block | strong_block).
			// Default 'always_load' preserves the pre-2.10 emission for every existing install.
			'consent_gating_mode'         => [
				'default'  => self::GATING_MODE_ALWAYS_LOAD,
				'type'     => 'string',
				'validate' => [ self::class, 'validate_enum', self::GATING_MODES ],
			],

			// CMP script attribute support.
			// Default has all named-CMP toggles off and an empty custom slot.
			// Activation pre-selects a detected CMP for fresh installs; the
			// upgrade routine seeds Cookiebot=true for upgraders to preserve
			// the previously hardcoded behavior.
			'cmp_script_attributes'       => [
				'default'  => self::CMP_SCRIPT_ATTRIBUTES_DEFAULT,
				'type'     => 'array',
				'sanitize' => [ self::class, 'sanitize_cmp_script_attributes' ],
				'validate' => [ self::class, 'validate_cmp_script_attributes' ],
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
			'woocommerce_exclude_tax'               => [
				'default' => false,
				'type'    => 'boolean',
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
		Util::load_plugin_api();

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

	/**
	 * Validate value is one of an allowed set.
	 *
	 * @param mixed         $value          Value to validate.
	 * @param array<string> $allowed_values Allowed values for the option.
	 * @return bool
	 */
	public static function validate_enum( $value, array $allowed_values ): bool {
		return in_array( $value, $allowed_values, true );
	}

	/**
	 * Regular expression for Consent Mode v2 region codes.
	 *
	 * ISO 3166-1 alpha-2 with optional ISO 3166-2 subdivision, per Google's
	 * spec. Examples: `DK`, `DE-BY`, `US-CA`.
	 */
	public const REGION_CODE_PATTERN = '/^[A-Z]{2}(-[A-Z0-9]{1,3})?$/';

	/**
	 * Sanitize an array of region codes.
	 *
	 * Trims, uppercases, and drops any entry that is not a valid
	 * ISO 3166-1 alpha-2 code with an optional ISO 3166-2 subdivision.
	 *
	 * @param mixed $value Raw value from the request.
	 * @return array<int, string> Sanitized, de-duplicated, zero-indexed list.
	 */
	public static function sanitize_region_codes( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $value as $code ) {
			if ( ! is_string( $code ) ) {
				continue;
			}
			$code = strtoupper( trim( $code ) );
			if ( $code === '' ) {
				continue;
			}
			if ( preg_match( self::REGION_CODE_PATTERN, $code ) !== 1 ) {
				continue;
			}
			$sanitized[] = $code;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Validate an array of region codes.
	 *
	 * Accepts any array; invalid entries are dropped by
	 * {@see self::sanitize_region_codes()}. Rejects non-array values so the
	 * save pipeline fails fast rather than silently storing garbage.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function validate_region_codes( $value ): bool {
		return is_array( $value );
	}

	/**
	 * Sanitize the cmp_script_attributes value to the canonical structure.
	 *
	 * Normalizes the toggles to bools, the custom slot to an array with
	 * `name` and `value` strings, and strips disallowed characters from
	 * the custom name so a malformed payload from the admin REST endpoint
	 * cannot leak unsafe attributes into the rendered <script> tag.
	 *
	 * @param mixed $value Raw value from the request.
	 * @return array<string, mixed>
	 */
	public static function sanitize_cmp_script_attributes( $value ): array {
		$defaults = self::CMP_SCRIPT_ATTRIBUTES_DEFAULT;
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$custom_input = isset( $value['custom'] ) && is_array( $value['custom'] ) ? $value['custom'] : [];
		$custom_name  = isset( $custom_input['name'] ) ? (string) $custom_input['name'] : '';
		$custom_name  = (string) preg_replace( self::CMP_CUSTOM_NAME_PATTERN, '', $custom_name );

		return [
			'cookiebot' => ! empty( $value['cookiebot'] ),
			'iubenda'   => ! empty( $value['iubenda'] ),
			'cookieyes' => ! empty( $value['cookieyes'] ),
			'custom'    => [
				'name'  => $custom_name,
				'value' => isset( $custom_input['value'] ) ? (string) $custom_input['value'] : '',
			],
		];
	}

	/**
	 * Validate the cmp_script_attributes value.
	 *
	 * Accepts any array; structure is normalized by
	 * {@see self::sanitize_cmp_script_attributes()}. Rejects non-array
	 * values so the save pipeline fails fast.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function validate_cmp_script_attributes( $value ): bool {
		return is_array( $value );
	}

	/**
	 * Sanitize the excluded_url_patterns option to a list of
	 * `{pattern, mode}` records.
	 *
	 * Drops entries with an empty pattern, trims each pattern, coerces
	 * the mode to `glob` unless explicitly `regex`, and caps both the
	 * list length and per-pattern length to bound the worst-case match
	 * cost per request.
	 *
	 * @param mixed $value Raw value from the request.
	 * @return array<int, array{pattern: string, mode: string}>
	 */
	public static function sanitize_excluded_url_patterns( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$pattern = isset( $entry['pattern'] ) ? (string) $entry['pattern'] : '';
			$pattern = trim( $pattern );

			$mode = isset( $entry['mode'] ) ? (string) $entry['mode'] : self::URL_EXCLUSION_MODE_GLOB;
			$mode = $mode === self::URL_EXCLUSION_MODE_REGEX
				? self::URL_EXCLUSION_MODE_REGEX
				: self::URL_EXCLUSION_MODE_GLOB;

			// Glob patterns pasted as a full URL get reduced to the path
			// the matcher actually sees at runtime, so the admin does not
			// have to know that hostname / scheme are stripped server-side.
			// Regex patterns are left alone because `://` is legitimate
			// regex syntax inside them.
			if ( $mode === self::URL_EXCLUSION_MODE_GLOB ) {
				$pattern = self::extract_path_from_url_pattern( $pattern );
			}

			if ( $pattern === '' ) {
				continue;
			}

			if ( strlen( $pattern ) > self::URL_EXCLUSION_MAX_PATTERN_LENGTH ) {
				$pattern = substr( $pattern, 0, self::URL_EXCLUSION_MAX_PATTERN_LENGTH );
			}

			$sanitized[] = [
				'pattern' => $pattern,
				'mode'    => $mode,
			];

			if ( count( $sanitized ) >= self::URL_EXCLUSION_MAX_PATTERNS ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Reduce a glob pattern pasted as a full URL to just its path.
	 *
	 * `https://example.test/foo/*`, `http://example.test/foo/*`, and
	 * `//example.test/foo/*` all collapse to `/foo/*`. Inputs that do
	 * not carry a host (`/foo/*`, `foo/*`, `*`) are returned unchanged.
	 *
	 * @param string $pattern Raw glob pattern as entered by the admin.
	 * @return string
	 */
	private static function extract_path_from_url_pattern( string $pattern ): string {
		if ( $pattern === '' ) {
			return '';
		}

		if (
			stripos( $pattern, 'http://' ) !== 0
			&& stripos( $pattern, 'https://' ) !== 0
			&& strpos( $pattern, '//' ) !== 0
		) {
			return $pattern;
		}

		$parts = \wp_parse_url( $pattern );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $pattern;
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		return $path === '' ? '/' : $path;
	}

	/**
	 * Validate the excluded_url_patterns option.
	 *
	 * Rejects non-array values so the save pipeline fails fast. Rejects
	 * regex entries whose pattern cannot be compiled, surfacing the
	 * problem to the admin rather than silently dropping the row.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function validate_excluded_url_patterns( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$pattern = isset( $entry['pattern'] ) ? trim( (string) $entry['pattern'] ) : '';
			if ( $pattern === '' ) {
				continue;
			}

			$mode = isset( $entry['mode'] ) ? (string) $entry['mode'] : '';
			if ( $mode !== self::URL_EXCLUSION_MODE_REGEX ) {
				continue;
			}

			if ( ! self::is_valid_regex_pattern( $pattern ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check that a regex pattern compiles under the runtime delimiter
	 * and flags used by the URL-exclusion matcher.
	 *
	 * Suppresses the compile warning because the goal here is to detect
	 * a bad pattern at save time, not to log it.
	 *
	 * @param string $pattern Raw pattern as entered by the admin.
	 * @return bool True when the pattern compiles cleanly.
	 */
	public static function is_valid_regex_pattern( string $pattern ): bool {
		$delimited = '~' . str_replace( '~', '\~', $pattern ) . '~i';

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- preg_match emits a warning on compile failure; we surface the result via the return value instead.
		$result = @preg_match( $delimited, '' );

		return $result !== false;
	}
}
