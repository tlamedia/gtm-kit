<?php
/**
 * Integration tests for the consent developer filter and action hooks.
 *
 * Covers:
 *
 *  - The `gtmkit_consent_updated` action fires with the documented
 *    signature when a registered listener calls it.
 *  - The `gtmkit_event_should_defer` filter is invoked by the deferral
 *    gate at the dataLayer push helper, defaults to false (events push),
 *    and skips the push when a listener returns true.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Frontend\EventDeferralGate}
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content}
 *  - {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_delay_js_script}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry;
use TLA_Media\GTM_Kit\Frontend\EventDeferralGate;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers the new action `gtmkit_consent_updated` and the new filter
 * `gtmkit_event_should_defer`.
 */
final class FilterHooksTest extends WP_UnitTestCase {

	/**
	 * Reset the WP scripts registry, the cached gtmkit settings, and the
	 * consent-related filters so each test sees a clean slate.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
		wp_scripts()->remove( 'gtmkit-datalayer' );
		wp_scripts()->remove( 'gtmkit-delay' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_event_should_defer' );
		remove_all_filters( 'gtmkit_datalayer_content' );
		remove_all_actions( 'gtmkit_consent_updated' );
	}

	/**
	 * The `gtmkit_consent_updated` action fires with the documented
	 * `($new_state, $source_id)` signature.
	 *
	 * @covers ::do_action
	 */
	public function test_consent_updated_action_fires_with_expected_signature(): void {
		$captured = [];
		add_action(
			'gtmkit_consent_updated',
			static function ( $new_state, $source_id ) use ( &$captured ): void {
				$captured = [ $new_state, $source_id ];
			},
			10,
			2
		);

		do_action(
			'gtmkit_consent_updated',
			[
				'analytics_storage' => 'granted',
				'ad_storage'        => 'denied',
			],
			'wp_consent_api'
		);

		$this->assertCount( 2, $captured, 'Action listener must receive both arguments.' );
		$this->assertSame( 'granted', $captured[0]['analytics_storage'] );
		$this->assertSame( 'denied', $captured[0]['ad_storage'] );
		$this->assertSame( 'wp_consent_api', $captured[1] );
	}

	/**
	 * The `gtmkit_event_should_defer` filter defaults to false — events
	 * push as normal.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EventDeferralGate::should_defer
	 */
	public function test_event_should_defer_defaults_to_false(): void {
		$options  = OptionsFactory::get_instance();
		$registry = new ConsentSignalSourceRegistry( $options );
		$gate     = new EventDeferralGate( $registry );

		$this->assertFalse( $gate->should_defer( 'view_item', [ 'event' => 'view_item' ] ) );
	}

	/**
	 * The deferral gate calls the filter with all four documented
	 * arguments: ($should_defer, $event_name, $event_payload, $consent_state).
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EventDeferralGate::should_defer
	 */
	public function test_deferral_filter_receives_documented_signature(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', 1 );
		$options->set_option( 'general', 'gcm_analytics_storage', 1 );
		$registry = new ConsentSignalSourceRegistry( $options );
		$gate     = new EventDeferralGate( $registry );

		$captured = [];
		add_filter(
			'gtmkit_event_should_defer',
			static function ( $should_defer, $event_name, $event_payload, $consent_state ) use ( &$captured ) {
				$captured = [ $should_defer, $event_name, $event_payload, $consent_state ];
				return $should_defer;
			},
			10,
			4
		);

		$gate->should_defer(
			'add_to_cart',
			[
				'event' => 'add_to_cart',
				'value' => 42,
			]
		);

		$this->assertSame( false, $captured[0], 'Default $should_defer must be false.' );
		$this->assertSame( 'add_to_cart', $captured[1] );
		$this->assertSame(
			[
				'event' => 'add_to_cart',
				'value' => 42,
			],
			$captured[2]
		);
		$this->assertSame( 'granted', $captured[3]['analytics_storage'], 'Consent state passed to filter must reflect active source.' );
	}

	/**
	 * When the filter returns true, the dataLayer push helper skips the
	 * push entirely — no inline script is registered.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content
	 */
	public function test_returning_true_from_filter_skips_datalayer_push(): void {
		$options = OptionsFactory::get_instance();

		add_filter(
			'gtmkit_datalayer_content',
			static fn() => [
				'event' => 'purchase',
				'value' => 99,
			]
		);
		add_filter( 'gtmkit_event_should_defer', '__return_true' );

		( new Frontend( $options ) )->enqueue_datalayer_content();

		$this->assertFalse(
			wp_scripts()->query( 'gtmkit-datalayer' ),
			'Deferred event must not register the gtmkit-datalayer inline script.'
		);
	}

	/**
	 * Default behavior (filter returns false): the dataLayer push helper
	 * registers the inline script and the payload appears in it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content
	 */
	public function test_default_path_pushes_datalayer_content(): void {
		$options = OptionsFactory::get_instance();

		add_filter(
			'gtmkit_datalayer_content',
			static fn() => [
				'event' => 'view_item',
				'value' => 7,
			]
		);

		( new Frontend( $options ) )->enqueue_datalayer_content();

		$inline = $this->extract_inline_script( 'gtmkit-datalayer' );
		$this->assertStringContainsString( '"event":"view_item"', $inline, 'Default path must emit the event payload.' );
	}

	/**
	 * The delay-js push site also honors the deferral gate.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_delay_js_script
	 */
	public function test_returning_true_from_filter_skips_delay_js_push(): void {
		$options = OptionsFactory::get_instance();

		add_filter(
			'gtmkit_event_should_defer',
			static function ( $should_defer, $event_name ) {
				return $event_name === 'load_delayed_js' ? true : $should_defer;
			},
			10,
			2
		);

		( new Frontend( $options ) )->enqueue_delay_js_script();

		$this->assertFalse(
			wp_scripts()->query( 'gtmkit-delay' ),
			'Deferred load_delayed_js must not register the gtmkit-delay inline script.'
		);
	}

	/**
	 * Pull the `before`-position inline script for a given handle.
	 *
	 * @param string $handle The script handle.
	 *
	 * @return string
	 */
	private function extract_inline_script( string $handle ): string {
		$data = wp_scripts()->get_data( $handle, 'before' );
		if ( is_array( $data ) ) {
			return implode( '', $data );
		}
		return (string) $data;
	}
}
