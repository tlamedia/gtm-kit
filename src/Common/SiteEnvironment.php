<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Options\Options;

/**
 * What kind of site WordPress reports this install to be.
 *
 * The single place GTM Kit asks WordPress about the site's environment
 * type, so the runtime gate, the settings readout, Site Health, the
 * scan and the admin notices can never reach different answers. Every
 * consumer goes through here rather than reading the
 * `WP_ENVIRONMENT_TYPE` constant, which misses WordPress's own
 * resolution: the environment variable of the same name, the rejection
 * of values outside the known set, and the fallback to production.
 *
 * Not to be confused with a Google Tag Manager environment, which is
 * the `gtm_auth`/`gtm_preview` pair on the container settings.
 */
final class SiteEnvironment {

	/**
	 * The environment type WordPress reports for a site that has not said otherwise.
	 *
	 * @var string
	 */
	public const PRODUCTION = 'production';

	/**
	 * Environment types on which container output is withheld by default.
	 *
	 * @var array<int, string>
	 */
	public const NON_PRODUCTION_TYPES = [ 'staging', 'development', 'local' ];

	/**
	 * The environment type WordPress reports for this site.
	 *
	 * Always one of `production`, `staging`, `development` or `local`:
	 * WordPress falls back to production for an undeclared site and for a
	 * declared value it does not recognise.
	 *
	 * @return string
	 */
	public static function get_type(): string {
		return (string) \wp_get_environment_type();
	}

	/**
	 * Whether WordPress reports this site as production.
	 *
	 * @return bool
	 */
	public static function is_production(): bool {
		return self::get_type() === self::PRODUCTION;
	}

	/**
	 * Whether the site itself declared its environment type.
	 *
	 * A site that says nothing is reported as production by WordPress, so
	 * the two cases are worth telling apart: only a declared value is a
	 * deliberate statement by the site, and only an undeclared site is
	 * worth suggesting a declaration to.
	 *
	 * @return bool
	 */
	public static function is_declared(): bool {
		if ( \defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE ) {
			return true;
		}

		return ( \function_exists( 'getenv' ) && false !== \getenv( 'WP_ENVIRONMENT_TYPE' ) );
	}

	/**
	 * Address fragments that commonly belong to a copy of a site rather than the real one.
	 *
	 * Matched against whole labels of the host, so `dev.example.com` and
	 * `example-staging.com` match while `devon-bakery.com` does not. Kept
	 * short and obvious on purpose: this list only ever produces a
	 * suggestion, never a change in output, so breadth would cost more in
	 * wrong guesses than it buys.
	 *
	 * @var array<int, string>
	 */
	private const COPY_HOST_LABELS = [
		'staging',
		'stage',
		'dev',
		'development',
		'test',
		'testing',
		'qa',
		'uat',
		'preview',
		'sandbox',
		'local',
		'localhost',
	];

	/**
	 * Whether the site's own address reads like a copy rather than the real site.
	 *
	 * A guess, and treated as one: it is only ever used to suggest that the
	 * site declare what it is, and never to change what GTM Kit outputs.
	 *
	 * @param string $url The address to inspect. Defaults to the site's home URL.
	 *
	 * @return bool
	 */
	public static function url_looks_like_a_copy( string $url = '' ): bool {

		$host = (string) \wp_parse_url( ( '' !== $url ) ? $url : (string) \home_url(), PHP_URL_HOST );

		if ( '' === $host ) {
			return false;
		}

		/**
		 * Host labels that suggest a site is a copy rather than the real one.
		 *
		 * Matched against whole labels of the host name, lowercased.
		 *
		 * @param array<int, string> $labels The host labels to look for.
		 */
		$labels = \apply_filters( 'gtmkit_copy_site_host_labels', self::COPY_HOST_LABELS );

		if ( ! \is_array( $labels ) ) {
			return false;
		}

		$host_labels = \preg_split( '/[.\-_]/', \strtolower( $host ) );

		if ( ! \is_array( $host_labels ) ) {
			return false;
		}

		return ( [] !== \array_intersect( $host_labels, \array_map( 'strval', $labels ) ) );
	}

	/**
	 * Whether GTM Kit withholds container output because of what the site reports.
	 *
	 * True when WordPress reports anything other than production and the
	 * site has not asked GTM Kit to load the container there anyway. A
	 * non-production value is by definition a declaration by the site, so
	 * acting on it is acting on what the site said about itself.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return bool
	 */
	public static function suppresses_container( Options $options ): bool {
		if ( self::is_production() ) {
			return false;
		}

		return empty( $options->get( 'general', 'load_on_non_production' ) );
	}
}
