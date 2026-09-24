<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Options\Options;

/**
 * The loader Stape issues for a server-side container.
 *
 * Stape answers a request for a container's loader with a ready-made snippet.
 * GTM Kit never prints that snippet. It keeps only the three values that make
 * up the loader address, each checked against a strict pattern, and builds its
 * own snippet around them. Everything GTM Kit's snippet does therefore still
 * applies, and a bad response has no way to put script on the page.
 *
 * A stored loader belongs to the settings it was issued for. The frontend uses
 * it only while those settings are unchanged and falls back to the standard
 * loader otherwise, so an import, a constant or a restored backup stays correct
 * without anyone asking Stape again.
 */
final class StapeLoader {

	/**
	 * The option holding the stored loader.
	 *
	 * Kept apart from the settings array so that nothing a user submits, imports
	 * or syncs can write it.
	 *
	 * @var string
	 */
	const OPTION = 'gtmkit_sgtm_loader';

	/**
	 * The transient that spaces out manual refreshes.
	 *
	 * @var string
	 */
	const THROTTLE_TRANSIENT = 'gtmkit_sgtm_loader_refresh';

	/**
	 * Seconds between two manual refreshes.
	 *
	 * @var int
	 */
	const THROTTLE_SECONDS = 60;

	/**
	 * A loader fetched from Stape.
	 *
	 * @var string
	 */
	const SOURCE_API = 'api';

	/**
	 * A loader pasted by the site owner.
	 *
	 * @var string
	 */
	const SOURCE_PASTED = 'pasted';

	/**
	 * The loader file name, without `.js`.
	 *
	 * @var string
	 */
	const PATH_PATTERN = '[a-z0-9]{1,64}';

	/**
	 * The name of the loader's query parameter.
	 *
	 * @var string
	 */
	const PARAM_PATTERN = '[A-Za-z0-9_]{1,32}';

	/**
	 * The value of the loader's query parameter: URL-encoded base64.
	 *
	 * @var string
	 */
	const VALUE_PATTERN = '(?:[A-Za-z0-9]|%[0-9A-Fa-f]{2}){1,512}';

	/**
	 * The longest snippet the parser reads. Stape's are under 2 KB.
	 *
	 * @var int
	 */
	const MAX_CODE_LENGTH = 20000;

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Read the loader address out of a snippet issued by Stape.
	 *
	 * Stape writes the address in one of two shapes: in full, as
	 * `https://{domain}/{path}.js?` with the query pair passed separately, or,
	 * with Cookie Keeper, as an origin, a path and a query pair in three
	 * adjacent string literals. Anything that does not yield exactly one path
	 * and one query pair on the configured domain is refused.
	 *
	 * @param string $code   The snippet.
	 * @param string $domain The configured server-side container domain.
	 *
	 * @return array{path: string, param: string, value: string}|null Null when the snippet cannot be trusted.
	 */
	public static function parse( string $code, string $domain ): ?array {

		$domain = strtolower( trim( $domain ) );

		if ( $domain === '' || strlen( $code ) > self::MAX_CODE_LENGTH ) {
			return null;
		}

		$pair_pattern = self::PARAM_PATTERN . '=' . self::VALUE_PATTERN;
		$paths        = [];
		$pairs        = [];
		$on_domain    = false;

		// Every script address, wherever it points.
		preg_match_all( '~(https?)://([^/\s"\'`]+)/([^/\s"\'`?]*)\.js\?([^\s"\'`]*)~i', $code, $urls, PREG_SET_ORDER );
		foreach ( $urls as $url ) {
			if ( strtolower( $url[1] ) !== 'https' || strtolower( $url[2] ) !== $domain ) {
				return null;
			}
			$on_domain = true;
			$paths[]   = $url[3];
			if ( $url[4] !== '' ) {
				$pairs[] = $url[4];
			}
		}

		// Every bare origin, as the Cookie Keeper snippet writes it.
		preg_match_all( '~(["\'])(https?)://([^/\s"\'`]+)\1~i', $code, $origins, PREG_SET_ORDER );
		foreach ( $origins as $origin ) {
			if ( strtolower( $origin[2] ) !== 'https' || strtolower( $origin[3] ) !== $domain ) {
				return null;
			}
			$on_domain = true;
		}

		// A path literal immediately followed by the query pair literal.
		preg_match_all( '~(["\'])(' . self::PATH_PATTERN . ')\1\s*,\s*[A-Za-z_$][\w$]*\s*=\s*(["\'])(' . $pair_pattern . ')\3~', $code, $adjacent, PREG_SET_ORDER );
		foreach ( $adjacent as $literal ) {
			$paths[] = $literal[2];
		}

		// Every query pair written as a string literal of its own.
		preg_match_all( '~(["\'])(' . $pair_pattern . ')\1~', $code, $literals, PREG_SET_ORDER );
		foreach ( $literals as $literal ) {
			$pairs[] = $literal[2];
		}

		$paths = array_values( array_unique( $paths ) );
		$pairs = array_values( array_unique( $pairs ) );

		if ( ! $on_domain || count( $paths ) !== 1 || count( $pairs ) !== 1 ) {
			return null;
		}

		if ( preg_match( '~^' . self::PATH_PATTERN . '$~D', $paths[0] ) !== 1
			|| preg_match( '~^(' . self::PARAM_PATTERN . ')=(' . self::VALUE_PATTERN . ')$~D', $pairs[0], $pair ) !== 1
		) {
			return null;
		}

		return [
			'path'  => $paths[0],
			'param' => $pair[1],
			'value' => $pair[2],
		];
	}

