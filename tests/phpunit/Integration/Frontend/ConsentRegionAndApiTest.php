<?php
/**
 * Integration tests for the Consent Mode v2 additions shipped with
 * roadmap #49 Consent Mode v2 Defaults and Admin UI.
 *
 * Covers:
 *  - The `gcm_region` option's effect on the emitted
 *    `gtag('consent', 'default', ...)` payload (empty vs populated).
 *  - The passive `window.gtmkit.consent.update()` JS surface and the
 *    `gtmkit:consent:updated` CustomEvent dispatch, gated by the master
 *    toggle.
 *  - The invariant that `dataLayer.push` is never wrapped, under any
 *    setting combination.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers the Consent Mode v2 region parameter and the JS update surface.
 */
final class ConsentRegionAndApiTest extends WP_UnitTestCase {

	/**
	 * Reset the cached settings and the `gtmkit` script handle between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
	}

	/**
	 * When `gcm_region` is empty, the `'region'` key is absent from the
	 * emitted `gtag('consent', 'default', ...)` payload.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_region_key_absent_when_region_empty(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		$options->set_option( 'general', 'gcm_region', [] );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringContainsString( "gtag('consent', 'default'", $inline, 'Consent-default gtag() call is missing.' );
		$this->assertStringNotContainsString( "'region':", $inline, "No 'region' key may appear when gcm_region is empty." );
	}

	/**
	 * When `gcm_region` is populated, the emitted payload contains the
	 * region codes as a JSON array.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_region_key_emits_codes_when_populated(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		$options->set_option( 'general', 'gcm_region', [ 'DK', 'DE-BY' ] );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringContainsString( "'region':", $inline, "'region' key must appear when gcm_region is populated." );
		$this->assertStringContainsString( '"DK"', $inline, 'Region code DK must be emitted.' );
		$this->assertStringContainsString( '"DE-BY"', $inline, 'Region code DE-BY must be emitted.' );
	}

	/**
	 * When the master toggle is on, `window.gtmkit.consent.update` is
	 * exposed and the `gtmkit:consent:updated` CustomEvent is dispatched.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_consent_update_surface_present_when_master_toggle_on(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringContainsString( 'window.gtmkit.consent', $inline, 'JS update surface must be exposed when consent mode is on.' );
		$this->assertStringContainsString( "gtag('consent', 'update', state)", $inline, 'Update function must forward to gtag consent update.' );
		$this->assertStringContainsString( "'gtmkit:consent:updated'", $inline, 'Custom event name must be dispatched.' );
	}

	/**
	 * When the master toggle is off, GTM Kit must emit zero consent
	 * surface area — no defaults, no update API, no event dispatch.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_consent_update_surface_absent_when_master_toggle_off(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 0 );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringNotContainsString( 'window.gtmkit.consent', $inline, 'JS update surface must not leak when consent mode is off.' );
		$this->assertStringNotContainsString( 'gtmkit:consent:updated', $inline, 'Consent event name must not leak when consent mode is off.' );
	}

	/**
	 * Regression: under every supported setting combination the inline
	 * script must never wrap `dataLayer.push`. Wrapping it is incompatible
	 * with CMPs that push their own consent shapes to the dataLayer
	 * (Patterns B and C in the spec).
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_datalayer_push_is_never_wrapped(): void {
		$options = OptionsFactory::get_instance();

		$combinations = [
			[
				'gcm_default_settings' => 0,
				'gcm_region'           => [],
			],
			[
				'gcm_default_settings' => 1,
				'gcm_region'           => [],
			],
			[
				'gcm_default_settings' => 1,
				'gcm_region'           => [ 'DK', 'US-CA' ],
			],
		];

		foreach ( $combinations as $combo ) {
			wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
			wp_scripts()->remove( 'gtmkit' );

			foreach ( $combo as $key => $value ) {
				$options->set_option( 'general', $key, $value );
			}

			( new Frontend( $options ) )->enqueue_settings_and_data_script();

			$inline = $this->extract_inline_script();
			$this->assertStringNotContainsString( 'dataLayer.push =', $inline, 'dataLayer.push must not be reassigned.' );
			$this->assertStringNotContainsString( 'new Proxy(', $inline, 'dataLayer must not be wrapped in a Proxy.' );
			$this->assertStringNotContainsString( 'Proxy(window.dataLayer', $inline, 'dataLayer must not be wrapped in a Proxy.' );
		}
	}

	/**
	 * The `gtmkit_consent_region` filter can populate the region array
	 * even when the option is empty.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_consent_region_filter_overrides_option(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		$options->set_option( 'general', 'gcm_region', [] );

		$filter = static fn() => [ 'FR' ];
		add_filter( 'gtmkit_consent_region', $filter );

		try {
			( new Frontend( $options ) )->enqueue_settings_and_data_script();
			$inline = $this->extract_inline_script();
		} finally {
			remove_filter( 'gtmkit_consent_region', $filter );
		}

		$this->assertStringContainsString( "'region':", $inline, 'Filter-populated region must appear in the payload.' );
		$this->assertStringContainsString( '"FR"', $inline, 'Filter-populated region code must be emitted.' );
	}

	/**
	 * The `gtmkit_consent_default_settings_enabled` filter can
	 * force-enable emission when the admin toggle is off.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_consent_enabled_filter_force_on(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 0 );

		$filter = '__return_true';
		add_filter( 'gtmkit_consent_default_settings_enabled', $filter );

		try {
			( new Frontend( $options ) )->enqueue_settings_and_data_script();
			$inline = $this->extract_inline_script();
		} finally {
			remove_filter( 'gtmkit_consent_default_settings_enabled', $filter );
		}

		$this->assertStringContainsString( "gtag('consent', 'default'", $inline, 'Filter must be able to force-enable the consent block.' );
		$this->assertStringContainsString( 'window.gtmkit.consent', $inline, 'Filter must expose the JS update surface.' );
	}

	/**
	 * Pull the `before`-position inline script for the `gtmkit` handle.
	 *
	 * @return string
	 */
	private function extract_inline_script(): string {
		$data = wp_scripts()->get_data( 'gtmkit', 'before' );
		if ( is_array( $data ) ) {
			return implode( '', $data );
		}

		return (string) $data;
	}
}
