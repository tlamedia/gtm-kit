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
	 * A setting stored as the string 'true' or 'false', which the settings app
	 * sends back unchanged with every save, does not block the save, and the
	 * save stores it as a boolean.
	 */
	public function test_a_stored_string_boolean_is_saved_as_a_boolean(): void {
		$response = $this->save(
			[
				'gtm_id'      => 'GTM-NEW1234',
				'console_log' => 'false',
				'debug_log'   => 'true',
			]
		);

		$this->assertSame( 200, $response->get_status() );

		$stored = get_option( Options::OPTION_NAME );
		$this->assertSame( 'GTM-NEW1234', $stored['general']['gtm_id'] );
		$this->assertFalse( $stored['general']['console_log'] );
		$this->assertTrue( $stored['general']['debug_log'] );
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

	/**
	 * Data layer names a save accepts.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function valid_datalayer_names(): array {
		return [
			'the default'           => [ 'dataLayer' ],
			'a custom name'         => [ 'shopLayer' ],
			'a leading underscore'  => [ '_dl' ],
			'a dollar sign'         => [ '$dl' ],
			'digits after a letter' => [ 'layer2' ],
			'empty, the default'    => [ '' ],
			'128 characters'        => [ str_repeat( 'a', 128 ) ],
		];
	}

	/**
	 * A data layer name that is a valid JavaScript name is saved.
	 *
	 * @dataProvider valid_datalayer_names
	 *
	 * @param string $name The submitted name.
	 */
	public function test_a_valid_datalayer_name_is_saved( string $name ): void {
		$response = $this->save( [ 'datalayer_name' => $name ] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $name, get_option( Options::OPTION_NAME )['general']['datalayer_name'] );
	}

	/**
	 * Data layer names a save refuses, by the reason they break the snippet.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalid_datalayer_names(): array {
		return [
			'a space'           => [ 'data layer' ],
			'a dash'            => [ 'data-layer' ],
			'a leading digit'   => [ '2layer' ],
			'a reserved word'   => [ 'class' ],
			'a dot'             => [ 'window.layer' ],
			'not a string'      => [ [ 'dataLayer' ] ],
			'a browser global'  => [ 'name' ],
			'the page location' => [ 'location' ],
			'a library global'  => [ '$' ],
			'129 characters'    => [ str_repeat( 'a', 129 ) ],
		];
	}

	/**
	 * A data layer name that is not a valid JavaScript name is refused by
	 * name, and the stored name is left as it was.
	 *
	 * @dataProvider invalid_datalayer_names
	 *
	 * @param mixed $name The submitted name.
	 */
	public function test_an_invalid_datalayer_name_is_refused( $name ): void {
		$response = $this->save(
			[
				'gtm_id'         => 'GTM-NEW1234',
				'datalayer_name' => $name,
			]
		);
		$error    = $response->as_error();

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'datalayer_name (', $error->get_error_message() );
		$this->assertSame( [ 'general.datalayer_name' ], array_keys( $error->get_error_data()['params'] ) );
		$this->assertSame( 'GTM-OLD1234', get_option( Options::OPTION_NAME )['general']['gtm_id'] );
	}

	/**
	 * A data layer name stored before names were checked, which the settings
	 * app sends back unchanged with every save, does not block saving
	 * another setting, and is left exactly as stored.
	 */
	public function test_a_stored_invalid_datalayer_name_does_not_block_a_save(): void {
		$stored                              = get_option( Options::OPTION_NAME );
		$stored['general']['datalayer_name'] = 'data-layer';
		$stored['general']['sgtm_container_identifier'] = 'Old_Loader';
		update_option( Options::OPTION_NAME, $stored );

		$response = $this->save(
			[
				'gtm_id'                    => 'GTM-NEW1234',
				'datalayer_name'            => 'data-layer',
				'sgtm_container_identifier' => 'Old_Loader',
			]
		);

		$this->assertSame( 200, $response->get_status() );

		$saved = get_option( Options::OPTION_NAME )['general'];
		$this->assertSame( 'GTM-NEW1234', $saved['gtm_id'] );
		$this->assertSame( 'data-layer', $saved['datalayer_name'] );
		$this->assertSame( 'Old_Loader', $saved['sgtm_container_identifier'] );
	}

	/**
	 * A stored value of another type, such as one written outside the
	 * settings screen, does not block a save either, and is kept as stored.
	 */
	public function test_a_stored_non_string_datalayer_name_does_not_block_a_save(): void {
		$stored                              = get_option( Options::OPTION_NAME );
		$stored['general']['datalayer_name'] = null;
		update_option( Options::OPTION_NAME, $stored );

		$response = $this->save(
			[
				'gtm_id'         => 'GTM-NEW1234',
				'datalayer_name' => null,
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_option( Options::OPTION_NAME )['general']['datalayer_name'] );
	}

	/**
	 * A stored invalid name does not let a different invalid name through.
	 */
	public function test_a_stored_invalid_datalayer_name_does_not_admit_a_new_one(): void {
		$stored                              = get_option( Options::OPTION_NAME );
		$stored['general']['datalayer_name'] = 'data-layer';
		update_option( Options::OPTION_NAME, $stored );

		$response = $this->save( [ 'datalayer_name' => 'data layer' ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'data-layer', get_option( Options::OPTION_NAME )['general']['datalayer_name'] );
	}

	/**
	 * The server-side container identifier takes the shape of a loader file
	 * name Stape issues, or nothing for the standard loader.
	 */
	public function test_the_sgtm_container_identifier_is_checked(): void {
		$this->assertSame( 200, $this->save( [ 'sgtm_container_identifier' => '38i0hixjpkyq' ] )->get_status() );
		$this->assertSame( 200, $this->save( [ 'sgtm_container_identifier' => '' ] )->get_status() );
		$this->assertSame( 400, $this->save( [ 'sgtm_container_identifier' => '../loader' ] )->get_status() );
		$this->assertSame( 400, $this->save( [ 'sgtm_container_identifier' => 'my loader' ] )->get_status() );
	}

	/**
	 * Cookie Keeper is a switch: a string boolean is saved as a boolean and
	 * anything else is refused.
	 */
	public function test_cookie_keeper_is_a_boolean(): void {
		$this->assertSame( 200, $this->save( [ 'sgtm_cookie_keeper' => 'true' ] )->get_status() );
		$this->assertTrue( get_option( Options::OPTION_NAME )['general']['sgtm_cookie_keeper'] );

		$this->assertSame( 400, $this->save( [ 'sgtm_cookie_keeper' => 'yes' ] )->get_status() );
	}
}
