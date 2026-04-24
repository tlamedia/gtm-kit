<?php
/**
 * Integration tests for the Consent Mode v2 default-state emission in the
 * gtm-kit inline settings script.
 *
 * Pattern: boots WordPress via `yoast/wp-test-utils`, configures the
 * `gcm_*` option set, runs the enqueue method, and extracts the inline
 * script from the WP scripts registry to assert on the emitted consent
 * payload. Covers the three states most load-bearing for roadmap item
 * #49 Consent Mode v2 Defaults:
 *
 *  1. Opt-out: `gcm_default_settings = 0` emits no consent block.
 *  2. Denied-by-default: all seven consent categories default to `denied`
 *     (the GDPR-compliant baseline).
 *  3. Fully granted: all seven consent categories default to `granted`
 *     (the open-consent development baseline).
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
 * Covers the Consent Mode v2 default-state emission.
 */
final class ConsentDefaultsTest extends WP_UnitTestCase {

	/**
	 * Seven consent categories written by the inline script.
	 *
	 * @var array<int, string>
	 */
	private const CONSENT_OPTION_KEYS = [
		'gcm_ad_personalization',
		'gcm_ad_storage',
		'gcm_ad_user_data',
		'gcm_analytics_storage',
		'gcm_personalization_storage',
		'gcm_functionality_storage',
		'gcm_security_storage',
	];

	/**
	 * Reset the cached settings and the `gtmkit` script handle between tests so
	 * each one observes a clean WP scripts registry.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
	}

	/**
	 * When `gcm_default_settings` is off, no consent block is emitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_no_consent_block_when_gcm_default_settings_is_off(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 0 );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringNotContainsString( "gtag('consent', 'default'", $inline, 'Consent-default gtag() call must not appear when the feature is off.' );
	}

	/**
	 * With `gcm_default_settings` on and every category flag unset, all seven
	 * categories emit `'denied'` — the GDPR-compliant baseline.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_all_consent_categories_denied_by_default(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		foreach ( self::CONSENT_OPTION_KEYS as $key ) {
			$options->set_option( 'general', $key, 0 );
		}

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringContainsString( "gtag('consent', 'default'", $inline, 'Consent-default gtag() call is missing.' );
		$this->assertSame( 7, substr_count( $inline, "'denied'" ), 'All seven consent categories must default to denied.' );
		$this->assertSame( 0, substr_count( $inline, "'granted'" ), 'No category may default to granted in the denied-by-default baseline.' );
	}

	/**
	 * With `gcm_default_settings` on and every category flag set, all seven
	 * categories emit `'granted'`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_all_consent_categories_granted_when_flags_enabled(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		foreach ( self::CONSENT_OPTION_KEYS as $key ) {
			$options->set_option( 'general', $key, 1 );
		}

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$inline = $this->extract_inline_script();
		$this->assertStringContainsString( "gtag('consent', 'default'", $inline, 'Consent-default gtag() call is missing.' );
		$this->assertSame( 7, substr_count( $inline, "'granted'" ), 'All seven consent categories must be granted when every flag is on.' );
		$this->assertSame( 0, substr_count( $inline, "'denied'" ), 'No category may emit denied when every flag is on.' );
	}

	/**
	 * Pull the `before`-position inline script for the `gtmkit` handle out of
	 * the WP scripts registry. `wp_add_inline_script()` stores entries as an
	 * array — concatenate them so callers can assert on a single string.
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
