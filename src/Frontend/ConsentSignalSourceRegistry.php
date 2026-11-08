<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options\Options;

/**
 * Consent signal source registry.
 *
 * Encapsulates registration and resolution of consent signal sources.
 * The registry exposes a single filter (`gtmkit_consent_signal_sources`)
 * that lets Premium add-ons and third-party code plug in alternative
 * consent state providers (e.g., wp-consent-api, CMPs) without
 * modifying core. The highest-priority active source wins; the
 * default source falls back to the Consent Mode v2 settings emitted
 * by the existing admin toggles.
 *
 * Priority registry (documented for ecosystem coordination):
 *
 *  - `gtmkit_default`  priority  10 (this class, core fallback)
 *  - CMPs              priority  80 (reserved for CMP integrations)
 *  - `wp_consent_api`  priority 100 (reserved for WP Consent API integration)
 *
 * Source descriptor shape (the array returned through the filter):
 *
 *     [
 *         'gtmkit_default' => [
 *             'id'        => 'gtmkit_default',
 *             'priority'  => 10,
 *             'is_active' => callable(): bool,
 *             'read'      => callable(): array<string, 'granted'|'denied'>,
 *             'read_default' => callable(): array<string, 'granted'|'denied'>,
 *         ],
 *     ]
 *
 * `read` answers for the visitor on the current request, which may mean
 * reading their consent cookies. The optional `read_default` answers for a
 * visitor who has not answered yet, and must not read the request at all.
 * It is what an administrator is shown when they ask what the site's
 * visitors get, because the administrator's own cookies say nothing about
 * that. A source without it leaves that question unanswered.
 */
final class ConsentSignalSourceRegistry {

	/**
	 * Default-source identifier.
	 *
	 * @var string
	 */
	public const DEFAULT_SOURCE_ID = 'gtmkit_default';

	/**
	 * Default-source priority. Documented in `docs/filters.md` so
	 * third parties can decide whether to override (priority > 10)
	 * or sit below (priority < 10).
	 *
	 * @var int
	 */
	public const DEFAULT_SOURCE_PRIORITY = 10;

	/**
	 * The seven Consent Mode v2 categories, in the canonical order
	 * used by the rest of GTM Kit (see Frontend::enqueue_settings_and_data_script()).
	 *
	 * @var array<int, string>
	 */
	private const CATEGORIES = [
		'ad_personalization',
		'ad_storage',
		'ad_user_data',
		'analytics_storage',
		'personalization_storage',
		'functionality_storage',
		'security_storage',
	];

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * Registers the default `gtmkit_default` signal source on the
	 * `gtmkit_consent_signal_sources` filter at priority 1, so user
	 * code at the default WordPress filter priority (10) can override
	 * or remove it via the same filter.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;

