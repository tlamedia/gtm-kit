<?php
/**
 * Unit tests for how the tracking scan schedules itself.
 *
 * The scan is only re-armed from `admin_init`, so whatever schedule it leaves
 * behind is the only thing keeping it daily on a site nobody logs into. A
 * one-shot action there degrades "once a day" into "once per admin visit",
 * and the stale result then sits behind a Site Health check and a dashboard
 * notice that both present it as current.
 *
 * The WP-Cron branch is covered by the integration suite against real
 * WordPress; these cover the Action Scheduler branch, which needs
 * `class_exists( 'ActionScheduler' )` to be true. That cannot be stubbed, so
 * the class is declared inside an isolated process and never leaks into the
 * rest of the suite.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\SnippetScan}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Scheduling tests for the Action Scheduler branch.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class SnippetScanSchedulingTest extends TestCase {

	/**
	 * Scheduling calls recorded during a test.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $calls = [];

	/**
	 * What the stubbed `as_next_scheduled_action` reports.
	 *
	 * @var mixed
	 */
	private $existing_action = false;

	/**
	 * Common setup.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		require_once __DIR__ . '/../stubs/action-scheduler.php';

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			// A WordPress core constant the scheduler reads, not a plugin one.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standing in for core in a bare PHPUnit process.
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$this->calls           = [];
		$this->existing_action = false;

		Functions\stubs(
			[
				'is_plugin_active' => false,
				'get_option'       => [],
			]
		);

		Functions\when( 'as_next_scheduled_action' )->alias( fn() => $this->existing_action );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );
		Functions\when( 'as_unschedule_all_actions' )->alias(
			function ( ...$args ) {
				$this->calls[] = [
					'fn'   => 'as_unschedule_all_actions',
					'args' => $args,
				];
			}
		);

		foreach ( [ 'as_schedule_recurring_action', 'as_schedule_single_action', 'wp_schedule_event' ] as $scheduler ) {
			Functions\when( $scheduler )->alias(
				function ( ...$args ) use ( $scheduler ) {
					$this->calls[] = [
						'fn'   => $scheduler,
						'args' => $args,
					];
					return true;
				}
			);
		}
	}

	/**
	 * Build a scan over empty options.
	 *
	 * @return SnippetScan The system under test.
	 */
	private function scan(): SnippetScan {
		return new SnippetScan( new Options() );
	}

	/**
	 * The scan arms a recurrence, not a single run.
	 *
	 * A single action fires once and enqueues no successor, so nothing
	 * re-arms the scan until an administrator next loads wp-admin.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SnippetScan::schedule_daily_event
	 */
	public function test_the_action_scheduler_branch_arms_a_daily_recurrence(): void {
		$this->scan()->schedule_daily_event();

		$this->assertSame( [ 'as_schedule_recurring_action' ], array_column( $this->calls, 'fn' ) );

		$args = $this->calls[0]['args'];

		$this->assertSame( DAY_IN_SECONDS, $args[1], 'The recurrence has to be daily.' );
		$this->assertSame( SnippetScan::SCAN_HOOK, $args[2] );
	}

	/**
	 * Deactivation cancels what scheduling armed.
	 *
	 * `as_unschedule_all_actions()` only matches on hook, args and group, so
	 * the two calls have to agree or a deactivated plugin leaves a recurring
	 * scan running.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SnippetScan::schedule_daily_event
	 * @covers \TLA_Media\GTM_Kit\Common\SnippetScan::clear_scheduled_event
	 */
	public function test_deactivation_cancels_the_recurrence_it_armed(): void {
		$this->scan()->schedule_daily_event();
		SnippetScan::clear_scheduled_event();

		$scheduled = $this->calls[0];
		$cancelled = $this->calls[1];

		$this->assertSame( 'as_schedule_recurring_action', $scheduled['fn'] );
		$this->assertSame( 'as_unschedule_all_actions', $cancelled['fn'] );

		// Scheduling is (timestamp, interval, hook, args, group); cancelling
		// is (hook, args, group).
		$this->assertSame( $scheduled['args'][2], $cancelled['args'][0], 'Hooks must match.' );
		$this->assertSame( $scheduled['args'][3], $cancelled['args'][1], 'Args must match.' );
		$this->assertSame( $scheduled['args'][4], $cancelled['args'][2], 'Groups must match.' );
	}

	/**
	 * The first run is anchored to the same point WP-Cron uses.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SnippetScan::schedule_daily_event
	 */
	public function test_the_first_run_matches_the_cron_branch_anchor(): void {
		$this->scan()->schedule_daily_event();

		$this->assertSame( strtotime( 'midnight' ), $this->calls[0]['args'][0] );
	}

	/**
	 * Run the 2.18.1 upgrade routine the way `plugins_loaded` does.
	 */
	private function run_v2181_upgrade(): void {
		$upgrade = new \ReflectionMethod( Upgrade::class, 'v2181_upgrade' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$upgrade->setAccessible( true );
		}
		$upgrade->invoke( ( new \ReflectionClass( Upgrade::class ) )->newInstanceWithoutConstructor() );
	}

	/**
	 * Upgrading from 2.18.0 clears the stale one-shot so the recurrence can arm.
	 *
	 * The scheduler only arms when nothing is pending, and a site coming from
	 * 2.18.0 still carries that release's one-shot action. Without the upgrade
	 * routine cancelling it, the guard sees it, skips arming, and the site runs
	 * one more cycle of exactly the behaviour this release fixes.
	 *
	 * The cancel waits for `init`, which WordPress fires before `admin_init`,
	 * so it still clears the way for the recurrence on the same request.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::v2181_upgrade
	 */
	public function test_the_upgrade_routine_clears_a_stale_one_shot_before_arming(): void {
		// A site on 2.18.0: a one-shot is pending, so the guard would block.
		$this->existing_action = 4242;

		$on_init = null;
		Actions\expectAdded( 'init' )->once()->whenHappen(
			function ( $callback ) use ( &$on_init ) {
				$on_init = $callback;
			}
		);

		$this->run_v2181_upgrade();

		$this->assertIsCallable( $on_init, 'The cancel has to be queued for init.' );
		$on_init();

		$this->assertSame(
			[ 'as_unschedule_all_actions' ],
			array_column( $this->calls, 'fn' ),
			'The upgrade routine has to cancel the stale action.'
		);

		// With it cancelled, the next admin_init arms the recurrence.
		$this->existing_action = false;
		$this->calls           = [];
		$this->scan()->schedule_daily_event();

		$this->assertSame( [ 'as_schedule_recurring_action' ], array_column( $this->calls, 'fn' ) );
	}

	/**
	 * The upgrade routine leaves Action Scheduler alone until `init`.
	 *
	 * Upgrades run on `plugins_loaded`, before Action Scheduler has set up its
	 * data store, and calling its API that early logs a notice.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::v2181_upgrade
	 */
	public function test_the_upgrade_routine_makes_no_action_scheduler_call_before_init(): void {
		$this->existing_action = 4242;

		$this->run_v2181_upgrade();

		$this->assertSame( [], $this->calls, 'Nothing may reach Action Scheduler before init.' );
		$this->assertNotFalse(
			has_action( 'init', [ SnippetScan::class, 'clear_scheduled_event' ] ),
			'The cancel has to be queued for init.'
		);
	}

	/**
	 * Once `init` has fired, the upgrade routine cancels straight away.
	 *
	 * A callback added to `init` after it has fired would never run, so the
	 * stale one-shot would survive.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::v2181_upgrade
	 */
	public function test_the_upgrade_routine_cancels_immediately_after_init(): void {
		do_action( 'init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Stands in for WordPress core firing its own hook.

		$this->run_v2181_upgrade();

		$this->assertSame( [ 'as_unschedule_all_actions' ], array_column( $this->calls, 'fn' ) );
	}

	/**
	 * A second admin request does not stack a duplicate.
	 *
	 * `schedule_daily_event()` runs on every admin_init, so this guard is what
	 * stops a busy site accumulating scans.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SnippetScan::schedule_daily_event
	 */
	public function test_scheduling_is_idempotent(): void {
		$this->scan()->schedule_daily_event();
		$this->assertCount( 1, $this->calls );

		// The first call armed it, so the next admin request sees it pending.
		$this->existing_action = 12345;
		$this->scan()->schedule_daily_event();

		$this->assertCount( 1, $this->calls, 'A pending scan must not be scheduled a second time.' );
	}
}
