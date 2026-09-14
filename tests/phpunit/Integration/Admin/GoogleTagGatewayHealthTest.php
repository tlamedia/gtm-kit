<?php
/**
 * Integration tests for the Google tag gateway health checks.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Common\GoogleTagGateway;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * The checks run whenever their event fires, and only a served tag passes.
 */
final class GoogleTagGatewayHealthTest extends WP_UnitTestCase {

	/**
	 * The daily check is attached on a request that is neither admin nor cron.
	 *
	 * A cron run started from WP-CLI or by an Action Scheduler runner can fire
	 * the event in a request that did not look like cron while plugins loaded.
	 * With nothing attached the event is lost, and the gateway keeps serving
	 * on a result nobody refreshes.
	 *
	 * @return void
	 */
	public function test_the_daily_check_is_attached_outside_admin_and_cron(): void {
		remove_all_actions( GoogleTagGatewayHealth::CHECK_HOOK );

		$this->assertFalse( is_admin() );
		$this->assertFalse( wp_doing_cron() );

		GoogleTagGatewayHealth::register( OptionsFactory::get_instance() );

		$this->assertNotFalse( has_action( GoogleTagGatewayHealth::CHECK_HOOK ) );
	}

	/**
	 * Leave the gateway off, unscheduled and outside the admin between tests.
	 *
	 * The shared Options instance keeps its copy of the settings across the
	 * per-test rollback, so a switch left on here would be read as the stored
	 * state by every later test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		OptionsFactory::get_instance()->set_option( 'general', 'google_tag_gateway', false );
		GoogleTagGatewayHealth::clear_scheduled_event();
		delete_option( GoogleTagGatewayHealth::OPTION );
		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Switch the gateway on or off in the stored settings.
	 *
	 * @param bool $on Whether the gateway is on.
	 *
	 * @return void
	 */
	private function set_gateway( bool $on ): void {
		OptionsFactory::get_instance()->set_option( 'general', 'google_tag_gateway', $on );
	}

	/**
	 * Store a passing health result, so a switch-on is not refused.
	 *
	 * @return void
	 */
	private function store_passing_health(): void {
		update_option(
			GoogleTagGatewayHealth::OPTION,
			[
				'schema'  => GoogleTagGatewayHealth::SCHEMA_VERSION,
				'service' => true,
				'script'  => true,
				'checked' => time(),
			],
			false
		);
	}

