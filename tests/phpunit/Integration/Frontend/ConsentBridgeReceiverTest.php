<?php
/**
 * Integration tests for the consent bridge receiver in the gtm-kit inline
 * settings script.
 *
 * GTM Kit's consent bridge template, a Google Tag Manager template, reports
 * Google's consent state into `window.gtmkit.consentBridge.report()`. The
 * receiver has to be on every page whether or not GTM Kit emits its own
 * Consent Mode defaults, and it has to be static, identical for every
 * visitor, so a cached page is right for everyone. What `report()` does is
 * covered by the JavaScript tests, which run the receiver lifted out of this
 * script.
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
 * Covers the consent bridge receiver emission.
 */
final class ConsentBridgeReceiverTest extends WP_UnitTestCase {

	/**
	 * The first line of the receiver, as the head script writes it.
	 *
	 * @var string
	 */
	private const RECEIVER_START = 'window.gtmkit.consentBridge = window.gtmkit.consentBridge || {';

	/**
	 * The line that closes the receiver.
	 *
	 * @var string
	 */
	private const RECEIVER_END = "\n\t\t};";

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
	 * The Consent Mode default settings toggle, off and on.
	 *
	 * @return array<string, array{0: int}>
	 */
	public function consent_mode_settings(): array {
		return [
			'Consent Mode defaults off' => [ 0 ],
			'Consent Mode defaults on'  => [ 1 ],
		];
	}

	/**
	 * The receiver is defined once, whether or not GTM Kit emits Consent Mode.
	 *
	 * @dataProvider consent_mode_settings
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 *
	 * @param int $gcm_default_settings The Consent Mode default settings toggle.
	 */
	public function test_receiver_is_defined_once_with_consent_mode_off_and_on( int $gcm_default_settings ): void {
		$inline = $this->emit_head_script( $gcm_default_settings );

		$this->assertSame( 1 === $gcm_default_settings, str_contains( $inline, 'window.gtmkit.consent = {' ), 'GTM Kit consent state must follow the Consent Mode toggle.' );
		$this->assertSame( 1, substr_count( $inline, self::RECEIVER_START ), 'The receiver must be defined exactly once.' );
		$this->assertStringContainsString( 'report: function (type, granted, basis)', $this->extract_receiver( $inline ) );
		$this->assertStringContainsString( "'gtmkit:consent:bridged'", $this->extract_receiver( $inline ) );
	}

	/**
	 * The receiver is the same text with Consent Mode off and on, and it
	 * contains nothing that would make it a Consent Mode writer.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_settings_and_data_script
	 */
	public function test_receiver_is_static_and_only_observes(): void {
		$off = $this->extract_receiver( $this->emit_head_script( 0 ) );

		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );

		$on = $this->extract_receiver( $this->emit_head_script( 1 ) );

		$this->assertSame( $off, $on, 'The receiver must be identical whether or not Consent Mode is on.' );
		$this->assertStringNotContainsString( '<?php', $on, 'The receiver must be static JavaScript.' );
		$this->assertStringNotContainsString( 'gtag(', $on, 'The receiver must never call gtag.' );
		$this->assertStringNotContainsString( 'consent.state', $on, 'The receiver must never touch GTM Kit consent state.' );
		$this->assertStringNotContainsString( 'gtmkit:consent:updated', $on, 'The receiver must never announce a GTM Kit consent change.' );
		$this->assertStringNotContainsString( '.push(', $on, 'The receiver must never push to a data layer.' );
	}

	/**
	 * Emit the head script with the given Consent Mode toggle and return it.
	 *
	 * @param int $gcm_default_settings The Consent Mode default settings toggle.
	 * @return string The inline script.
	 */
	private function emit_head_script( int $gcm_default_settings ): string {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', $gcm_default_settings );

		( new Frontend( $options ) )->enqueue_settings_and_data_script();

		$data = wp_scripts()->get_data( 'gtmkit', 'before' );
		if ( is_array( $data ) ) {
			return implode( '', $data );
		}

		return (string) $data;
	}

	/**
	 * The receiver's text, from its first line to the line that closes it.
	 *
	 * @param string $inline The inline script.
	 * @return string The receiver.
	 */
	private function extract_receiver( string $inline ): string {
		$start = strpos( $inline, self::RECEIVER_START );
		$this->assertNotFalse( $start, 'The receiver is missing from the head script.' );

		$end = strpos( $inline, self::RECEIVER_END, (int) $start );
		$this->assertNotFalse( $end, 'The receiver is not closed as expected.' );

		return substr( $inline, (int) $start, ( (int) $end + strlen( self::RECEIVER_END ) ) - (int) $start );
	}
}
