<?php
/**
 * Integration tests for the tracking scan's wiring.
 *
 * The parser and the state machine are covered by the unit suite. What needs
 * a booted WordPress is everything around them: the scheduled event, the
 * option the result lives in, the notices raised and cleared from a stored
 * result, and the Site Health test rendering each of the three states.
 *
 * Targets: {@see \TLA_Media\GTM_Kit\Common\SnippetScan} and
 * {@see \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\Notification;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\PluginAvailability;
use TLA_Media\GTM_Kit\Admin\Suggestions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers the scan's scheduling, storage, notices and Site Health surface.
 */
final class SnippetScanIntegrationTest extends WP_UnitTestCase {

	/**
	 * Run the admin bootstrap the way an admin request would.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'gtmkit_version', GTMKIT_VERSION );
		delete_option( SnippetScan::OPTION );

		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'gtmkit_site_health_tests' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		\TLA_Media\GTM_Kit\gtmkit_admin_init();
	}

	/**
	 * Leave no scheduled event behind for the next test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		SnippetScan::clear_scheduled_event();
		delete_option( SnippetScan::OPTION );

		parent::tear_down();
	}

	/**
	 * Build a scan engine against the site's real options.
	 *
	 * @return SnippetScan
	 */
	private function engine(): SnippetScan {
		return new SnippetScan( OptionsFactory::get_instance() );
	}

	/**
	 * Store a scan result for the surfaces to read.
	 *
	 * @param string               $state One of the SnippetScan STATE_* constants.
	 * @param array<string, mixed> $overrides Fields to override on the stored result.
	 *
	 * @return void
	 */
	private function store_result( string $state, array $overrides = [] ): void {
		update_option(
			SnippetScan::OPTION,
			array_merge(
				[
					'schema'           => SnippetScan::SCHEMA_VERSION,
					'url'              => home_url( '/' ),
					'final_url'        => home_url( '/' ),
					'scanned_at'       => time() - HOUR_IN_SECONDS,
					'state'            => $state,
					'reason'           => '',
					'status'           => 200,
					'implementations'  => [],
					'container_output' => true,
					'cmp'              => null,
					'duplicate'        => [
						'type'       => '',
						'containers' => [],
						'culprit'    => '',
					],
				],
				$overrides
			),
			false
		);
	}

	/**
	 * Evaluate the duplicate-tracking suggestion against the stored result.
	 *
	 * The suggestion is invoked directly rather than by firing `admin_init`,
	 * so the assertion is about this notice and not about whatever else a
	 * booted WordPress does on that hook.
	 *
	 * @return NotificationsHandler The handler holding the current notifications.
	 */
	private function run_duplicate_suggestion(): NotificationsHandler {
		Util::load_plugin_api();

		$handler = NotificationsHandler::get();
		$handler->setup_current_notifications();

		$options             = OptionsFactory::get_instance();
		$plugin_availability = new PluginAvailability();
		$plugin_availability->register();

		$suggestions = new Suggestions(
			$handler,
			$plugin_availability,
			$options,
			new Util( $options, new RestAPIServer() ),
			$this->engine()
		);

		$suggestions->suggest_duplicate_tracking();

		return $handler;
	}

	/**
	 * Run the registered Site Health test and return its result.
	 *
	 * @return array<string, mixed>
	 */
	private function site_health_result(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$tests = apply_filters( 'site_status_tests', [] );

		$this->assertArrayHasKey( 'gtmkit_tracking_scan', $tests['direct'] );

		return (array) call_user_func( $tests['direct']['gtmkit_tracking_scan']['test'] );
	}

	/**
	 * The daily event is scheduled, and cleared again on deactivation.
	 */
	public function test_the_daily_event_is_scheduled_and_cleared(): void {
		$this->engine()->schedule_daily_event();

		$this->assertNotFalse( wp_next_scheduled( SnippetScan::SCAN_HOOK ) );

		SnippetScan::clear_scheduled_event();

		$this->assertFalse( wp_next_scheduled( SnippetScan::SCAN_HOOK ) );
	}

	/**
	 * Scheduling twice leaves one event, not two.
	 */
	public function test_scheduling_twice_leaves_one_event(): void {
		$engine = $this->engine();
		$engine->schedule_daily_event();
		$first = wp_next_scheduled( SnippetScan::SCAN_HOOK );

		$engine->schedule_daily_event();

		$this->assertSame( $first, wp_next_scheduled( SnippetScan::SCAN_HOOK ) );
	}

	/**
	 * The result option round-trips and is never autoloaded.
	 */
	public function test_the_result_option_round_trips_without_autoloading(): void {
		$this->store_result( SnippetScan::STATE_FOUND );

		$result = $this->engine()->get_result();

		$this->assertIsArray( $result );
		$this->assertSame( SnippetScan::STATE_FOUND, $result['state'] );
		$this->assertTrue( $this->engine()->has_container() );

		$this->assertArrayNotHasKey( SnippetScan::OPTION, wp_load_alloptions() );
	}

