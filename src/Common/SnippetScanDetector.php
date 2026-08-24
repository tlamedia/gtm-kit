<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

/**
 * Identifies tracking implementations in a page's HTML.
 *
 * A pure parser: it is handed a response body and a description of what GTM
 * Kit itself would have written, and it returns one entry per implementation
 * it can identify. It makes no decisions about what the finding means; that
 * belongs to {@see SnippetScan} and the surfaces that report it.
 *
 * Matching is deliberately host-agnostic. A server-side container moves the
 * loader to the site's own domain under a name the site owner chooses, so a
 * detector anchored to `googletagmanager.com` would miss precisely the setups
 * that are hardest to debug by hand. Where a loader carries no container ID at
 * all, the inline bootstrap that pushes `gtm.start` and creates the script
 * element is the signal, and the loader URL is corroboration.
 */
final class SnippetScanDetector {

	/**
	 * A Google Tag Manager container.
	 *
	 * @var string
	 */
	public const TYPE_GTM = 'gtm';

	/**
	 * A gtag.js implementation, i.e. a Google tag loaded directly.
	 *
	 * @var string
	 */
	public const TYPE_GTAG = 'gtag';

	/**
	 * Identified from a script element with a `src`.
	 *
	 * @var string
	 */
	public const SOURCE_SCRIPT = 'script';

	/**
	 * Identified from an inline bootstrap script.
	 *
	 * @var string
	 */
	public const SOURCE_INLINE = 'inline';

	/**
	 * Identified from the noscript iframe. Never a second load: it is the
	 * fallback of an implementation, not an implementation of its own.
	 *
	 * @var string
	 */
	public const SOURCE_NOSCRIPT = 'noscript';

	/**
	 * A bare container ID somewhere in the document, with nothing loading it.
	 *
	 * @var string
	 */
	public const SOURCE_LITERAL = 'literal';

	/**
	 * The implementation carries GTM Kit's own container or loader.
	 *
	 * @var string
	 */
	public const OWNER_GTMKIT = 'gtmkit';

	/**
	 * The implementation is something other than GTM Kit's own output.
	 *
	 * @var string
	 */
	public const OWNER_FOREIGN = 'foreign';

	/**
	 * The implementation carries nothing that identifies it either way.
	 *
	 * @var string
	 */
	public const OWNER_UNKNOWN = 'unknown';

	/**
	 * Identify every tracking implementation in a page's HTML.
	 *
	 * @param string                $html The response body.
	 * @param array<string, string> $own Description of GTM Kit's own output: `container`, `domain`, `loader`.
	 *
	 * @return array<int, array<string, mixed>> One entry per identified implementation.
	 */
	public static function detect( string $html, array $own = [] ): array {

		$own = [
			'container' => isset( $own['container'] ) ? (string) $own['container'] : '',
			'domain'    => isset( $own['domain'] ) ? (string) $own['domain'] : '',
			'loader'    => isset( $own['loader'] ) ? (string) $own['loader'] : '',
		];

		$implementations = array_merge(
			self::detect_loader_scripts( $html, $own ),
			self::detect_inline_bootstraps( $html ),
			self::detect_noscript_frames( $html )
		);

		$implementations = array_merge( $implementations, self::detect_bare_containers( $html, $implementations ) );

		foreach ( $implementations as $index => $implementation ) {
			$implementations[ $index ]['owner'] = self::classify_owner( $implementation, $own );
		}

		return array_values( $implementations );
	}

	/**
	 * Whether an implementation counts as a separate load of tracking.
	 *
	 * The noscript iframe belongs to an implementation that is already
	 * counted, and a bare container ID is not loaded by anything, so neither
	 * can make a page load tracking twice.
	 *
	 * @param array<string, mixed> $implementation An entry from {@see self::detect()}.
	 *
	 * @return bool
	 */
	public static function counts_as_load( array $implementation ): bool {

		$source = isset( $implementation['source'] ) ? (string) $implementation['source'] : '';

		return $source === self::SOURCE_SCRIPT || $source === self::SOURCE_INLINE;
	}

