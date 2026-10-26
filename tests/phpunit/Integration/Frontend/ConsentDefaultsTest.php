<?php
/**
 * Integration tests for the Consent Mode v2 default-state emission in the
 * gtm-kit inline settings script.
 *
 * Pattern: boots WordPress via `yoast/wp-test-utils`, configures the
 * `gcm_*` option set, runs the enqueue method, and extracts the inline
 * script from the WP scripts registry to assert on the emitted consent
 * payload. Covers the three load-bearing Consent Mode v2 default states:
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
		// Each category is rendered twice — once inside `gtag('consent', 'default', ...)`
		// and once inside the `window.gtmkit.consent.state` surface — so the
		// expected count is 7 categories × 2 emissions = 14.
		$this->assertSame( 14, substr_count( $this->extract_consent_default_blocks( $inline ), "'denied'" ), 'All seven consent categories must default to denied (twice, in the gtag block and the state surface).' );
		$this->assertSame( 0, substr_count( $this->extract_consent_default_blocks( $inline ), "'granted'" ), 'No category may default to granted in the denied-by-default baseline.' );
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
		// Each category is rendered twice — once inside the gtag default
		// block and once inside the window.gtmkit.consent.state surface.
		$this->assertSame( 14, substr_count( $this->extract_consent_default_blocks( $inline ), "'granted'" ), 'All seven consent categories must be granted when every flag is on (twice, in the gtag block and the state surface).' );
		$this->assertSame( 0, substr_count( $this->extract_consent_default_blocks( $inline ), "'denied'" ), 'No category may emit denied when every flag is on.' );
	}

	/**
	 * A `'denied'` literal elsewhere in the head script does not leak into the
	 * consent-default count.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_unrelated_denied_literal_does_not_affect_consent_count(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		foreach ( self::CONSENT_OPTION_KEYS as $key ) {
			$options->set_option( 'general', $key, 1 );
		}

		( new Frontend( $options ) )->enqueue_settings_and_data_script();
		wp_add_inline_script( 'gtmkit', "var unrelatedStatus = 'denied';", 'before' );

		$inline = $this->extract_inline_script();
		$this->assertSame( 1, substr_count( $inline, "'denied'" ), 'The unrelated literal must be present in the head script.' );
		$this->assertSame( 0, substr_count( $this->extract_consent_default_blocks( $inline ), "'denied'" ), 'Only the consent-default blocks may be counted.' );
		$this->assertSame( 14, substr_count( $this->extract_consent_default_blocks( $inline ), "'granted'" ) );
	}

	/**
	 * Return only the consent-default literals of the head script: the object
	 * passed to `gtag('consent', 'default', ...)` and the
	 * `window.gtmkit.consent.state` object. Counting inside these keeps the
	 * assertions blind to unrelated `'denied'` / `'granted'` literals elsewhere
	 * in the script.
	 *
	 * @param string $inline The full inline head script.
	 *
	 * @return string
	 */
	private function extract_consent_default_blocks( string $inline ): string {
		preg_match_all( "/gtag\\('consent', 'default', (\\{[^}]*\\})/", $inline, $gtag_blocks );
		preg_match_all( '/window\\.gtmkit\\.consent = \\{\\s*state: (\\{[^}]*\\})/', $inline, $state_blocks );

		return implode( "\n", array_merge( $gtag_blocks[1], $state_blocks[1] ) );
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