	/**
	 * Read the data layer name a snippet issued by Stape was made for.
	 *
	 * Stape writes the name as the string literal right after the `script`
	 * literal: as the next argument without Cookie Keeper, and as the next
	 * variable in the declaration list with it. Stape also encodes the name
	 * into the loader's query value, so a loader only works with the name it
	 * was issued for.
	 *
	 * @param string $code The snippet.
	 *
	 * @return string|null Null unless the snippet names exactly one valid data layer.
	 */
	public static function read_datalayer_name( string $code ): ?string {

		if ( strlen( $code ) > self::MAX_CODE_LENGTH ) {
			return null;
		}

		preg_match_all( '~(["\'])script\1\s*,\s*(?:[A-Za-z_$][\w$]*\s*=\s*)?(["\'])([^"\'\s]*)\2~', $code, $matches );
		$names = array_values( array_unique( $matches[3] ) );

		if ( count( $names ) !== 1 || preg_match( '~^[A-Za-z_$][A-Za-z0-9_$]{0,127}$~D', $names[0] ) !== 1 ) {
			return null;
		}

		return $names[0];
	}

	/**
	 * Whether the site owner has asked for the loader Stape issues.
	 *
	 * @return bool
	 */
	public function is_switched_on(): bool {
		return (bool) $this->options->get( 'general', 'sgtm_stape_issued_loader' );
	}

	/**
	 * Whether switched on and every setting Stape needs is filled in.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! $this->is_switched_on() || (bool) $this->options->get( 'general', 'google_tag_gateway' ) ) {
			return false;
		}

		$inputs = $this->get_inputs();

		return $inputs['identifier'] !== '' && $inputs['gtm_id'] !== '' && $inputs['domain'] !== '';
	}

	/**
	 * The settings a loader is issued for.
	 *
	 * @return array{identifier: string, gtm_id: string, domain: string, cookie_keeper: bool, datalayer_name: string}
	 */
	public function get_inputs(): array {
		$datalayer_name = (string) $this->options->get( 'general', 'datalayer_name' );

		return [
			'identifier'     => (string) $this->options->get( 'general', 'sgtm_container_identifier' ),
			'gtm_id'         => (string) $this->options->get( 'general', 'gtm_id' ),
			'domain'         => (string) $this->options->get( 'general', 'sgtm_domain' ),
			'cookie_keeper'  => (bool) $this->options->get( 'general', 'sgtm_cookie_keeper' ),
			'datalayer_name' => ( $datalayer_name !== '' ) ? $datalayer_name : 'dataLayer',
		];
	}

	/**
	 * A fingerprint of the settings a loader is issued for.
	 *
	 * @return string
	 */
	public function fingerprint(): string {
		return md5( (string) wp_json_encode( $this->get_inputs() ) );
	}