	/**
	 * The scan target defaults to the home page and honours the filter.
	 */
	public function test_the_scan_target_defaults_to_the_home_page_and_is_filterable(): void {
		$this->assertSame( home_url( '/' ), $this->engine()->get_scan_url() );

		add_filter( 'gtmkit_snippet_scan_url', static fn() => 'https://example.test/sample/' );

		$this->assertSame( 'https://example.test/sample/', $this->engine()->get_scan_url() );

		remove_all_filters( 'gtmkit_snippet_scan_url' );
	}

	/**
	 * A duplicate finding raises the notice.
	 */
	public function test_a_duplicate_finding_raises_the_notice(): void {
		$this->store_result(
			SnippetScan::STATE_FOUND,
			[
				'duplicate' => [
					'type'       => SnippetScan::DUPLICATE_CONTAINERS,
					'containers' => [ 'GTM-ABCD123', 'GTM-ZZZZ999' ],
					'culprit'    => '',
				],
			]
		);

		$handler = $this->run_duplicate_suggestion();

		$this->assertInstanceOf( Notification::class, $handler->get_notification_by_id( 'gtmkit-duplicate-tracking' ) );
	}

	/**
	 * A scan that could not be completed raises no duplicate notice.
	 */
	public function test_an_inconclusive_scan_raises_no_duplicate_notice(): void {
		$this->store_result(
			SnippetScan::STATE_INCONCLUSIVE,
			[ 'reason' => SnippetScan::REASON_LOOPBACK_BLOCKED ]
		);

		$handler = $this->run_duplicate_suggestion();

		$this->assertNull( $handler->get_notification_by_id( 'gtmkit-duplicate-tracking' ) );
	}

	/**
	 * The Site Health test is registered as a direct test alongside the others.
	 */
	public function test_the_site_health_test_is_registered_as_a_direct_test(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$tests = apply_filters( 'site_status_tests', [] );

		$this->assertArrayHasKey( 'gtmkit_tracking_scan', $tests['direct'] );
		$this->assertIsCallable( $tests['direct']['gtmkit_tracking_scan']['test'] );
	}

	/**
	 * With nothing stored, the test says so rather than inventing a verdict.
	 */
	public function test_the_site_health_test_reports_a_site_that_has_not_been_scanned(): void {
		$result = $this->site_health_result();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'gtmkit_tracking_scan', $result['test'] );
		$this->assertStringContainsString( 'gtmkit-snippet-rescan', $result['actions'] );
	}

	/**
	 * A page carrying one implementation is good news.
	 */
	public function test_the_site_health_test_reports_a_page_that_loads_tracking(): void {
		$this->store_result(
			SnippetScan::STATE_FOUND,
			[
				'implementations' => [
					[
						'type'      => 'gtm',
						'source'    => 'inline',
						'container' => 'GTM-ABCD123',
						'host'      => 'www.googletagmanager.com',
						'owner'     => 'gtmkit',
						'weak'      => false,
					],
				],
			]
		);

		$result = $this->site_health_result();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Last checked', $result['description'] );
	}

	/**
	 * A duplicate finding is reported with the containers involved.
	 */
	public function test_the_site_health_test_reports_a_duplicate(): void {
		$this->store_result(
			SnippetScan::STATE_FOUND,
			[
				'duplicate' => [
					'type'       => SnippetScan::DUPLICATE_CONTAINERS,
					'containers' => [ 'GTM-ABCD123', 'GTM-ZZZZ999' ],
					'culprit'    => 'Google Tag Manager for WordPress',
				],
			]
		);

		$result = $this->site_health_result();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'GTM-ABCD123, GTM-ZZZZ999', $result['description'] );
		$this->assertStringContainsString( 'Google Tag Manager for WordPress', $result['description'] );
	}

	/**
	 * A page with nothing on it, while GTM Kit believes it is adding the
	 * container, is a contradiction worth flagging.
	 */
	public function test_the_site_health_test_reports_a_missing_container(): void {
		$this->store_result( SnippetScan::STATE_NOT_FOUND, [ 'container_output' => true ] );

		$this->assertSame( 'critical', $this->site_health_result()['status'] );
	}

	/**
	 * A scan that could not be completed never becomes a verdict about tracking.
	 */
	public function test_the_site_health_test_reports_an_inconclusive_scan(): void {
		$this->store_result(
			SnippetScan::STATE_INCONCLUSIVE,
			[ 'reason' => SnippetScan::REASON_LOOPBACK_BLOCKED ]
		);

		$result = $this->site_health_result();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'cannot say', $result['description'] );
	}

	/**
	 * The test performs no HTTP request of its own.
	 */
	public function test_the_site_health_test_performs_no_request(): void {
		$this->store_result( SnippetScan::STATE_FOUND );

		$requests = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$requests ) {
				++$requests;

				return $preempt;
			}
		);

		$this->site_health_result();

		remove_all_filters( 'pre_http_request' );

		$this->assertSame( 0, $requests );
	}
}