	/**
	 * Identify loader scripts, on any host.
	 *
	 * @param string                $html The response body.
	 * @param array<string, string> $own Description of GTM Kit's own output.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_loader_scripts( string $html, array $own ): array {

		$found = [];

		if ( preg_match_all( '~<script\b(?![^>]*\btype\s*=\s*["\']application/(?:ld\+)?json["\'])[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $html, $matches ) === false ) {
			return $found;
		}

		foreach ( $matches[1] as $raw_src ) {
			$src  = str_replace( '&amp;', '&', $raw_src );
			$type = self::loader_type( $src, $own );

			if ( $type === '' ) {
				continue;
			}

			$found[] = [
				'type'      => $type,
				'source'    => self::SOURCE_SCRIPT,
				'container' => self::container_from_url( $src ),
				'host'      => self::host_from_url( $src ),
				'weak'      => false,
			];
		}

		return $found;
	}

	/**
	 * Decide whether a script URL is a tracking loader, and of which kind.
	 *
	 * @param string                $src The script URL.
	 * @param array<string, string> $own Description of GTM Kit's own output.
	 *
	 * @return string One of the TYPE_* constants, or an empty string.
	 */
	private static function loader_type( string $src, array $own ): string {

		if ( stripos( $src, 'gtag/js?' ) !== false ) {
			return self::TYPE_GTAG;
		}

		if ( stripos( $src, 'gtm.js?' ) !== false
			|| preg_match( '~[?&](?:id|st)=GTM-~i', $src ) === 1
		) {
			return self::TYPE_GTM;
		}

		if ( preg_match( '~[?&]st=~i', $src ) === 1 && preg_match( '~\.js(?:\?|$)~i', $src ) === 1 ) {
			// A server-side loader named by the site owner, carrying the
			// container in `st` rather than `id`.
			return self::TYPE_GTM;
		}

		if ( self::is_own_sgtm_loader( $src, $own ) ) {
			// A server-side loader on the configured domain that carries no
			// container ID at all. Recognisable only because the domain and
			// the loader name are the ones this site is configured with.
			return self::TYPE_GTM;
		}

		return '';
	}

	/**
	 * Whether a script URL is this site's configured server-side loader.
	 *
	 * @param string                $src The script URL.
	 * @param array<string, string> $own Description of GTM Kit's own output.
	 *
	 * @return bool
	 */
	private static function is_own_sgtm_loader( string $src, array $own ): bool {

		if ( $own['domain'] === '' || $own['domain'] === 'www.googletagmanager.com' || $own['loader'] === '' ) {
			return false;
		}

		if ( strcasecmp( self::host_from_url( $src ), $own['domain'] ) !== 0 ) {
			return false;
		}

		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		$file = basename( $path );

		// The Cookie Keeper variant serves the same loader under a `kp` prefix.
		return strcasecmp( $file, $own['loader'] . '.js' ) === 0
			|| strcasecmp( $file, 'kp' . $own['loader'] . '.js' ) === 0;
	}

	/**
	 * Identify inline bootstrap scripts.
	 *
	 * The bootstrap is the primary presence signal: whatever the loader is
	 * called and wherever it is served from, something in the page has to
	 * push `gtm.start` and create the script element.
	 *
	 * @param string $html The response body.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_inline_bootstraps( string $html ): array {

		$found = [];

		if ( preg_match_all( '~<script\b(?![^>]*\bsrc\s*=)[^>]*>(.*?)</script>~is', $html, $matches ) === false ) {
			return $found;
		}

		foreach ( $matches[1] as $code ) {
			if ( ! self::is_bootstrap( $code ) ) {
				continue;
			}

			$found[] = [
				'type'      => self::TYPE_GTM,
				'source'    => self::SOURCE_INLINE,
				'container' => self::container_from_text( $code ),
				'host'      => self::host_from_text( $code ),
				'weak'      => false,
			];
		}

		return $found;
	}

	/**
	 * Whether an inline script is a container bootstrap.
	 *
	 * @param string $code The script contents.
	 *
	 * @return bool
	 */
	private static function is_bootstrap( string $code ): bool {

		if ( stripos( $code, 'gtm.start' ) !== false ) {
			return true;
		}

		// The classic snippet body, whitespace-tolerant and independent of
		// the dataLayer name the site uses.
		return preg_match( '~\bw\[l\]\s*=\s*w\[l\]\s*\|\|\s*\[\s*\]~i', $code ) === 1;
	}