	/**
	 * Run the admin bootstrap as an admin request would, and return the
	 * gateway's own admin_init callbacks it registered.
	 *
	 * Only the gateway's callbacks are returned, so a test can run them without
	 * firing everything else WordPress and GTM Kit hang on admin_init.
	 *
	 * @return array<int, callable>
	 */
	private function boot_admin_and_find_gateway_callbacks(): array {
		global $wp_filter;

		update_option( 'gtmkit_version', GTMKIT_VERSION );
		remove_all_actions( 'admin_init' );
		remove_all_actions( GoogleTagGatewayHealth::CHECK_HOOK );
		set_current_screen( 'dashboard' );

		\TLA_Media\GTM_Kit\gtmkit_admin_init();

		$found = [];
		$hook  = $wp_filter['admin_init'] ?? null;
		foreach ( $hook ? $hook->callbacks : [] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof GoogleTagGatewayHealth ) {
					$found[] = $callback['function'];
				}
			}
		}

		return $found;
	}

	/**
	 * An admin page load schedules the daily check while the gateway is on.
	 *
	 * The admin bootstrap is where the schedule is kept, and Action Scheduler
	 * runs its queue over admin-ajax, which comes through the same bootstrap,
	 * so the check itself must be attached there too. Before this was wired,
	 * nothing scheduled the check at all, and a gateway that broke after
	 * switch-on kept serving on its switch-on result.
	 *
	 * @return void
	 */
	public function test_an_admin_page_load_schedules_the_daily_check(): void {
		$this->set_gateway( true );
		GoogleTagGatewayHealth::clear_scheduled_event();

		$callbacks = $this->boot_admin_and_find_gateway_callbacks();

		$this->assertNotEmpty( $callbacks, 'The admin bootstrap must hook the gateway schedule.' );
		$this->assertNotFalse( has_action( GoogleTagGatewayHealth::CHECK_HOOK ), 'The admin bootstrap must attach the check itself.' );

		array_map( 'call_user_func', $callbacks );

		$this->assertNotFalse( wp_next_scheduled( GoogleTagGatewayHealth::CHECK_HOOK ) );
	}

	/**
	 * An admin page load with the gateway off schedules nothing.
	 *
	 * @return void
	 */
	public function test_an_admin_page_load_with_the_gateway_off_schedules_nothing(): void {
		$this->set_gateway( false );
		GoogleTagGatewayHealth::clear_scheduled_event();

		array_map( 'call_user_func', $this->boot_admin_and_find_gateway_callbacks() );

		$this->assertFalse( wp_next_scheduled( GoogleTagGatewayHealth::CHECK_HOOK ) );
	}

	/**
	 * Switching the gateway on through a save schedules the check at once.
	 *
	 * A gateway switched on from the settings screen must not wait for a later
	 * admin page load before anything re-checks it.
	 *
	 * @return void
	 */
	public function test_switching_on_schedules_the_check_immediately(): void {
		$this->store_passing_health();

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'sgtm_domain', '' );
		$this->set_gateway( false );
		GoogleTagGatewayHealth::clear_scheduled_event();

		$options->set( [ 'general' => [ 'google_tag_gateway' => true ] ], false, false );

		$this->assertTrue( (bool) $options->get( 'general', 'google_tag_gateway' ) );
		$this->assertNotFalse( wp_next_scheduled( GoogleTagGatewayHealth::CHECK_HOOK ) );
	}

	/**
	 * Switching the gateway off through a save clears the check.
	 *
	 * @return void
	 */
	public function test_switching_off_clears_the_check(): void {
		$this->set_gateway( true );
		GoogleTagGatewayHealth::schedule();

		OptionsFactory::get_instance()->set( [ 'general' => [ 'google_tag_gateway' => false ] ], false, false );

		$this->assertFalse( wp_next_scheduled( GoogleTagGatewayHealth::CHECK_HOOK ) );
	}

	/**
	 * A check that fires while the gateway is off clears itself and checks nothing.
	 *
	 * @return void
	 */
	public function test_a_check_firing_while_the_gateway_is_off_clears_itself(): void {
		$this->set_gateway( false );
		GoogleTagGatewayHealth::schedule();
		delete_option( GoogleTagGatewayHealth::OPTION );

		( new GoogleTagGatewayHealth( OptionsFactory::get_instance() ) )->run_scheduled_check();

		$this->assertFalse( wp_next_scheduled( GoogleTagGatewayHealth::CHECK_HOOK ) );
		$this->assertFalse( get_option( GoogleTagGatewayHealth::OPTION ), 'No request may be made for a gateway that is off.' );
	}

	/**
	 * The tag probe requests the container the way the page's loader does.
	 *
	 * @return void
	 */
	public function test_the_tag_probe_requests_what_the_page_loader_requests(): void {
		$url = GoogleTagGatewayHealth::tag_request_url( 'GTM-ABC123' );

		$this->assertSame( GoogleTagGateway::proxy_url() . '?id=GTM-ABC123', $url );
		$this->assertStringNotContainsString( 'healthCheck', $url );
	}

	/**
	 * Only a response that is the tag counts as the tag being served.
	 *
	 * The proxy's own `ok` reply is the case that matters: a check satisfied by
	 * it passes on a site where a firewall rule, the web server or the gateway
	 * service stops the tag itself from loading.
	 *
	 * @dataProvider response_provider
	 *
	 * @param array<string, mixed> $response The proxy response.
	 * @param bool                 $served Whether it counts as the tag being served.
	 *
	 * @return void
	 */
	public function test_only_the_tag_counts_as_served( array $response, bool $served ): void {
		$this->assertSame( $served, GoogleTagGatewayHealth::response_serves_tag( $response ) );
	}

	/**
	 * Proxy responses and whether each one is the tag.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function response_provider(): array {
		return [
			'the tag'                     => [
				[
					'statusCode' => 200,
					'headers'    => [ 'Content-Type: application/javascript; charset=UTF-8' ],
					'body'       => 'var data = {};',
				],
				true,
			],
			'the tag as text/javascript'  => [
				[
					'statusCode' => 200,
					'headers'    => [ 'content-type: text/javascript' ],
					'body'       => 'var data = {};',
				],
				true,
			],
			'the proxy health reply'      => [
				[
					'statusCode' => 200,
					'headers'    => [ 'Content-Type: text/html; charset=UTF-8' ],
					'body'       => 'ok',
				],
				false,
			],
			'an empty script'             => [
				[
					'statusCode' => 200,
					'headers'    => [ 'Content-Type: application/javascript' ],
					'body'       => '',
				],
				false,
			],
			'a failed connection'         => [
				[
					'statusCode' => 502,
					'headers'    => [],
					'body'       => '',
				],
				false,
			],
			'a firewall page with status' => [
				[
					'statusCode' => 403,
					'headers'    => [ 'Content-Type: text/html' ],
					'body'       => '<html>Forbidden</html>',
				],
				false,
			],
		];
	}
}