		// add_filter dedupes [object, method] callbacks per spl_object_hash,
		// so re-constructing the registry within a single request is safe.
		add_filter( 'gtmkit_consent_signal_sources', [ $this, 'register_default_source' ], 1 );
	}

	/**
	 * Hook callback: register the default signal source.
	 *
	 * Wraps the existing legacy filters so the legacy path keeps working
	 * when no override source is registered:
	 *
	 *  - `is_active` returns the result of `gtmkit_consent_default_settings_enabled`.
	 *  - `read`      returns the result of `gtmkit_consent_default_state`.
	 *  - `read_default` returns the same, since the defaults never read the request.
	 *
	 * @param mixed $sources Registry passed through the filter chain.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function register_default_source( $sources ): array {
		if ( ! is_array( $sources ) ) {
			$sources = [];
		}

		$options = $this->options;

		// The defaults come from the settings alone, never from the request,
		// so they are also what a visitor who has not answered gets.
		$read = static function () use ( $options ): array {
			/**
			 * Per-category Consent Mode v2 default state.
			 *
			 * @param array<string, 'granted'|'denied'> $consent_defaults The seven-category state.
			 */
			$consent_defaults = apply_filters(
				'gtmkit_consent_default_state',
				[
					'ad_personalization'      => $options->get( 'general', 'gcm_ad_personalization' ) ? 'granted' : 'denied',
					'ad_storage'              => $options->get( 'general', 'gcm_ad_storage' ) ? 'granted' : 'denied',
					'ad_user_data'            => $options->get( 'general', 'gcm_ad_user_data' ) ? 'granted' : 'denied',
					'analytics_storage'       => $options->get( 'general', 'gcm_analytics_storage' ) ? 'granted' : 'denied',
					'personalization_storage' => $options->get( 'general', 'gcm_personalization_storage' ) ? 'granted' : 'denied',
					'functionality_storage'   => $options->get( 'general', 'gcm_functionality_storage' ) ? 'granted' : 'denied',
					'security_storage'        => $options->get( 'general', 'gcm_security_storage' ) ? 'granted' : 'denied',
				]
			);

			return self::normalize_state( is_array( $consent_defaults ) ? $consent_defaults : [] );
		};

		$sources[ self::DEFAULT_SOURCE_ID ] = [
			'id'           => self::DEFAULT_SOURCE_ID,
			'priority'     => self::DEFAULT_SOURCE_PRIORITY,
			'is_active'    => static function () use ( $options ): bool {
				return (bool) apply_filters(
					'gtmkit_consent_default_settings_enabled',
					(bool) $options->get( 'general', 'gcm_default_settings' )
				);
			},
			'read'         => $read,
			'read_default' => $read,
		];

		return $sources;
	}

	/**
	 * Resolve the currently-active signal source.
	 *
	 * Iterates registered sources, filters by `is_active() === true`,
	 * sorts by `priority` descending, returns the first descriptor.
	 * Returns null if no source claims active.
	 *
	 * @return array<string, mixed>|null Active source descriptor or null.
	 */
	public function resolve(): ?array {
		/**
		 * Registry of consent signal sources. Highest-priority active source wins.
		 *
		 * @param array<string, array<string, mixed>> $sources Map keyed by source id.
		 */
		$sources = apply_filters( 'gtmkit_consent_signal_sources', [] );
		if ( ! is_array( $sources ) ) {
			return null;
		}

		$active = [];
		foreach ( $sources as $descriptor ) {
			if ( ! self::is_valid_descriptor( $descriptor ) ) {
				continue;
			}
			if ( true === (bool) call_user_func( $descriptor['is_active'] ) ) {
				$active[] = $descriptor;
			}
		}

		if ( empty( $active ) ) {
			return null;
		}

		usort(
			$active,
			static fn( array $a, array $b ): int => (int) $b['priority'] <=> (int) $a['priority']
		);

		return $active[0];
	}

	/**
	 * Read consent state from the active source.
	 *
	 * @return array<string, string>|null
	 *     Current state, or null if no source is active.
	 */
	public function read_state(): ?array {
		$source = $this->resolve();
		if ( null === $source ) {
			return null;
		}

		$state = call_user_func( $source['read'] );

		return self::normalize_state( is_array( $state ) ? $state : [] );
	}

	/**
	 * Read the state a visitor who has not answered gets from the active source.
	 *
	 * Never reads the request, so the answer is the same whoever asks. Null
	 * when no source is active, and when the active source cannot say what a
	 * new visitor gets: reading the current visitor instead would answer a
	 * different question.
	 *
	 * @return array<string, string>|null
	 */
	public function read_default_state(): ?array {
		$source = $this->resolve();
		if ( null === $source || ! self::has_default_reader( $source ) ) {
			return null;
		}

		$state = call_user_func( $source['read_default'] );

		return self::normalize_state( is_array( $state ) ? $state : [] );
	}

	/**
	 * Whether the active source can say what a visitor who has not answered gets.
	 *
	 * True when no source is active, since there is then no state to report
	 * and nothing is missing.
	 *
	 * @return bool
	 */
	public function is_default_state_available(): bool {
		$source = $this->resolve();

		return null === $source || self::has_default_reader( $source );
	}

	/**
	 * Whether a source descriptor carries a reader for the new-visitor state.
	 *
	 * @param array<string, mixed> $descriptor A valid source descriptor.
	 *
	 * @return bool
	 */
	private static function has_default_reader( array $descriptor ): bool {
		return isset( $descriptor['read_default'] ) && is_callable( $descriptor['read_default'] );
	}

	/**
	 * Validate that an entry returned through the filter is shaped like a
	 * source descriptor. Silently drops malformed entries instead of fataling.
	 *
	 * @param mixed $descriptor The candidate descriptor.
	 */
	private static function is_valid_descriptor( $descriptor ): bool {
		if ( ! is_array( $descriptor ) ) {
			return false;
		}
		if ( ! isset( $descriptor['id'], $descriptor['priority'], $descriptor['is_active'], $descriptor['read'] ) ) {
			return false;
		}
		if ( ! is_string( $descriptor['id'] ) ) {
			return false;
		}
		if ( ! is_callable( $descriptor['is_active'] ) || ! is_callable( $descriptor['read'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Coerce a state array to the canonical seven-category shape with
	 * `granted`/`denied` values. Missing keys default to `denied`,
	 * unknown values fall back to `denied`. Keeps the consent emission
	 * resilient when a misbehaving source returns a partial array.
	 *
	 * @param array<string, mixed> $state Raw state from a source.
	 *
	 * @return array<string, string>
	 */
	private static function normalize_state( array $state ): array {
		$normalized = [];
		foreach ( self::CATEGORIES as $category ) {
			$value                   = $state[ $category ] ?? 'denied';
			$normalized[ $category ] = ( $value === 'granted' ) ? 'granted' : 'denied';
		}
		return $normalized;
	}
}
