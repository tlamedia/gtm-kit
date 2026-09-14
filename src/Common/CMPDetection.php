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

		$name = self::DISPLAY_NAMES[ $slug ] ?? $slug;

		/**
		 * Filters the name a consent platform is shown under.
		 *
		 * Pair it with `gtmkit_active_cmp` when declaring a platform GTM Kit
		 * does not recognise, so Site Health names it the way its users know
		 * it instead of showing the raw slug. An empty return keeps the
		 * default name, so a detected platform is never shown without one.
		 *
		 * @param string $name The display name. The slug itself for a platform GTM Kit does not recognise.
		 * @param string $slug The consent platform slug.
		 */
		$filtered = apply_filters( 'gtmkit_cmp_display_name', $name, $slug );

		return ( is_string( $filtered ) && $filtered !== '' ) ? $filtered : $name;
	}

	/**
	 * Detect the first matching active CMP plugin, if any.
	 *
	 * Iteration follows the canonical order Cookiebot → Iubenda →
	 * CookieYes; sites running more than one CMP plugin (rare and
	 * misconfigured) get the first match for a deterministic fallback.
	 *
	 * @return string|null One of `cookiebot`, `iubenda`, `cookieyes`, a slug
	 *     declared through the `gtmkit_active_cmp` filter, or null when no
	 *     consent platform is active.
	 */
	public static function detect_active_cmp(): ?string {
		Util::load_plugin_api();

		$detected = null;

		foreach ( self::PLUGIN_FILES as $slug => $plugin_files ) {
			foreach ( $plugin_files as $plugin_file ) {
				if ( is_plugin_active( $plugin_file ) ) {
					$detected = $slug;
					break 2;
				}
			}
		}

		/**
		 * Filters the consent platform GTM Kit treats as active.
		 *
		 * Detection only sees consent platforms installed as plugins. A site
		 * whose platform is loaded by the theme, a code snippet or a tag can
		 * declare it here, so Site Health and the dashboard notices stop
		 * reporting consent as unconfigured. Return null or an empty string
		 * for no consent platform.
		 *
		 * @param string|null $detected The detected slug, or null when none was detected.
		 */
		$filtered = apply_filters( 'gtmkit_active_cmp', $detected );

		return ( is_string( $filtered ) && $filtered !== '' ) ? $filtered : null;
	}
}