	/**
	 * The stored loader, if it is well formed.
	 *
	 * The values are checked again on the way out, so a loader written to the
	 * database by anything other than this class is never printed. A loader
	 * without the `datalayer_checked` flag was stored without being checked
	 * against the data layer name, and may listen to a data layer GTM Kit never
	 * pushes to, so it is treated as absent.
	 *
	 * @return array{path: string, param: string, value: string, inputs: string, source: string, region: string, fetched_at: int}|null
	 */
	public function get_stored(): ?array {
		$stored = get_option( self::OPTION );

		if ( ! self::is_checked( $stored ) ) {
			return null;
		}

		foreach ( [ 'path', 'param', 'value', 'inputs', 'source' ] as $key ) {
			if ( ! isset( $stored[ $key ] ) || ! is_string( $stored[ $key ] ) ) {
				return null;
			}
		}

		if ( preg_match( '~^' . self::PATH_PATTERN . '$~D', $stored['path'] ) !== 1
			|| preg_match( '~^' . self::PARAM_PATTERN . '$~D', $stored['param'] ) !== 1
			|| preg_match( '~^' . self::VALUE_PATTERN . '$~D', $stored['value'] ) !== 1
			|| ! in_array( $stored['source'], [ self::SOURCE_API, self::SOURCE_PASTED ], true )
		) {
			return null;
		}

		return [
			'path'       => $stored['path'],
			'param'      => $stored['param'],
			'value'      => $stored['value'],
			'inputs'     => $stored['inputs'],
			'source'     => $stored['source'],
			'region'     => ( isset( $stored['region'] ) && is_string( $stored['region'] ) ) ? $stored['region'] : '',
			'fetched_at' => isset( $stored['fetched_at'] ) ? (int) $stored['fetched_at'] : 0,
		];
	}

	/**
	 * Whether a stored option value was checked against the data layer name before it was stored.
	 *
	 * @param mixed $stored The stored option value.
	 *
	 * @return bool
	 * @phpstan-assert-if-true array<string, mixed> $stored
	 */
	public static function is_checked( $stored ): bool {
		return is_array( $stored ) && isset( $stored['datalayer_checked'] ) && true === $stored['datalayer_checked'];
	}

	/**
	 * The stored loader, when it belongs to the current settings.
	 *
	 * @return array{path: string, param: string, value: string, inputs: string, source: string, region: string, fetched_at: int}|null
	 */
	public function get_active(): ?array {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		$stored = $this->get_stored();

		if ( null === $stored || ! hash_equals( $stored['inputs'], $this->fingerprint() ) ) {
			return null;
		}

		return $stored;
	}

	/**
	 * Store a loader for the current settings.
	 *
	 * Every caller has checked the loader against the data layer name first,
	 * which the stored entry records.
	 *
	 * @param array{path: string, param: string, value: string} $loader The parsed loader.
	 * @param string                                            $source One of the SOURCE_* constants.
	 * @param string                                            $region The Stape region that issued it, or an empty string.
	 *
	 * @return void
	 */
	public function store( array $loader, string $source, string $region ): void {
		update_option(
			self::OPTION,
			[
				'path'              => $loader['path'],
				'param'             => $loader['param'],
				'value'             => $loader['value'],
				'inputs'            => $this->fingerprint(),
				'source'            => $source,
				'region'            => $region,
				'fetched_at'        => time(),
				'datalayer_checked' => true,
			],
			true
		);
	}

	/**
	 * Forget the stored loader.
	 *
	 * @return void
	 */
	public function delete(): void {
		delete_option( self::OPTION );
	}

	/**
	 * The settings a save must change before Stape is asked again.
	 *
	 * Read before the save and handed to sync_after_save(). Empty while the
	 * loader is off or incomplete, so the save that switches it on always asks.
	 *
	 * @return string
	 */
	public function get_save_baseline(): string {
		return $this->is_enabled() ? $this->fingerprint() : '';
	}

