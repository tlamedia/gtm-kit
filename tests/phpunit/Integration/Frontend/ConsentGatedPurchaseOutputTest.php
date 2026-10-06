<?php
/**
 * Integration tests for how a purchase with contact details is printed.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_datalayer_content()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * The page never carries contact details inside the event it pushes directly.
 */
final class ConsentGatedPurchaseOutputTest extends WP_UnitTestCase {

	/**
	 * The payload the data layer filter hands over.
	 *
	 * @var array<string, mixed>
	 */
	private array $payload = [];

	/**
	 * Reset the script registry and feed the data layer a fixed payload.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		wp_scripts()->remove( 'gtmkit' );
		wp_scripts()->remove( 'gtmkit-datalayer' );

		OptionsFactory::get_instance()->set_option( 'general', 'gtm_id', 'GTM-TEST123' );

		add_filter( 'gtmkit_datalayer_content', [ $this, 'payload' ], 999 );
	}

	/**
	 * Remove the payload filter.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'gtmkit_datalayer_content', [ $this, 'payload' ], 999 );

		parent::tear_down();
	}

	/**
	 * Filter callback returning the test payload.
	 *
	 * @return array<string, mixed>
	 */
	public function payload(): array {
		return $this->payload;
	}

	/**
	 * The inline script printed for the data layer.
	 *
	 * @return string
	 */
	private function printed(): string {
		( new Frontend( OptionsFactory::get_instance() ) )->enqueue_datalayer_content();

		return implode( "\n", (array) wp_scripts()->get_data( 'gtmkit-datalayer', 'before' ) );
	}

	/**
	 * A purchase with contact details is printed through the consent gate, with the details apart.
	 *
	 * @return void
	 */
	public function test_contact_details_are_printed_apart_from_the_event(): void {
		$this->payload = [
			'event'     => 'purchase',
			'ecommerce' => [
				'transaction_id' => '837',
				'customer'       => [
					'id'            => 7,
					'billing_email' => 'ada@example.test',
				],
			],
			'user_data' => [ 'sha256_email_address' => 'hashed-email' ],
		];

		$script = $this->printed();

		$this->assertStringContainsString( 'pushWhenConsented', $script );
		$this->assertStringNotContainsString( 'window.gtmkit.events.push( {', $script );

		preg_match( '/pushWhenConsented\( (\{.*\}), "dataLayer", (\{.*\}), (\{.*\}) \);/', $script, $call );
		$this->assertCount( 4, $call, 'The event, the held details and the site-wide settings are printed as separate arguments.' );

		$event = json_decode( $call[1], true );
		$held  = json_decode( $call[2], true );

		$this->assertArrayNotHasKey( 'user_data', $event );
		$this->assertSame( [ 'id' => 7 ], $event['ecommerce']['customer'] );
		$this->assertSame( 'hashed-email', $held['user_data']['sha256_email_address'] );
		$this->assertSame( 'ada@example.test', $held['ecommerce']['customer']['billing_email'] );
		$this->assertSame( [ 'unknownConsent' => 'allow' ], json_decode( $call[3], true ) );
	}

	/**
	 * A site can make unknown consent withhold the details, and only the two known values are printed.
	 *
	 * @return void
	 */
	public function test_the_unknown_consent_setting_reaches_the_page(): void {
		$this->payload = [
			'event'     => 'purchase',
			'user_data' => [ 'sha256_email_address' => 'hashed-email' ],
		];

		add_filter( 'gtmkit_consent_unknown', static fn() => 'deny' );
		$this->assertStringContainsString( '{"unknownConsent":"deny"}', $this->printed() );

		wp_scripts()->remove( 'gtmkit-datalayer' );
		remove_all_filters( 'gtmkit_consent_unknown' );
		add_filter( 'gtmkit_consent_unknown', static fn() => 'something else' );
		$this->assertStringContainsString( '{"unknownConsent":"allow"}', $this->printed() );

		remove_all_filters( 'gtmkit_consent_unknown' );
	}

	/**
	 * A purchase without contact details, and every other payload, is pushed as before.
	 *
	 * @return void
	 */
	public function test_a_payload_without_contact_details_is_pushed_as_before(): void {
		$this->payload = [
			'event'     => 'purchase',
			'ecommerce' => [ 'transaction_id' => '837' ],
		];

		$script = $this->printed();

		$this->assertStringNotContainsString( 'pushWhenConsented', $script );
		$this->assertStringContainsString( 'window.gtmkit.events.push( {"event":"purchase"', $script );
	}
}
