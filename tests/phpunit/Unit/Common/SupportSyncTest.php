<?php
/**
 * Unit tests for the live support system-data sync engine.
 *
 * Pattern: BrainMonkey (via `yoast/wp-test-utils`), following the
 * UtilTest.php template. The wp_options table is simulated with a small
 * in-memory store so the marker lifecycle can be asserted end to end, and
 * the remote support endpoint is simulated through `wp_remote_*` stubs.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SupportSync;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use WP_Error;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see SupportSync}.
 */
final class SupportSyncTest extends TestCase {

	/**
	 * In-memory wp_options substitute.
	 *
	 * @var array<string, mixed>
	 */
	private array $options_store = [];

	/**
	 * The autoload flag captured from the most recent update_option() call.
	 *
	 * @var mixed
	 */
	private $last_autoload_flag;

	/**
	 * System under test.
	 *
	 * @var SupportSync
	 */
	private SupportSync $support_sync;

	/**
	 * An instance of Util wired like production.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
	 * An instance of Options wired like production.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Wire up real Options + Util with stubbed WP functions and an
	 * in-memory option store.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'GTMKIT_PATH' ) ) {
			define( 'GTMKIT_PATH', '/fake/plugin/path/' );
		}
		if ( ! defined( 'GTMKIT_URL' ) ) {
			define( 'GTMKIT_URL', 'https://example.test/wp-content/plugins/gtm-kit/' );
		}

		$this->options_store      = [];
		$this->last_autoload_flag = null;

		Functions\when( 'get_option' )->alias(
			function ( $name, $default_value = false ) {
				return array_key_exists( $name, $this->options_store ) ? $this->options_store[ $name ] : $default_value;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value, $autoload = null ) {
				$this->options_store[ $name ] = $value;
				$this->last_autoload_flag     = $autoload;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( $name ) {
				unset( $this->options_store[ $name ] );
				return true;
			}
		);

		// The cron functions are deliberately NOT stubbed here: a
		// `Functions\when()` stub set in set_up() would shadow the
		// `Functions\expect()` mocks individual tests place on the same
		// function. Tests that merely pass through a clear path call
		// stub_cron_cleanup() instead.
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ) {
				return $thing instanceof WP_Error;
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : '';
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
			}
		);

		$this->options      = Options::create();
		$this->util         = new Util( $this->options, new RestAPIServer() );
		$this->support_sync = new SupportSync( $this->options, $this->util );
	}

	/**
	 * Store a valid marker directly in the option store.
	 *
	 * @param array<string, mixed> $overrides Marker fields to override.
	 *
	 * @return array<string, mixed> The stored marker.
	 */
	private function seed_marker( array $overrides = [] ): array {
		$now    = time();
		$marker = array_merge(
			[
				'ticket'               => 'FS123-ABC45',
				'first_shared_at'      => $now,
				'last_push_at'         => $now,
				'last_status_check_at' => $now,
			],
			$overrides
		);

		$this->options_store[ SupportSync::OPTION ] = $marker;

		return $marker;
	}

	/**
	 * Build a wp_remote_* response array.
	 *
	 * @param int                  $code The HTTP status code.
	 * @param array<string, mixed> $body The JSON body.
	 *
	 * @return array<string, mixed>
	 */
	private function http_response( int $code, array $body ): array {
		return [
			'response' => [ 'code' => $code ],
			'body'     => (string) json_encode( $body ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Building a plain test fixture outside WordPress.
		];
	}

	/**
	 * Stub the cron cleanup for tests that pass through a session-clear
	 * path without asserting on the unscheduling itself.
	 *
	 * @return void
	 */
	private function stub_cron_cleanup(): void {
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );
	}

