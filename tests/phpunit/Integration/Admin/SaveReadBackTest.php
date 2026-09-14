<?php
/**
 * Integration tests for the read-back check on a settings save.
 *
 * Saves go through the real `set-options` route, the real Options write and
 * the real option cache. A stale read is simulated by replacing what the
 * cache or the option read returns once the write has happened, which is
 * what a persistent object cache keeping an outdated copy, or a database
 * that did not keep the write, looks like to the plugin.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::set_options()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\AdminAPI;
use TLA_Media\GTM_Kit\Options\Options;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Covers the saved, not-kept and rejected paths of a settings save.
 */
final class SaveReadBackTest extends WP_UnitTestCase {

	/**
	 * The object cache flag to restore after each test.
	 *
	 * @var bool
	 */
	private bool $was_using_ext_object_cache;

	/**
	 * Seed a known container ID, sign in an administrator, and start a fresh REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->was_using_ext_object_cache = (bool) wp_using_ext_object_cache();

		update_option( Options::OPTION_NAME, [ 'general' => [ 'gtm_id' => 'GTM-OLD1234' ] ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Restore the object cache flag.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_using_ext_object_cache( $this->was_using_ext_object_cache );

		parent::tear_down();
	}

	/**
	 * Save a new container ID through the route the settings app uses.
	 *
	 * @param string $gtm_id The container ID to save.
	 *
	 * @return WP_REST_Response
	 */
	private function save( string $gtm_id ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/gtmkit/v1/set-options' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( [ 'general' => [ 'gtm_id' => $gtm_id ] ] ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Once the write has happened, make the option read back with its old value.
	 *
	 * @param callable $make_stale Replaces what the next read returns.
	 *
	 * @return void
	 */
	private function after_the_write( callable $make_stale ): void {
		add_action(
			'update_option_' . Options::OPTION_NAME,
			static function ( $old_value ) use ( $make_stale ) {
				$make_stale( $old_value );
			},
			10,
			1
		);
	}

	/**
	 * A normal save answers with the saved settings, in the shape the app reads.
	 */
	public function test_a_normal_save_answers_with_the_saved_settings(): void {
		$response = $this->save( 'GTM-NEW1234' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'GTM-NEW1234', $response->get_data()['data']['general']['gtm_id'] );
	}

	/**
	 * A normal save with a persistent object cache still passes the check.
	 */
	public function test_a_normal_save_passes_with_a_persistent_object_cache(): void {
		wp_using_ext_object_cache( true );

		$response = $this->save( 'GTM-NEW1234' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'GTM-NEW1234', $response->get_data()['data']['general']['gtm_id'] );
	}

	/**
	 * A persistent object cache still holding the old copy after the write
	 * is answered with an error naming the cache, not with the old values.
	 */
	public function test_a_stale_object_cache_is_an_error_naming_the_cache(): void {
		wp_using_ext_object_cache( true );

		$this->after_the_write(
			static function ( $old_value ) {
				$alloptions                         = wp_load_alloptions();
				$alloptions[ Options::OPTION_NAME ] = maybe_serialize( $old_value );
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			}
		);

		$response = $this->save( 'GTM-NEW1234' );
		$error    = $response->as_error();

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'gtmkit_save_not_kept', $error->get_error_code() );
		$this->assertStringContainsString( 'persistent object cache', $error->get_error_message() );
		$this->assertStringContainsString( 'Flush the object cache', $error->get_error_message() );
		$this->assertSame( [ 'general.gtm_id' ], $error->get_error_data()['settings'] );
	}

	/**
	 * Without a persistent object cache, a write that did not stick is
	 * blamed on the database, never on a cache the site does not have.
	 */
	public function test_a_lost_write_without_an_object_cache_points_at_the_database(): void {
		wp_using_ext_object_cache( false );

		$this->after_the_write(
			static function ( $old_value ) {
				add_filter( 'option_' . Options::OPTION_NAME, static fn() => $old_value );
			}
		);

		$error = $this->save( 'GTM-NEW1234' )->as_error();

		$this->assertSame( 'gtmkit_save_not_kept', $error->get_error_code() );
		$this->assertStringContainsString( 'database', $error->get_error_message() );
		$this->assertStringNotContainsString( 'object cache', $error->get_error_message() );
	}

	/**
	 * Keys that were not submitted, or that the save did not write, are
	 * never compared, and a value normalised on write compares as written.
	 */
	public function test_only_submitted_and_written_keys_are_compared(): void {
		$submitted = [
			'general' => [
				'gtm_id'  => 'abc1234',
				'dropped' => 'x',
			],
			'scalar'  => 'ignored',
		];
		$written   = [
			'general'      => [ 'gtm_id' => 'GTM-ABC1234' ],
			'integrations' => [ 'woo' => true ],
		];

		$this->assertSame( [], AdminAPI::find_options_not_kept( $submitted, $written, $written ) );
		$this->assertSame(
			[ 'general.gtm_id' ],
			AdminAPI::find_options_not_kept( $submitted, $written, [ 'general' => [ 'gtm_id' => 'GTM-OLD1234' ] ] )
		);
		$this->assertSame(
			[ 'general.gtm_id' ],
			AdminAPI::find_options_not_kept( [ 'general' => [ 'gtm_id' => '1' ] ], [ 'general' => [ 'gtm_id' => 1 ] ], [ 'general' => [ 'gtm_id' => '1' ] ] ),
			'Comparison is strict.'
		);
	}
}
