<?php
/**
 * Integration tests for a settings save that switches the Google tag gateway on.
 *
 * Saves go through the real `set-options` route, so the error the settings
 * app receives is the one a user would see.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::set_options()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\AdminAPI;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use TLA_Media\GTM_Kit\Options\Processor\GoogleTagGatewayProcessor;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * A switch-on the site cannot serve is refused out loud, with nothing written.
 *
 * Without this the save answered success and the settings screen adopted the
 * returned settings, so the toggle flipped back off with no word of why.
 */
final class GoogleTagGatewaySaveTest extends WP_UnitTestCase {

	/**
	 * Seed a site tagging through a custom domain, sign in an administrator,
	 * and start a fresh REST server.
	 *
	 * The seed goes through the shared Options instance rather than straight
	 * into the database, because that instance keeps its own copy of the
	 * settings across the per-test rollback, and the save reads that copy.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		OptionsFactory::get_instance()->set(
			[
				'general' => [
					'gtm_id'             => 'GTM-OLD1234',
					'console_log'        => false,
					'sgtm_domain'        => 'gtm.example.com',
					'google_tag_gateway' => false,
				],
			]
		);

		delete_option( GoogleTagGatewayHealth::OPTION );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Save settings through the route the settings app uses.
	 *
	 * @param array<string, mixed> $general The general settings to submit.
	 *
	 * @return WP_REST_Response
	 */
	private function save( array $general ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/gtmkit/v1/set-options' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( [ 'general' => $general ] ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Switching on beside a custom domain is refused, says why, and writes nothing.
	 *
	 * @return void
	 */
	public function test_a_refused_switch_on_is_reported_and_nothing_is_written(): void {
		$response = $this->save(
			[
				'gtm_id'             => 'GTM-OLD1234',
				'console_log'        => true,
				'sgtm_domain'        => 'gtm.example.com',
				'google_tag_gateway' => true,
			]
		);
		$error    = $response->as_error();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'gtmkit_gateway_refused', $error->get_error_code() );
		$this->assertStringContainsString( 'server-side tagging domain', $error->get_error_message() );
		$this->assertSame(
			GoogleTagGatewayProcessor::REFUSED_SGTM_DOMAIN,
			$error->get_error_data()['params']['general.google_tag_gateway']
		);

		$stored = get_option( Options::OPTION_NAME );
		$this->assertFalse( $stored['general']['google_tag_gateway'] );
		$this->assertFalse( $stored['general']['console_log'], 'A valid change in the same save is not written either.' );
	}

	/**
	 * A save that leaves the gateway alone is not held up by it.
	 *
	 * @return void
	 */
	public function test_a_save_that_leaves_the_gateway_off_goes_through(): void {
		$response = $this->save(
			[
				'gtm_id'             => 'GTM-OLD1234',
				'console_log'        => true,
				'sgtm_domain'        => 'gtm.example.com',
				'google_tag_gateway' => false,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( get_option( Options::OPTION_NAME )['general']['console_log'] );
	}

	/**
	 * Each reason for a refusal is explained on its own terms.
	 *
	 * @return void
	 */
	public function test_each_refusal_reason_is_explained_on_its_own_terms(): void {
		$domain  = AdminAPI::get_gateway_refusal_message( GoogleTagGatewayProcessor::REFUSED_SGTM_DOMAIN );
		$service = AdminAPI::get_gateway_refusal_message( GoogleTagGatewayProcessor::REFUSED_SERVICE );
		$script  = AdminAPI::get_gateway_refusal_message( GoogleTagGatewayProcessor::REFUSED_SCRIPT );

		$this->assertStringContainsString( 'server-side tagging domain', $domain );
		$this->assertStringContainsString( 'outgoing', $service );
		$this->assertStringContainsString( 'your own domain', $script );
		$this->assertStringContainsString( 'published', $script, 'An unpublished container fails the check too, and is not the host\'s doing.' );
		$this->assertCount( 3, array_unique( [ $domain, $service, $script ] ) );
	}

	/**
	 * A string "false" is not taken for a switch-on.
	 *
	 * With a custom domain set, a save mistaken for a switch-on is refused by
	 * the gateway check, after up to two blocking network requests, with an
	 * explanation about the gateway. The value is instead left to the
	 * validator, which does not accept the string and names the setting.
	 *
	 * @return void
	 */
	public function test_a_string_false_is_not_taken_for_a_switch_on(): void {
		$response = $this->save(
			[
				'gtm_id'             => 'GTM-OLD1234',
				'console_log'        => true,
				'sgtm_domain'        => 'gtm.example.com',
				'google_tag_gateway' => 'false',
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'gtmkit_settings_rejected', $response->as_error()->get_error_code() );
	}
}
