<?php
/**
 * Integration tests for the settings API check and the offline system data.
 *
 * The result matrix of the check is covered by the unit suite. What needs a
 * booted WordPress is the wiring: that the check is queued the way the
 * Status screen runs it, that its request passes through the real route and
 * the real permission check, and that the system data reaches the settings
 * page with the REST API switched off.
 *
 * Targets: {@see \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api()} and
 * {@see \TLA_Media\GTM_Kit\Common\SupportSync::get_export()} as localised by
 * {@see \TLA_Media\GTM_Kit\Admin\GeneralOptionsPage::localize_script()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\GeneralOptionsPage;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SupportSync;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the settings API check end to end and the REST-free export.
 */
final class RestApiReachabilityTest extends WP_UnitTestCase {

	/**
	 * Run the admin bootstrap and start a fresh REST server.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'gtmkit_version', GTMKIT_VERSION );

		remove_all_filters( 'site_status_tests' );
		remove_all_actions( 'wp_ajax_health-check-' . SiteHealth::REST_API_TEST_ACTION );

		\TLA_Media\GTM_Kit\gtmkit_admin_init();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Build the Site Health integration the way the bootstrap does.
	 *
	 * @return SiteHealth
	 */
	private function site_health(): SiteHealth {
		$options = OptionsFactory::get_instance();

		return new SiteHealth( $options, new Util( $options, new RestAPIServer() ) );
	}

	/**
	 * Serve the check's loopback request through the REST server in-process,
	 * as the current user, the way the real request would be served.
	 *
	 * @return void
	 */
	private function serve_loopback_in_process(): void {
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) {
				if ( 0 !== strpos( $url, rest_url( 'gtmkit/v1/health' ) ) ) {
					return $pre;
				}

				$request = new WP_REST_Request( $args['method'], '/gtmkit/v1/health' );
				$request->set_body( $args['body'] );

				$response = rest_get_server()->dispatch( $request );

				return [
					'headers'  => [],
					'body'     => wp_json_encode( $response->get_data() ),
					'response' => [
						'code'    => $response->get_status(),
						'message' => '',
					],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * The check is queued as an asynchronous admin-ajax test with a handler.
	 */
	public function test_the_check_is_queued_asynchronously_with_an_ajax_handler(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$tests = apply_filters( 'site_status_tests', [] );

		$this->assertArrayHasKey( SiteHealth::REST_API_TEST_ID, $tests['async'] );
		$this->assertArrayNotHasKey( SiteHealth::REST_API_TEST_ID, $tests['direct'] );
		$this->assertNotFalse( has_action( 'wp_ajax_health-check-' . SiteHealth::REST_API_TEST_ACTION ) );
	}

	/**
	 * An administrator's request passes the real route and permission check.
	 */
	public function test_the_check_passes_through_the_real_route_for_an_administrator(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->serve_loopback_in_process();

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'good', $result['status'], wp_strip_all_tags( $result['description'] ) );
	}

	/**
	 * A request the permission check refuses is reported with its status and reason.
	 */
	public function test_the_check_reports_a_refused_request(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->serve_loopback_in_process();

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( '<code>401</code>', $result['description'] );
		$this->assertStringContainsString( '<code>rest_forbidden</code>', $result['description'] );
	}

	/**
	 * Render the settings payload the way the settings page does.
	 *
	 * @return array<string, mixed> The decoded `gtmkitSettings` object.
	 */
	private function render_settings_payload(): array {
		$options = OptionsFactory::get_instance();
		$util    = new Util( $options, new RestAPIServer() );

		wp_register_script( 'gtmkit-settings-script', 'https://example.test/settings.js', [], GTMKIT_VERSION, true );
		( new GeneralOptionsPage( $options, $util ) )->localize_script( 'general', 'settings' );

		$inline = (string) wp_scripts()->get_data( 'gtmkit-settings-script', 'data' );
		$this->assertSame( 1, preg_match( '/var gtmkitSettings = (.*);$/s', $inline, $matches ), 'The settings payload is rendered into the page.' );

		return json_decode( $matches[1], true );
	}

	/**
	 * Without Premium the settings page carries no system data export.
	 */
	public function test_the_page_carries_no_export_without_premium(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$settings = $this->render_settings_payload();

		$this->assertArrayHasKey( 'supportExport', $settings );
		$this->assertNull( $settings['supportExport'] );
	}

	/**
	 * With Premium and the REST API refusing every request, the settings page
	 * still carries the system data, byte-identical to an automatic push
	 * apart from its source label, and nothing asks the plugin's namespace
	 * for it.
	 *
	 * Runs in its own process, because simulating Premium defines a function
	 * that would otherwise stay defined for every later test.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_export_reaches_the_page_with_the_rest_api_unavailable(): void {
		require dirname( __DIR__, 2 ) . '/Unit/Common/Conditionals/fixtures/premium-loader.php';

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		add_filter( 'rest_authentication_errors', static fn() => new WP_Error( 'rest_disabled', 'The REST API is switched off.', [ 'status' => 403 ] ) );

		$dispatched = [];
		add_filter(
			'rest_pre_dispatch',
			static function ( $result, $server, $request ) use ( &$dispatched ) {
				$dispatched[] = $request->get_route();
				return $result;
			},
			10,
			3
		);

		$requested = [];
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$requested ) {
				$requested[] = $url;
				return new WP_Error( 'http_request_blocked', 'Outbound requests are blocked in this test.' );
			},
			10,
			3
		);

		$settings = $this->render_settings_payload();
		$options  = OptionsFactory::get_instance();
		$expected = wp_json_encode( ( new SupportSync( $options, new Util( $options, new RestAPIServer() ) ) )->build_request_body( SupportSync::SOURCE_AUTO ) );

		$this->assertSame( $expected, str_replace( '"source":"export"', '"source":"auto"', $settings['supportExport']['json'] ) );
		$this->assertStringEndsWith( '.json', $settings['supportExport']['filename'] );

		foreach ( array_merge( $dispatched, $requested ) as $route_or_url ) {
			$this->assertStringNotContainsString( 'gtmkit/v1', $route_or_url );
		}
	}
}
