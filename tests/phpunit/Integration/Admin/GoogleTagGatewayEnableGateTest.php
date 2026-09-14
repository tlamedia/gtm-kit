<?php
/**
 * Integration tests for the gate on switching the Google tag gateway on.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use TLA_Media\GTM_Kit\Options\Processor\GoogleTagGatewayProcessor;
use WP_UnitTestCase;

/**
 * The setting can only be switched on when the gateway would actually work.
 *
 * Every case here stores a health result first, so the gate reads a standing
 * result rather than reaching the network from a test.
 */
final class GoogleTagGatewayEnableGateTest extends WP_UnitTestCase {

	/**
	 * The processor under test.
	 *
	 * @var GoogleTagGatewayProcessor
	 */
	private GoogleTagGatewayProcessor $processor;

	/**
	 * Reset the settings and the stored health result between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->processor = new GoogleTagGatewayProcessor();

		delete_option( GoogleTagGatewayHealth::OPTION );
		delete_option( GoogleTagGatewayHealth::NOTICE_DISMISSED_OPTION );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', '' );
		OptionsFactory::get_instance()->set_option( 'general', 'google_tag_gateway', false );
	}

	/**
	 * Leave the gateway off for the tests that follow.
	 *
	 * The shared Options instance keeps its copy of the settings across the
	 * per-test rollback, so a switch left on here would be read as the stored
	 * state by every later test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		OptionsFactory::get_instance()->set_option( 'general', 'google_tag_gateway', false );
		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', '' );

		parent::tear_down();
	}

	/**
	 * Store a health result.
	 *
	 * @param bool $service Whether the outbound service check passed.
	 * @param bool $script Whether the proxy reachability check passed.
	 *
	 * @return void
	 */
	private function store_health( bool $service, bool $script ): void {
		update_option(
			GoogleTagGatewayHealth::OPTION,
			[
				'schema'  => GoogleTagGatewayHealth::SCHEMA_VERSION,
				'service' => $service,
				'script'  => $script,
				'checked' => time(),
			],
			false
		);
	}

	/**
	 * Both checks passing lets the setting be switched on.
	 *
	 * @return void
	 */
	public function test_switching_on_is_allowed_when_both_checks_pass(): void {
		$this->store_health( true, true );

		$this->assertTrue( $this->processor->process( true, false ) );
	}

	/**
	 * A failing check refuses the switch.
	 *
	 * @dataProvider failing_check_provider
	 *
	 * @param bool $service Whether the outbound service check passed.
	 * @param bool $script Whether the proxy reachability check passed.
	 *
	 * @return void
	 */
	public function test_switching_on_is_refused_when_a_check_fails( bool $service, bool $script ): void {
		$this->store_health( $service, $script );

		$this->assertFalse( $this->processor->process( true, false ) );
	}

	/**
	 * The combinations in which the gateway cannot serve.
	 *
	 * @return array<string, array<int, bool>>
	 */
	public static function failing_check_provider(): array {
		return [
			'the server cannot reach the service' => [ false, true ],
			'the proxy is not reachable'          => [ true, false ],
			'neither check passes'                => [ false, false ],
		];
	}

	/**
	 * A custom server-side tagging domain refuses the switch on its own.
	 *
	 * @return void
	 */
	public function test_switching_on_is_refused_while_a_custom_sgtm_domain_is_set(): void {
		$this->store_health( true, true );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', 'gtm.example.com' );

		$this->assertFalse( $this->processor->process( true, false ) );
	}

	/**
	 * Switching off is always allowed.
	 *
	 * @return void
	 */
	public function test_switching_off_is_always_allowed(): void {
		$this->store_health( false, false );

		$this->assertFalse( $this->processor->process( false, true ) );
	}

	/**
	 * An already-enabled setting is left alone when the checks start failing.
	 *
	 * Later failures are handled by falling back at serving time, which keeps
	 * tracking up. Switching the setting off here would instead let a network
	 * blip during an unrelated settings save turn the feature off for good.
	 *
	 * @return void
	 */
	public function test_an_enabled_setting_survives_a_failing_check(): void {
		$this->store_health( false, false );

		$this->assertTrue( $this->processor->process( true, true ) );
	}

	/**
	 * Clearing the domain in the same save as switching on is judged on that save.
	 *
	 * The settings screen enables the gateway toggle as soon as the domain
	 * field is emptied, so both changes arrive together. Judging the switch
	 * against the stored domain would refuse it every time.
	 *
	 * @return void
	 */
	public function test_clearing_the_domain_in_the_same_save_allows_the_switch(): void {
		$this->store_health( true, true );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', 'gtm.example.com' );

		$this->assertTrue( $this->processor->process_with_options( true, false, [ 'general' => [ 'sgtm_domain' => '' ] ] ) );
	}

	/**
	 * The same, through a real save.
	 *
	 * @return void
	 */
	public function test_a_save_clearing_the_domain_and_switching_on_keeps_the_switch(): void {
		$this->store_health( true, true );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'sgtm_domain', 'gtm.example.com' );
		$options->set_option( 'general', 'google_tag_gateway', false );

		$options->set(
			[
				'general' => [
					'sgtm_domain'        => '',
					'google_tag_gateway' => true,
				],
			],
			false,
			false
		);

		$this->assertTrue( (bool) $options->get( 'general', 'google_tag_gateway' ) );
	}

	/**
	 * A refusal names what stands in the way.
	 *
	 * @return void
	 */
	public function test_a_refusal_names_its_reason(): void {
		$this->assertSame(
			GoogleTagGatewayProcessor::REFUSED_SGTM_DOMAIN,
			GoogleTagGatewayProcessor::refusal( [ 'general' => [ 'sgtm_domain' => 'gtm.example.com' ] ] )
		);

		$this->store_health( false, true );
		$this->assertSame( GoogleTagGatewayProcessor::REFUSED_SERVICE, GoogleTagGatewayProcessor::refusal( [] ) );

		$this->store_health( true, false );
		$this->assertSame( GoogleTagGatewayProcessor::REFUSED_SCRIPT, GoogleTagGatewayProcessor::refusal( [] ) );

		$this->store_health( true, true );
		$this->assertNull( GoogleTagGatewayProcessor::refusal( [] ) );
	}

	/**
	 * The container checked is the one the save sets, normalised like the save.
	 *
	 * The container ID and the gateway switch sit on the same screen, so a
	 * fresh setup sends both in one save. Checking the stored ID would find it
	 * empty and fall back to the proxy's own reply, which passes on a site
	 * where the tag itself never loads.
	 *
	 * @return void
	 */
	public function test_the_check_uses_the_container_id_the_save_sets(): void {
		$stored = (string) OptionsFactory::get_instance()->get( 'general', 'gtm_id' );

		$this->assertSame( 'GTM-ABC123', GoogleTagGatewayProcessor::container_id( [ 'general' => [ 'gtm_id' => 'abc123' ] ] ) );
		$this->assertSame( 'GTM-ABC123', GoogleTagGatewayProcessor::container_id( [ 'general' => [ 'gtm_id' => 'GTM-ABC123' ] ] ) );
		$this->assertSame( '', GoogleTagGatewayProcessor::container_id( [ 'general' => [ 'gtm_id' => '' ] ] ) );
		$this->assertSame( $stored, GoogleTagGatewayProcessor::container_id( [ 'general' => [] ] ) );
	}
}
