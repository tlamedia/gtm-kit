<?php
/**
 * Integration tests for a settings save that validation rejects.
 *
 * Saves go through the real `set-options` route and the real Options
 * validation, so the error the settings app receives is the one a user
 * would see.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::set_options()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Options\Options;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Covers how a rejected save is reported, and that nothing is written.
 */
final class SaveValidationTest extends WP_UnitTestCase {

	/**
	 * Seed known settings, sign in an administrator, and start a fresh REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option(
			Options::OPTION_NAME,
			[
				'general' => [
					'gtm_id'      => 'GTM-OLD1234',
					'console_log' => false,
				],
			]
		);

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
	 * A value a validator refuses is answered with a 400 naming the setting
	 * and the reason, and the stored settings are left as they were.
	 */
	public function test_a_rejected_value_is_reported_and_nothing_is_written(): void {
		$response = $this->save(
			[
				'gtm_id'      => 'not-a-container',
				'console_log' => true,
			]
		);
		$error    = $response->as_error();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'gtmkit_settings_rejected', $error->get_error_code() );
		$this->assertStringContainsString( 'were not saved', $error->get_error_message() );
		$this->assertStringContainsString( 'gtm_id (value not accepted)', $error->get_error_message() );
		$this->assertArrayHasKey( 'general.gtm_id', $error->get_error_data()['params'] );

		$stored = get_option( Options::OPTION_NAME );
		$this->assertSame( 'GTM-OLD1234', $stored['general']['gtm_id'] );
		$this->assertFalse( $stored['general']['console_log'], 'A valid change in the same save is not written either.' );
	}

	/**
	 * A value of the wrong type names the type as the reason, and several
	 * rejected settings are all listed.
	 */
	public function test_every_rejected_setting_is_named_with_its_reason(): void {
		$error = $this->save(
			[
				'gtm_id'      => 'not-a-container',
				'console_log' => 'on',
			]
		)->as_error();

		$this->assertStringContainsString( '2 settings have values', $error->get_error_message() );
		$this->assertStringContainsString( 'gtm_id (value not accepted)', $error->get_error_message() );
		$this->assertStringContainsString( 'console_log (wrong type of value)', $error->get_error_message() );
		$this->assertSame( [ 'general.gtm_id', 'general.console_log' ], array_keys( $error->get_error_data()['params'] ) );
	}

	/**
	 * A valid save still answers with the saved settings in the shape the app reads.
	 */
	public function test_a_valid_save_is_unchanged(): void {
		$response = $this->save( [ 'gtm_id' => 'GTM-NEW1234' ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'GTM-NEW1234', $response->get_data()['data']['general']['gtm_id'] );
	}
}
