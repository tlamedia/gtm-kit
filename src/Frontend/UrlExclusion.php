<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * URL exclusion matcher.
 *
 * Decides whether a request path matches any of the admin-managed
 * patterns that suppress GTM Kit output for the current URL. The
 * decision is a pure function of the request path and the stored
 * patterns so it stays correct under full-page caching.
 */
final class UrlExclusion {

	/**
	 * Decide whether the given request path is excluded.
	 *
	 * @param string                                                $path     Full request path (no query string).
	 * @param array<int, array{pattern: string, mode: string}>|null $patterns Stored pattern list.
	 * @return bool
	 */
	public static function is_excluded( string $path, $patterns ): bool {
		if ( ! is_array( $patterns ) ) {
			$patterns = [];
		}

		/**
		 * Filter the effective list of URL-exclusion patterns just before
		 * matching. Useful for code that wants to add, remove, or replace
		 * patterns without persisting them to the admin option.
		 *
		 * @param array<int, array{pattern: string, mode: string}> $patterns Effective pattern list.
		 * @param string                                           $path     Normalized request path being evaluated.
		 */
		$patterns = apply_filters( 'gtmkit_excluded_url_patterns', $patterns, $path );

		$normalized_path = self::normalize_path( $path );
		$is_excluded     = false;

		if ( is_array( $patterns ) && ! empty( $patterns ) ) {
			$is_excluded = self::matches_any( $normalized_path, $patterns );
		}

		/**
		 * Filter the final URL-exclusion decision for the current request.
		 * Last word for code that needs to force GTM Kit on or off based
		 * on signals beyond the stored pattern list.
		 *
		 * @param bool   $is_excluded     Computed exclusion state.
		 * @param string $normalized_path Path used for matching (after normalisation).
		 */
		return (bool) apply_filters( 'gtmkit_is_url_excluded', $is_excluded, $normalized_path );
	}

	/**
	 * Normalize the request path to the form used by the matcher.
	 *
	 * - decodes percent-encoded characters once;
	 * - lower-cases for case-insensitive matching;
	 * - reduces multiple trailing slashes to none (a single trailing
	 *   slash is stripped so `/checkout/` and `/checkout` are treated
	 *   the same).
	 *
	 * @param string $path Raw path.
	 * @return string Normalized path.
	 */
	public static function normalize_path( string $path ): string {
		$path = rawurldecode( $path );
		$path = strtolower( $path );

		if ( $path !== '/' ) {
			$path = rtrim( $path, '/' );
			if ( $path === '' ) {
				$path = '/';
			}
		}

		return $path;
	}

	/**
	 * Match the normalized path against the configured patterns.
	 *
	 * @param string                                           $normalized_path Path after normalisation.
	 * @param array<int, array{pattern: string, mode: string}> $patterns        Stored pattern list.
	 * @return bool
	 */
	private static function matches_any( string $normalized_path, array $patterns ): bool {
		foreach ( $patterns as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$pattern = isset( $entry['pattern'] ) ? trim( (string) $entry['pattern'] ) : '';
			if ( $pattern === '' ) {
				continue;
			}

			$mode  = isset( $entry['mode'] ) ? (string) $entry['mode'] : OptionSchema::URL_EXCLUSION_MODE_GLOB;
			$regex = $mode === OptionSchema::URL_EXCLUSION_MODE_REGEX
				? self::regex_pattern_for_match( $pattern )
				: self::glob_to_regex( $pattern );

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- preg_match warns on a bad pattern; we treat the `false` return as fail-open below.
			$result = @preg_match( $regex, $normalized_path );

			if ( $result === 1 ) {
				return true;
			}

			if ( $result === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,QITStandard.PHP.DebugCode.DebugFunctionFound -- intentional debug-mode signal for admins debugging a malformed pattern.
				error_log( sprintf( '[GTM Kit] URL exclusion pattern failed at runtime: %s', $pattern ) );
			}
		}

		return false;
	}

	/**
	 * Translate a glob pattern into an anchored PCRE.
	 *
	 * `*` matches any run of characters including `/`. `?` matches a
	 * single character. Every other regex metacharacter is escaped. The
	 * pattern is anchored at the start of the path; the user adds `*`
	 * to allow a suffix, mirroring how they would write the pattern in
	 * the admin UI.
	 *
	 * The trailing slash of the user-supplied pattern is normalized the
	 * same way the path is, so `/checkout/` and `/checkout` produce the
	 * same regex.
	 *
	 * @param string $glob Glob expression.
	 * @return string Compiled regex including delimiters and flags.
	 */
	private static function glob_to_regex( string $glob ): string {
		$glob = strtolower( $glob );

		if ( $glob !== '/' ) {
			$glob = rtrim( $glob, '/' );
			if ( $glob === '' ) {
				$glob = '/';
			}
		}

		$length    = strlen( $glob );
		$converted = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $glob[ $i ];
			if ( $char === '*' ) {
				$converted .= '.*';
			} elseif ( $char === '?' ) {
				$converted .= '.';
			} else {
				$converted .= preg_quote( $char, '~' );
			}
		}

		return '~^' . $converted . '$~i';
	}

	/**
	 * Wrap a raw regex pattern with the delimiters and flags the
	 * matcher uses. `~` is the delimiter because it rarely appears in
	 * URL paths; literal `~` inside the pattern is escaped.
	 *
	 * @param string $pattern Raw regex pattern entered by the admin.
	 * @return string Compiled regex including delimiters and flags.
	 */
	private static function regex_pattern_for_match( string $pattern ): string {
		return '~' . str_replace( '~', '\~', $pattern ) . '~i';
	}

	/**
	 * Resolve the request path the matcher should evaluate.
	 *
	 * Pulled from `$_SERVER['REQUEST_URI']`, with query string and
	 * fragment discarded. Returns `/` when no path is available so
	 * callers can pass the result through unchanged.
	 *
	 * @return string
	 */
	public static function current_request_path(): string {
		$raw = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $raw === '' ) {
			return '/';
		}

		$path = wp_parse_url( $raw, PHP_URL_PATH );
		if ( ! is_string( $path ) || $path === '' ) {
			return '/';
		}

		return $path;
	}
}
