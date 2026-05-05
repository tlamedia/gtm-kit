<?php
/**
 * Integration tests for the consent signal source registry.
 *
 * Covers the three resolution behaviors third-party code relies on:
 *
 *  1. With no extra registrations, the registry resolves to the
 *     `gtmkit_default` source (the legacy fallback for backward
 *     compatibility with the original Consent Mode v2 emission).
 *  2. A higher-priority active source replaces the default.
 *  3. Inactive sources are skipped even at high priority, and removing
 *     the default falls through to the next-highest active source —
 *     or returns null when no source claims active.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers source registration, prioritization, and resolution.
 */
final class SignalSourceRegistryTest extends WP_UnitTestCase {

	/**
	 * Reset the signal source filter so each test sees a clean registry.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_consent_default_state' );
		remove_all_filters( 'gtmkit_consent_default_settings_enabled' );
	}

	/**
	 * With no extra sources registered, the registry resolves to
	 * `gtmkit_default` and reads through the legacy
	 * `gtmkit_consent_default_state` filter.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::resolve
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::read_state
	 */
	public function test_resolves_to_default_source_when_no_extras_registered(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		$options->set_option( 'general', 'gcm_analytics_storage', 1 );

		$registry = new ConsentSignalSourceRegistry( $options );

		$resolved = $registry->resolve();
		$this->assertIsArray( $resolved, 'Registry must resolve to a source when the master toggle is on.' );
		$this->assertSame( ConsentSignalSourceRegistry::DEFAULT_SOURCE_ID, $resolved['id'] );

		$state = $registry->read_state();
		$this->assertSame( 'granted', $state['analytics_storage'], 'Default source reads through to the option-backed state.' );
		$this->assertSame( 'denied', $state['ad_storage'], 'Categories without a flag default to denied.' );
	}

	/**
	 * Registering a higher-priority active source replaces the default
	 * in `read_state()`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::resolve
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::read_state
	 */
	public function test_higher_priority_source_wins(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		$registry = new ConsentSignalSourceRegistry( $options );

		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['custom_cmp'] = [
					'id'        => 'custom_cmp',
					'priority'  => 100,
					'is_active' => '__return_true',
					'read'      => static function (): array {
						return [
							'ad_personalization'      => 'granted',
							'ad_storage'              => 'granted',
							'ad_user_data'            => 'granted',
							'analytics_storage'       => 'granted',
							'personalization_storage' => 'granted',
							'functionality_storage'   => 'granted',
							'security_storage'        => 'granted',
						];
					},
				];
				return $sources;
			}
		);

		$resolved = $registry->resolve();
		$this->assertSame( 'custom_cmp', $resolved['id'] );

		$state = $registry->read_state();
		$this->assertSame( 'granted', $state['analytics_storage'] );
		$this->assertSame( 'granted', $state['ad_storage'] );
	}

	/**
	 * An inactive source is skipped even at high priority; the default
	 * source still wins.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::resolve
	 */
	public function test_inactive_source_is_skipped_even_at_high_priority(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		$registry = new ConsentSignalSourceRegistry( $options );

		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['standby_cmp'] = [
					'id'        => 'standby_cmp',
					'priority'  => 100,
					'is_active' => '__return_false',
					'read'      => static fn(): array => [],
				];
				return $sources;
			}
		);

		$resolved = $registry->resolve();
		$this->assertSame( ConsentSignalSourceRegistry::DEFAULT_SOURCE_ID, $resolved['id'] );
	}

	/**
	 * Unsetting the default source via the filter falls through to the
	 * next-highest active source.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::resolve
	 */
	public function test_unsetting_default_falls_through_to_next_active(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		$registry = new ConsentSignalSourceRegistry( $options );

		// Register a low-priority alternative.
		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['fallback_source'] = [
					'id'        => 'fallback_source',
					'priority'  => 5,
					'is_active' => '__return_true',
					'read'      => static fn(): array => [ 'analytics_storage' => 'granted' ],
				];
				return $sources;
			}
		);

		// Remove the default source after the registry's bootstrap callback runs.
		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				unset( $sources[ ConsentSignalSourceRegistry::DEFAULT_SOURCE_ID ] );
				return $sources;
			},
			20
		);

		$resolved = $registry->resolve();
		$this->assertSame( 'fallback_source', $resolved['id'] );
	}

	/**
	 * When no source claims active, `read_state()` returns null and
	 * Frontend can decide to suppress the consent block.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::read_state
	 */
	public function test_returns_null_when_no_active_sources(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 0 );

		$registry = new ConsentSignalSourceRegistry( $options );

		// Force the master toggle filter false so even the default is inactive.
		add_filter( 'gtmkit_consent_default_settings_enabled', '__return_false' );

		$this->assertNull( $registry->resolve() );
		$this->assertNull( $registry->read_state() );
	}

	/**
	 * Malformed descriptors returned through the filter are silently
	 * dropped instead of fataling.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::resolve
	 */
	public function test_malformed_descriptor_is_ignored(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		$registry = new ConsentSignalSourceRegistry( $options );

		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['broken']      = [ 'this' => 'is not a descriptor' ];
				$sources['also_broken'] = 'not even an array';
				return $sources;
			}
		);

		$resolved = $registry->resolve();
		$this->assertSame( ConsentSignalSourceRegistry::DEFAULT_SOURCE_ID, $resolved['id'] );
	}
}
