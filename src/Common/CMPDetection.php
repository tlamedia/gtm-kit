<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

/**
 * Plugin-level consent management platform detection.
 *
 * Used to pre-select the matching CMP toggle on fresh installs so a
 * site that already runs Cookiebot, Iubenda, or CookieYes ships with
 * the right script attribute the moment GTM Kit is activated. Detection
 * is plugin-only in this release: walking the active-plugins list is
 * fast, deterministic, and covers the most common deployments. CMPs
 * loaded via themes or GTM tags are not detected; surface in support
 * tickets if that gap matters.
 */
final class CMPDetection {

	/**
	 * CMP slug → list of plugin file paths that indicate the CMP is
	 * active. Multiple entries per CMP cover historical/alternate slugs.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const PLUGIN_FILES = [
		'cookiebot' => [
			'cookiebot/cookiebot.php',
			'cookiebot-by-cybot/cookiebot.php',
		],
		'iubenda'   => [
			'iubenda-cookie-law-solution/iubenda_cookie_solution.php',
		],
		'cookieyes' => [
			'cookie-law-info/cookie-law-info.php',
		],
	];

	/**
	 * Display names for the consent platforms this class recognises.
	 *
	 * @var array<string, string>
	 */
	private const DISPLAY_NAMES = [
		'cookiebot' => 'Cookiebot',
		'iubenda'   => 'Iubenda',
		'cookieyes' => 'CookieYes',
	];

	/**
	 * Resolve a detected CMP to the name its users know it by.
	 *
	 * @param string|null $slug A slug returned by {@see self::detect_active_cmp()}.
	 *
	 * @return string The display name, or an empty string when nothing was detected.
	 */
	public static function get_display_name( ?string $slug ): string {

		if ( $slug === null || $slug === '' ) {
			return '';
		}

		return self::DISPLAY_NAMES[ $slug ] ?? $slug;
	}

	/**
	 * Detect the first matching active CMP plugin, if any.
	 *
	 * Iteration follows the canonical order Cookiebot → Iubenda →
	 * CookieYes; sites running more than one CMP plugin (rare and
	 * misconfigured) get the first match for a deterministic fallback.
	 *
	 * @return string|null One of `cookiebot`, `iubenda`, `cookieyes`, or
	 *     null when no known CMP plugin is active.
	 */
	public static function detect_active_cmp(): ?string {
		Util::load_plugin_api();

		foreach ( self::PLUGIN_FILES as $slug => $plugin_files ) {
			foreach ( $plugin_files as $plugin_file ) {
				if ( is_plugin_active( $plugin_file ) ) {
					return $slug;
				}
			}
		}

		return null;
	}
}