	/**
	 * Stub the WP functions Util::get_site_data() touches so the real
	 * payload builder can run inside the unit harness.
	 *
	 * @return void
	 */
	private function stub_site_data_functions(): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulating the WP global inside the unit harness; restored in tear_down().
		$GLOBALS['wp_version'] = '6.9.1';

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/fake/wp-content/plugins' );
		}

		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Mimic WP_Theme::get_template().
				 *
				 * @return string
				 */
				public function get_template(): string {
					return 'twentytwentyfive';
				}
			}
		);
		Functions\when( 'get_plugins' )->justReturn(
			[
				'gtm-kit/gtm-kit.php' => [
					'Name'    => 'GTM Kit',
					'Version' => '2.16.4',
				],
			]
		);
		Functions\when( 'is_plugin_active' )->alias(
			static function ( $plugin ) {
				return $plugin === 'gtm-kit/gtm-kit.php';
			}
		);
		Functions\when( 'get_plugin_data' )->justReturn( [ 'Version' => '2.16.4' ] );
		Functions\when( 'get_locale' )->justReturn( 'en_US' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'site_url' )->justReturn( 'https://example.test' );
	}

	/**
	 * Clean up the WP global set by stub_site_data_functions().
	 *
	 * @inheritDoc
	 */
	protected function tear_down(): void {
		unset( $GLOBALS['wp_version'] );

		parent::tear_down();
	}

	/**
	 * Activating a session stores the marker with all timestamps set to
	 * now, outside the autoloaded options.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::activate
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::get_marker
	 */
	public function test_activate_stores_marker_without_autoload(): void {
		$before = time();
		$this->support_sync->activate( 'FS123-ABC45' );
		$after = time();

		$marker = $this->support_sync->get_marker();

		$this->assertNotNull( $marker );
		$this->assertSame( 'FS123-ABC45', $marker['ticket'] );
		foreach ( [ 'first_shared_at', 'last_push_at', 'last_status_check_at' ] as $key ) {
			$this->assertGreaterThanOrEqual( $before, $marker[ $key ] );
			$this->assertLessThanOrEqual( $after, $marker[ $key ] );
		}
		$this->assertFalse( $this->last_autoload_flag, 'The marker must not be autoloaded on every request.' );
	}

	/**
	 * A new share overwrites a previous session: latest ticket wins.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::activate
	 */
	public function test_activate_overwrites_previous_session(): void {
		$this->seed_marker( [ 'ticket' => 'FS111-OLD11' ] );

		$this->support_sync->activate( 'FS222-NEW22' );

		$marker = $this->support_sync->get_marker();
		$this->assertNotNull( $marker );
		$this->assertSame( 'FS222-NEW22', $marker['ticket'] );
	}

	/**
	 * An invalid stored value is not reported as an active session.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::get_marker
	 */
	public function test_get_marker_rejects_malformed_values(): void {
		$this->options_store[ SupportSync::OPTION ] = 'not-an-array';
		$this->assertNull( $this->support_sync->get_marker() );

		$this->options_store[ SupportSync::OPTION ] = [ 'ticket' => 'FS123-ABC45' ];
		$this->assertNull( $this->support_sync->get_marker(), 'A marker without timestamps is invalid.' );
	}

	/**
	 * Clearing the session removes the marker and unschedules any pending push.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::clear_marker
	 */
	public function test_clear_marker_removes_option_and_unschedules(): void {
		$this->seed_marker();

		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( SupportSync::PUSH_HOOK );

		$this->support_sync->clear_marker();

		$this->assertNull( $this->support_sync->get_marker() );
		$this->assertArrayNotHasKey( SupportSync::OPTION, $this->options_store );
	}

	/**
	 * Clearing by ticket only ends the session that belongs to that ticket.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::clear_marker_for_ticket
	 */
	public function test_clear_marker_for_ticket_matches_ticket(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'ticket' => 'FS123-ABC45' ] );

		$this->support_sync->clear_marker_for_ticket( 'FS999-OTHER' );
		$this->assertNotNull( $this->support_sync->get_marker(), 'A session for a different ticket stays active.' );

		$this->support_sync->clear_marker_for_ticket( 'FS123-ABC45' );
		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * Without an active session a settings save schedules nothing.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::schedule_push
	 */
	public function test_schedule_push_without_marker_schedules_nothing(): void {
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->support_sync->schedule_push();

		$this->assertArrayNotHasKey( SupportSync::OPTION, $this->options_store );
	}

	/**
	 * With an active session a settings save schedules one coalescing push.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::schedule_push
	 */
	public function test_schedule_push_schedules_one_coalescing_event(): void {
		$this->seed_marker();
		$before = time();

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( SupportSync::PUSH_HOOK )
			->andReturn( false );
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->withArgs(
				static function ( $timestamp, $hook ) use ( $before ) {
					return $hook === SupportSync::PUSH_HOOK
						&& $timestamp >= $before + 60
						&& $timestamp <= time() + 61;
				}
			);

		$this->support_sync->schedule_push();

		$this->assertNotNull( $this->support_sync->get_marker() );
	}

	/**
	 * A burst of settings saves coalesces: nothing is scheduled while a
	 * push is already pending.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::schedule_push
	 */
	public function test_schedule_push_noops_when_push_already_pending(): void {
		$this->seed_marker();

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( SupportSync::PUSH_HOOK )
			->andReturn( time() + 30 );
		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->support_sync->schedule_push();

		$this->assertNotNull( $this->support_sync->get_marker() );
	}

	/**
	 * A save after the session cap ends the session instead of scheduling.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::schedule_push
	 */
	public function test_schedule_push_clears_expired_marker(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'first_shared_at' => time() - 604801 ] );

		Functions\expect( 'wp_schedule_single_event' )->never();

		$this->support_sync->schedule_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * The push callback transmits nothing without an active session.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_without_marker_transmits_nothing(): void {
		Functions\expect( 'wp_remote_get' )->never();
		Functions\expect( 'wp_remote_request' )->never();

		$this->support_sync->run_push();

		$this->assertArrayNotHasKey( SupportSync::OPTION, $this->options_store );
	}

	/**
	 * The push callback ends an expired session without transmitting.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_clears_expired_marker_without_transmitting(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'first_shared_at' => time() - 604801 ] );

		Functions\expect( 'wp_remote_get' )->never();
		Functions\expect( 'wp_remote_request' )->never();

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * A push within the status-cache window skips the status check and
	 * PUTs the auto-sourced payload to the ticket endpoint.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_pushes_within_status_cache_window(): void {
		$this->stub_site_data_functions();
		$this->seed_marker( [ 'last_push_at' => time() - 3600 ] );
		$before = time();

		Functions\expect( 'wp_remote_get' )->never();
		Functions\expect( 'wp_remote_request' )
			->once()
			->withArgs(
				function ( $url, $args ) {
					$body = json_decode( $args['body'], true );

					return $url === 'https://support.gtmkit.com/api/wporg/support/FS123-ABC45'
						&& $args['method'] === 'PUT'
						&& is_string( $body['system_data'] )
						&& $body['source'] === 'auto';
				}
			)
			->andReturn(
				$this->http_response(
					200,
					[
						'success' => true,
						'status'  => 'open',
					]
				)
			);

		$this->support_sync->run_push();

		$marker = $this->support_sync->get_marker();
		$this->assertNotNull( $marker, 'A successful push keeps the session active.' );
		$this->assertGreaterThanOrEqual( $before, $marker['last_push_at'] );
	}

	/**
	 * A stale status cache triggers a status check first; a closed ticket
	 * ends the session and nothing is transmitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_stops_on_closed_status_without_transmitting(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'last_status_check_at' => time() - 901 ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->withArgs(
				static function ( $url ) {
					return $url === 'https://support.gtmkit.com/api/wporg/support/FS123-ABC45/status';
				}
			)
			->andReturn( $this->http_response( 200, [ 'status' => 'closed' ] ) );
		Functions\expect( 'wp_remote_request' )->never();

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * A failed status check (transport error) fails closed: session ends,
	 * nothing is transmitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_fails_closed_on_status_transport_error(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'last_status_check_at' => time() - 901 ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( new WP_Error() );
		Functions\expect( 'wp_remote_request' )->never();

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * An unexpected status response code fails closed: session ends,
	 * nothing is transmitted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_fails_closed_on_unexpected_status_code(): void {
		$this->stub_cron_cleanup();
		$this->seed_marker( [ 'last_status_check_at' => time() - 901 ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( $this->http_response( 404, [ 'status' => 'closed' ] ) );
		Functions\expect( 'wp_remote_request' )->never();

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * A confirmed-open status check refreshes the marker and the push
	 * proceeds.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_confirmed_open_refreshes_check_and_pushes(): void {
		$this->stub_site_data_functions();
		$stale = time() - 901;
		$this->seed_marker( [ 'last_status_check_at' => $stale ] );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( $this->http_response( 200, [ 'status' => 'open' ] ) );
		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn(
				$this->http_response(
					200,
					[
						'success' => true,
						'status'  => 'open',
					]
				)
			);

		$this->support_sync->run_push();

		$marker = $this->support_sync->get_marker();
		$this->assertNotNull( $marker );
		$this->assertGreaterThan( $stale, $marker['last_status_check_at'] );
	}

	/**
	 * A 410 Gone on the push ends the session.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_clears_marker_on_410(): void {
		$this->stub_cron_cleanup();
		$this->stub_site_data_functions();
		$this->seed_marker();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn(
				$this->http_response(
					410,
					[
						'success' => false,
						'status'  => 'closed',
					]
				)
			);

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * A 200 whose body reports failure (unknown ticket) ends the session.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::run_push
	 */
	public function test_run_push_clears_marker_when_body_reports_failure(): void {
		$this->stub_cron_cleanup();
		$this->stub_site_data_functions();
		$this->seed_marker();

		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn( $this->http_response( 200, [ 'success' => false ] ) );

		$this->support_sync->run_push();

		$this->assertNull( $this->support_sync->get_marker() );
	}

	/**
	 * The automatic push transmits the identical system-data payload as
	 * the manual share; only the source label differs.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::build_request_body
	 */
	public function test_payload_is_identical_to_manual_share(): void {
		$this->stub_site_data_functions();

		$manual = $this->support_sync->build_request_body( SupportSync::SOURCE_MANUAL );
		$auto   = $this->support_sync->build_request_body( SupportSync::SOURCE_AUTO );

		$this->assertSame( $manual['system_data'], $auto['system_data'], 'The automatic push must transmit byte-identical system data.' );
		$this->assertSame( 'manual', $manual['source'] );
		$this->assertSame( 'auto', $auto['source'] );
		$this->assertSame(
			json_encode( $this->util->get_site_data( $this->options->get_all_raw(), false ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Mirrors the stubbed wp_json_encode alias for a byte comparison.
			$auto['system_data'],
			'The payload is exactly the site data the manual share sends.'
		);
	}

	/**
	 * The client state reports an active session with ticket and end date,
	 * and inactive when the session is gone or expired.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::get_client_state
	 */
	public function test_get_client_state_reflects_session(): void {
		Functions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp ) {
				return gmdate( $format, $timestamp );
			}
		);

		$this->assertSame( [ 'active' => false ], $this->support_sync->get_client_state() );

		$first_shared_at                    = time() - 3600;
		$this->options_store['date_format'] = 'Y-m-d';
		$marker                             = $this->seed_marker( [ 'first_shared_at' => $first_shared_at ] );
		$state                              = $this->support_sync->get_client_state();

		$this->assertTrue( $state['active'] );
		$this->assertSame( $marker['ticket'], $state['ticket'] );
		$this->assertSame( gmdate( 'Y-m-d', $first_shared_at + 604800 ), $state['until'] );

		$this->seed_marker( [ 'first_shared_at' => time() - 604801 ] );
		$this->assertSame( [ 'active' => false ], $this->support_sync->get_client_state(), 'An expired session reads as inactive.' );
	}

	/**
	 * The config filter can tune the timings and junk values fall back to
	 * the defaults.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::get_config
	 */
	public function test_config_filter_tunes_and_sanitizes(): void {
		$this->assertSame(
			[
				'coalesce_delay'        => 60,
				'session_cap'           => 604800,
				'status_check_interval' => 900,
			],
			$this->support_sync->get_config()
		);

		Filters\expectApplied( 'gtmkit_support_sync_config' )
			->once()
			->andReturn(
				[
					'coalesce_delay'        => 5,
					'session_cap'           => 'junk',
					'status_check_interval' => -1,
				]
			);

		$this->assertSame(
			[
				'coalesce_delay'        => 5,
				'session_cap'           => 604800,
				'status_check_interval' => 0,
			],
			$this->support_sync->get_config()
		);
	}

	/**
	 * Registering hooks the option update and the push callback.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SupportSync::register
	 */
	public function test_register_adds_hooks(): void {
		SupportSync::register( $this->options, $this->util );

		$this->assertTrue( has_action( 'update_option_gtmkit' ) );
		$this->assertTrue( has_action( SupportSync::PUSH_HOOK ) );
	}
}
