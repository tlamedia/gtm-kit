<?php
/**
 * Integration tests for the review ask.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\ReviewAsk}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\ReviewAsk;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\SnippetScanDetector;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The ask appears only after two unbroken weeks of observed success, and its
 * own record decides when it is put off or over.
 */
final class ReviewAskTest extends WP_UnitTestCase {

	/**
	 * The time every test starts from.
	 *
	 * @var int
	 */
	private int $now;

	/**
	 * The ask under test.
	 *
	 * @var ReviewAsk
	 */
	private ReviewAsk $ask;

	/**
	 * Seed a site whose container is saved and output, and refuse any
	 * outbound request.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->now = time();

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TW5FD4G7' );
		$options->set_option( 'general', 'container_active', true );

		// The plugin's own observer would stamp each stored result with the
		// real clock; these tests drive the clock themselves.
		remove_all_actions( 'add_option_' . SnippetScan::OPTION );
		remove_all_actions( 'update_option_' . SnippetScan::OPTION );

		delete_option( ReviewAsk::OPTION );
		delete_option( SnippetScan::OPTION );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->ask = new ReviewAsk( new SnippetScan( $options ), new SiteHealth( $options, new Util( $options, new RestAPIServer() ) ) );

		add_filter( 'pre_http_request', [ $this, 'fail_on_request' ], 10, 3 );
	}

	/**
	 * Leave no state behind.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( ReviewAsk::OPTION );
		delete_option( SnippetScan::OPTION );
		parent::tear_down();
	}

	/**
	 * Fail the test on any outbound HTTP request.
	 *
	 * @param false|array<string, mixed> $response The short-circuited response.
	 * @param array<string, mixed>       $args The request arguments.
	 * @param string                     $url The request URL.
	 *
	 * @return never
	 */
	public function fail_on_request( $response, array $args, string $url ) {
		$this->fail( 'The review ask sent a request to ' . $url );
	}

	/**
	 * A point in time, in days from the start of the test.
	 *
	 * @param int $days Days from the start.
	 *
	 * @return int
	 */
	private function day( int $days ): int {
		return $this->now + $days * DAY_IN_SECONDS;
	}