	/**
	 * Identify noscript fallback iframes.
	 *
	 * @param string $html The response body.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_noscript_frames( string $html ): array {

		$found = [];

		if ( preg_match_all( '~<iframe\b[^>]*\bsrc\s*=\s*["\']([^"\']*ns\.html\?[^"\']*)["\']~i', $html, $matches ) === false ) {
			return $found;
		}

		foreach ( $matches[1] as $raw_src ) {
			$src = str_replace( '&amp;', '&', $raw_src );

			if ( preg_match( '~[?&]id=GTM-~i', $src ) !== 1 ) {
				continue;
			}

			$found[] = [
				'type'      => self::TYPE_GTM,
				'source'    => self::SOURCE_NOSCRIPT,
				'container' => self::container_from_url( $src ),
				'host'      => self::host_from_url( $src ),
				'weak'      => false,
			];
		}

		return $found;
	}

	/**
	 * Identify container IDs that appear in the document with nothing loading them.
	 *
	 * Recorded as a weak signal: a container ID in the page is evidence that
	 * something intends to load tracking, but not evidence that anything does.
	 *
	 * @param string                           $html The response body.
	 * @param array<int, array<string, mixed>> $identified Implementations identified so far.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_bare_containers( string $html, array $identified ): array {

		$found = [];

		if ( preg_match_all( '~\bGTM-[A-Z0-9]{4,}\b~', $html, $matches ) === false ) {
			return $found;
		}

		$accounted = [];
		foreach ( $identified as $implementation ) {
			$container = (string) $implementation['container'];
			if ( $container !== '' ) {
				$accounted[ strtoupper( $container ) ] = true;
			}
		}

		foreach ( array_unique( $matches[0] ) as $container ) {
			if ( isset( $accounted[ strtoupper( $container ) ] ) ) {
				continue;
			}

			$accounted[ strtoupper( $container ) ] = true;

			$found[] = [
				'type'      => self::TYPE_GTM,
				'source'    => self::SOURCE_LITERAL,
				'container' => $container,
				'host'      => '',
				'weak'      => true,
			];
		}

		return $found;
	}

	/**
	 * Decide whether an implementation is GTM Kit's own tracking or something else.
	 *
	 * The question answered here is whose tracking this is, not which piece
	 * of code printed it: an implementation carrying this site's container or
	 * its configured server-side loader is GTM Kit's, however it reached the
	 * page.
	 *
	 * @param array<string, mixed>  $implementation An identified implementation.
	 * @param array<string, string> $own Description of GTM Kit's own output.
	 *
	 * @return string One of the OWNER_* constants.
	 */
	private static function classify_owner( array $implementation, array $own ): string {

		$container = (string) $implementation['container'];
		$host      = (string) $implementation['host'];

		if ( $own['container'] !== '' && $container !== '' ) {
			return ( strcasecmp( $container, $own['container'] ) === 0 ) ? self::OWNER_GTMKIT : self::OWNER_FOREIGN;
		}

		if ( $container !== '' ) {
			return self::OWNER_FOREIGN;
		}

		if ( (string) $implementation['type'] === self::TYPE_GTAG ) {
			return self::OWNER_FOREIGN;
		}

		if ( $host !== '' && $own['domain'] !== '' && strcasecmp( $host, $own['domain'] ) === 0 ) {
			return self::OWNER_GTMKIT;
		}

		return self::OWNER_UNKNOWN;
	}

	/**
	 * Extract the container or measurement ID a loader URL carries.
	 *
	 * @param string $url The script or iframe URL.
	 *
	 * @return string The ID, or an empty string.
	 */
	private static function container_from_url( string $url ): string {

		if ( preg_match( '~[?&](?:id|st)=([^&#]+)~i', $url, $matches ) !== 1 ) {
			return '';
		}

		$value = strtoupper( rawurldecode( $matches[1] ) );

		if ( preg_match( '~^(?:GTM|G|AW|DC|GT|UA)-[A-Z0-9\-]+$~', $value ) === 1 ) {
			return $value;
		}

		// The Cookie Keeper loader carries the container without its prefix.
		if ( preg_match( '~^[A-Z0-9]{4,}$~', $value ) === 1 ) {
			return 'GTM-' . $value;
		}

		return '';
	}

	/**
	 * Extract the container ID an inline bootstrap carries.
	 *
	 * @param string $code The script contents.
	 *
	 * @return string The container ID, or an empty string.
	 */
	private static function container_from_text( string $code ): string {

		if ( preg_match( '~\bGTM-[A-Z0-9]{4,}\b~', $code, $matches ) !== 1 ) {
			return '';
		}

		return $matches[0];
	}

	/**
	 * Extract the host an inline bootstrap loads its script from.
	 *
	 * @param string $code The script contents.
	 *
	 * @return string The host, or an empty string.
	 */
	private static function host_from_text( string $code ): string {

		if ( preg_match( '~https?://([A-Za-z0-9.\-]+)~', $code, $matches ) !== 1 ) {
			return '';
		}

		return $matches[1];
	}

	/**
	 * Extract the host of a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string The host, or an empty string.
	 */
	private static function host_from_url( string $url ): string {

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}
}