	/**
	 * Bring the stored loader in line with settings that were just saved.
	 *
	 * Asks Stape only when the save switched the loader on or changed the
	 * settings it is issued for. A save that left them alone never asks, even
	 * when nothing is stored because the last request failed: every save would
	 * otherwise wait on Stape again. Refresh is how the site owner retries. A
	 * loader that no longer matches is removed whether or not a new one
	 * arrives, because it belongs to the old settings.
	 *
	 * @param StapeLoaderClient $client   The Stape API client.
	 * @param string            $baseline What get_save_baseline() returned before the save.
	 *
	 * @return array{status: string, reason: string}
	 */
	public function sync_after_save( StapeLoaderClient $client, string $baseline ): array {

		if ( ! $this->is_enabled() ) {
			if ( false === get_option( self::OPTION ) ) {
				return self::outcome( 'unchanged' );
			}

			$this->delete();

			return self::outcome( 'removed' );
		}

		$stored = $this->get_stored();

		if ( null !== $stored && hash_equals( $stored['inputs'], $this->fingerprint() ) ) {
			return self::outcome( 'unchanged' );
		}

		if ( hash_equals( $baseline, $this->fingerprint() ) ) {
			return self::outcome( 'unchanged' );
		}

		return $this->fetch( $client, true );
	}

	/**
	 * Ask Stape for the loader again, on the site owner's request.
	 *
	 * A failure keeps the stored loader, which still belongs to the current
	 * settings when there is one.
	 *
	 * @param StapeLoaderClient $client The Stape API client.
	 *
	 * @return array{status: string, reason: string}
	 */
	public function refresh( StapeLoaderClient $client ): array {

		if ( ! $this->is_enabled() ) {
			return self::outcome( 'unavailable' );
		}

		if ( false !== get_transient( self::THROTTLE_TRANSIENT ) ) {
			return self::outcome( 'throttled' );
		}

		set_transient( self::THROTTLE_TRANSIENT, 1, self::THROTTLE_SECONDS );

		return $this->fetch( $client, false );
	}

	/**
	 * Store a loader from the snippet the site owner copied out of Stape.
	 *
	 * @param string $code The pasted snippet.
	 *
	 * @return array{status: string, reason: string}
	 */
	public function paste( string $code ): array {

		if ( ! $this->is_enabled() ) {
			return self::outcome( 'unavailable' );
		}

		$loader = self::parse( $code, $this->get_inputs()['domain'] );

		$datalayer_name = self::read_datalayer_name( $code );

		if ( null === $loader || null === $datalayer_name ) {
			return self::outcome( 'failed', StapeLoaderClient::REASON_UNPARSEABLE );
		}

		if ( $datalayer_name !== $this->get_inputs()['datalayer_name'] ) {
			return self::outcome( 'failed', StapeLoaderClient::REASON_DATALAYER_MISMATCH );
		}

		$this->store( $loader, self::SOURCE_PASTED, '' );

		return self::outcome( 'stored' );
	}

	/**
	 * What the settings screen shows about the loader in use.
	 *
	 * @return array{source: string, region: string, fetchedAt: int}
	 */
	public function get_client_state(): array {
		$active = $this->get_active();

		return [
			'source'    => ( null !== $active ) ? $active['source'] : 'standard',
			'region'    => ( null !== $active ) ? $active['region'] : '',
			'fetchedAt' => ( null !== $active ) ? $active['fetched_at'] : 0,
		];
	}

	/**
	 * Fetch and store a loader.
	 *
	 * @param StapeLoaderClient $client            The Stape API client.
	 * @param bool              $delete_on_failure Whether a failure removes the stored loader.
	 *
	 * @return array{status: string, reason: string}
	 */
	private function fetch( StapeLoaderClient $client, bool $delete_on_failure ): array {
		$result = $client->fetch( $this->get_inputs() );

		if ( null === $result['loader'] ) {
			if ( $delete_on_failure ) {
				$this->delete();
			}

			return self::outcome( 'failed', $result['reason'] );
		}

		$this->store( $result['loader'], self::SOURCE_API, $result['region'] );

		return self::outcome( 'fetched' );
	}

	/**
	 * Assemble an outcome.
	 *
	 * @param string $status What happened.
	 * @param string $reason Why a request failed, or an empty string.
	 *
	 * @return array{status: string, reason: string}
	 */
	private static function outcome( string $status, string $reason = '' ): array {
		return [
			'status' => $status,
			'reason' => $reason,
		];
	}
}