	/**
	 * The container GTM Kit loads, as the check reports it.
	 *
	 * @param string $owner Who the check says loaded it.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function implementations( string $owner = SnippetScanDetector::OWNER_GTMKIT ): array {
		return [
			[
				'type'      => 'gtm',
				'source'    => SnippetScanDetector::SOURCE_SCRIPT,
				'container' => 'GTM-TW5FD4G7',
				'owner'     => $owner,
			],
		];
	}

	/**
	 * Store the result of a check run on a given day, and let the ask see it.
	 *
	 * @param int                  $day The day the check ran.
	 * @param string               $state One of the SnippetScan STATE_* constants.
	 * @param array<string, mixed> $overrides Fields to override on the stored result.
	 *
	 * @return void
	 */
	private function check_on( int $day, string $state = SnippetScan::STATE_FOUND, array $overrides = [] ): void {
		update_option(
			SnippetScan::OPTION,
			array_merge(
				[
					'schema'           => SnippetScan::SCHEMA_VERSION,
					'url'              => home_url( '/' ),
					'final_url'        => home_url( '/' ),
					'scanned_at'       => $this->day( $day ),
					'state'            => $state,
					'reason'           => '',
					'status'           => 200,
					'implementations'  => SnippetScan::STATE_FOUND === $state ? self::implementations() : [],
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

		$this->ask->observe( $this->day( $day ) );
	}

	/**
	 * Run a successful check every day in a range.
	 *
	 * @param int $from The first day.
	 * @param int $to The last day.
	 *
	 * @return void
	 */
	private function checks_from( int $from, int $to ): void {
		for ( $day = $from; $day <= $to; $day++ ) {
			$this->check_on( $day );
		}
	}

	/**
	 * Two weeks of daily success brings the ask, and not a day sooner.
	 *
	 * @return void
	 */
	public function test_asks_after_two_weeks_of_success(): void {
		$this->checks_from( 0, 13 );
		$this->assertFalse( $this->ask->should_ask( $this->day( 13 ) ) );

		$this->check_on( 14 );
		$this->assertTrue( $this->ask->should_ask( $this->day( 14 ) ) );
	}

	/**
	 * Each gate on its own keeps the ask away.
	 *
	 * @return void
	 */
	public function test_each_gate_hides_it(): void {
		$this->checks_from( 0, 15 );
		$this->assertTrue( $this->ask->should_ask( $this->day( 15 ) ), 'Every gate is open.' );

		$this->check_on(
			15,
			SnippetScan::STATE_FOUND,
			[
				'duplicate' => [
					'type'       => SnippetScan::DUPLICATE_CONTAINERS,
					'containers' => [ 'GTM-A', 'GTM-B' ],
					'culprit'    => '',
				],
			]
		);
		$this->assertFalse( $this->ask->should_ask( $this->day( 15 ) ), 'Duplicate tracking is a failing state.' );

		$this->check_on( 15 );
		add_filter( 'gtmkit_container_active', '__return_false' );
		$this->assertFalse( $this->ask->should_ask( $this->day( 15 ) ), 'The container test is not good.' );
		remove_all_filters( 'gtmkit_container_active' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertFalse( $this->ask->should_ask( $this->day( 15 ) ), 'An editor cannot manage GTM Kit.' );
	}

	/**
	 * Tracking that GTM Kit did not load is not GTM Kit's success.
	 *
	 * @return void
	 */
	public function test_tracking_from_elsewhere_does_not_count(): void {
		for ( $day = 0; $day <= 15; $day++ ) {
			$this->check_on( $day, SnippetScan::STATE_FOUND, [ 'implementations' => self::implementations( SnippetScanDetector::OWNER_FOREIGN ) ] );
		}

		$this->assertFalse( $this->ask->should_ask( $this->day( 15 ) ) );
	}

	/**
	 * An open question is not a success: inconclusive or missing results stop the clock.
	 *
	 * @return void
	 */
	public function test_unknown_is_not_yes(): void {
		$this->checks_from( 0, 14 );

		$this->check_on( 15, SnippetScan::STATE_INCONCLUSIVE, [ 'reason' => SnippetScan::REASON_REQUEST_FAILED ] );
		$this->assertFalse( $this->ask->should_ask( $this->day( 15 ) ) );

		delete_option( SnippetScan::OPTION );
		$this->assertFalse( $this->ask->should_ask( $this->day( 16 ) ) );
	}

	/**
	 * The clock starts over when a check stops finding the container.
	 *
	 * @return void
	 */
	public function test_clock_resets_when_the_container_is_not_found(): void {
		$this->checks_from( 0, 9 );
		$this->check_on( 10, SnippetScan::STATE_NOT_FOUND );
		$this->checks_from( 11, 24 );

		$this->assertFalse( $this->ask->should_ask( $this->day( 24 ) ), 'Thirteen days since it was found again.' );

		$this->check_on( 25 );
		$this->assertTrue( $this->ask->should_ask( $this->day( 25 ) ) );
	}

	/**
	 * A result nobody has refreshed for days proves nothing about today.
	 *
	 * @return void
	 */
	public function test_a_stale_result_is_not_a_success(): void {
		$this->checks_from( 0, 14 );

		$this->assertTrue( $this->ask->should_ask( $this->day( 16 ) ), 'One missed check is allowed for.' );
		$this->assertFalse( $this->ask->should_ask( $this->day( 17 ) ), 'Three days without a check is too long.' );
	}

	/**
	 * Checks that stop and start again restart the two weeks.
	 *
	 * @return void
	 */
	public function test_a_gap_in_the_checks_restarts_the_clock(): void {
		$this->checks_from( 0, 5 );
		$this->checks_from( 10, 20 );

		$this->assertFalse( $this->ask->should_ask( $this->day( 20 ) ), 'Only ten days since the checks resumed.' );

		$this->checks_from( 21, 24 );
		$this->assertTrue( $this->ask->should_ask( $this->day( 24 ) ) );
	}

	/**
	 * Each stored check result moves the clock without anyone opening the admin.
	 *
	 * @return void
	 */
	public function test_stored_results_move_the_clock(): void {
		$options = OptionsFactory::get_instance();
		ReviewAsk::register( new SnippetScan( $options ), $options, new Util( $options, new RestAPIServer() ) );

		$stamp = static function ( string $state ): void {
			update_option(
				SnippetScan::OPTION,
				[
					'schema'          => SnippetScan::SCHEMA_VERSION,
					'scanned_at'      => time(),
					'state'           => $state,
					'implementations' => SnippetScan::STATE_FOUND === $state ? self::implementations() : [],
					'duplicate'       => [
						'type'       => '',
						'containers' => [],
						'culprit'    => '',
					],
				],
				false
			);
		};

		$stamp( SnippetScan::STATE_FOUND );
		$this->assertIsInt( get_option( ReviewAsk::OPTION )['first_seen'] );

		$stamp( SnippetScan::STATE_INCONCLUSIVE );
		$this->assertNull( get_option( ReviewAsk::OPTION )['first_seen'] );
	}

	/**
	 * "Not now" puts it off for thirty days, once; the second dismissal is final.
	 *
	 * @return void
	 */
	public function test_snooze_then_reappear_then_final(): void {
		$this->checks_from( 0, 14 );
		$this->ask->record( ReviewAsk::ACTION_SNOOZE, $this->day( 14 ) );

		$this->checks_from( 15, 43 );
		$this->assertFalse( $this->ask->should_ask( $this->day( 43 ) ) );

		$this->check_on( 44 );
		$this->assertTrue( $this->ask->should_ask( $this->day( 44 ) ) );

		$this->ask->record( ReviewAsk::ACTION_SNOOZE, $this->day( 44 ) );
		$this->checks_from( 45, 120 );
		$this->assertFalse( $this->ask->should_ask( $this->day( 120 ) ) );
	}

	/**
	 * "Don't ask again" and following the review link each end it at once.
	 *
	 * @dataProvider final_action_provider
	 *
	 * @param string $action The action.
	 *
	 * @return void
	 */
	public function test_final_actions( string $action ): void {
		$this->checks_from( 0, 14 );
		$this->ask->record( $action, $this->day( 14 ) );

		$this->checks_from( 15, 120 );
		$this->assertFalse( $this->ask->should_ask( $this->day( 120 ) ) );
	}

	/**
	 * The actions that end the ask.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function final_action_provider(): array {
		return [
			"don't ask again" => [ ReviewAsk::ACTION_DISMISS ],
			'review link'     => [ ReviewAsk::ACTION_REVIEWED ],
		];
	}

	/**
	 * The settings screen gets the link only while the ask is due.
	 *
	 * @return void
	 */
	public function test_settings_payload(): void {
		$this->assertNull( $this->ask->add_to_settings( [] )['reviewAsk'] );

		// The payload reads the real clock, so the checks end today.
		$this->now -= 15 * DAY_IN_SECONDS;
		$this->checks_from( 0, 15 );

		$this->assertSame( [ 'url' => ReviewAsk::REVIEW_URL ], $this->ask->add_to_settings( [] )['reviewAsk'] );
	}

	/**
	 * The answer route takes only the three answers, and only from someone
	 * who can manage GTM Kit.
	 *
	 * @return void
	 */
	public function test_answer_route(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();

		$answer = static function ( string $action ) {
			$request = new WP_REST_Request( 'POST', '/gtmkit/v1/review-ask' );
			$request->set_param( 'action', $action );
			return rest_get_server()->dispatch( $request );
		};

		$this->assertSame( 400, $answer( 'maybe' )->get_status() );

		$this->assertSame( 200, $answer( ReviewAsk::ACTION_SNOOZE )->get_status() );
		$this->assertSame( 1, get_option( ReviewAsk::OPTION )['snoozes'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertSame( 401, $answer( ReviewAsk::ACTION_DISMISS )->get_status() );
		$this->assertFalse( get_option( ReviewAsk::OPTION )['done'] );
	}
}
