<?php
/**
 * Unit tests for the Site Health integration.
 *
 * Covers the result matrix of both tests and the assembly of the debug
 * information section, since those are the parts a user reads and a
 * support agent acts on.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\SiteHealth}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Result-matrix and debug-section tests for the Site Health integration.
 */
final class SiteHealthTest extends TestCase {

	/**
	 * The `gtmkit` option as stored on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Plugin files the stubbed `is_plugin_active` reports as active.
	 *
	 * @var array<int, string>
	 */
	private array $active_plugins = [];

	/**
	 * The value the stubbed `wp_get_environment_type` returns.
	 *
	 * @var string
	 */
	private string $environment = 'production';

	/**
	 * Stub every WordPress function the integration and its dependencies touch.
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
		if ( ! defined( 'GTMKIT_VERSION' ) ) {
			define( 'GTMKIT_VERSION', '2.18.0' );
		}
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/fake/plugins' );
		}

		Functions\stubs(
			[
				'__'                => null,
				'esc_html'          => null,
				'esc_html__'        => null,
				'esc_url'           => null,
				'sanitize_key'      => static fn( $key ) => strtolower( (string) $key ),
				'admin_url'         => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'network_admin_url' => static fn( $path = '' ) => 'https://example.test/wp-admin/network/' . $path,
				'is_network_admin'  => false,
				'is_multisite'      => false,
				'add_action'        => null,
				'add_filter'        => null,
			]
		);

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => ( Options::OPTION_NAME === $name ) ? $this->stored : $default_value
		);

		Functions\when( 'is_plugin_active' )->alias(
			fn( $plugin_file ) => in_array( $plugin_file, $this->active_plugins, true )
		);

		Functions\when( 'get_plugin_data' )->alias(
			static fn( $plugin_file ) => [ 'Version' => ( strpos( (string) $plugin_file, 'woocommerce' ) !== false ) ? '9.8.1' : '1.7.0' ]
		);

		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );
	}

	/**
	 * Build the system under test against a given `general` option group.
	 *
	 * @param array<string, mixed> $general The stored general options.
	 *
	 * @return SiteHealth The system under test.
	 */
	private function site_health( array $general = [] ): SiteHealth {
		$this->stored = [ 'general' => $general ];

		$options = new Options();

		return new SiteHealth( $options, new Util( $options, new RestAPIServer() ) );
	}

	/**
	 * A container ID with output enabled passes.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_passes_when_configured_and_active(): void {
		$result = $this->site_health(
			[
				'gtm_id'           => 'GTM-ABC123',
				'container_active' => true,
			]
		)->test_container();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'gtmkit_container', $result['test'] );
		$this->assertStringContainsString( 'GTM-ABC123', $result['description'] );
		$this->assertStringContainsString( 'jump.gtmkit.com/link/', $result['actions'] );
	}

	/**
	 * A missing container ID is critical: nothing can be measured at all.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_is_critical_without_a_container_id(): void {
		$result = $this->site_health( [ 'gtm_id' => '' ] )->test_container();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( 'page=gtmkit_general#/container', $result['actions'] );
		$this->assertStringContainsString( 'run-the-setup-wizard', $result['actions'] );
	}

	/**
	 * Container output switched off is a recommendation, described as a
	 * legitimate setup rather than a fault.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_recommends_when_output_is_switched_off(): void {
		$result = $this->site_health(
			[
				'gtm_id'           => 'GTM-ABC123',
				'container_active' => false,
			]
		)->test_container();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Inject Container Code', $result['description'] );
		$this->assertStringContainsString( 'valid setup', $result['description'] );
		$this->assertStringContainsString( 'disable-container-injection', $result['actions'] );
	}

	/**
	 * A filter switching output off is reported as such, so the reader knows
	 * the setting is not what is holding the container back.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_names_the_filter_when_a_filter_switches_output_off(): void {
		Filters\expectApplied( 'gtmkit_container_active' )->andReturn( false );

		$result = $this->site_health(
			[
				'gtm_id'           => 'GTM-ABC123',
				'container_active' => true,
			]
		)->test_container();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'gtmkit_container_active', $result['description'] );
	}

	/**
	 * A local or development install is never nagged with a critical result.
	 *
	 * @dataProvider provide_non_production_environments
	 *
	 * @param string $environment The environment type reported by WordPress.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_never_reports_critical_outside_production( string $environment ): void {
		$this->environment = $environment;

		$result = $this->site_health( [ 'gtm_id' => '' ] )->test_container();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( $environment, $result['description'] );
	}

	/**
	 * The environment types that cap the container result.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_non_production_environments(): array {
		return [
			'local'       => [ 'local' ],
			'development' => [ 'development' ],
		];
	}

	/**
	 * A container withheld because of what the site reports is a correct state.
	 *
	 * It must not reuse the copy about GTM Kit not adding the container, which
	 * describes a misconfiguration rather than a deliberate arrangement.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_reports_a_withheld_container_as_deliberate(): void {
		$this->environment = 'staging';

		$result = $this->site_health(
			[
				'gtm_id'           => 'GTM-ABC123',
				'container_active' => true,
			]
		)->test_container();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'staging', $result['description'] );
		$this->assertStringNotContainsString( 'does not add the container to your pages', $result['label'] );
		$this->assertStringNotContainsString( 'adds its snippet to your pages', $result['description'] );
	}

	/**
	 * With the setting switched on, the ordinary pass is reported again.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_container
	 */
	public function test_container_passes_normally_when_the_setting_loads_it_anyway(): void {
		$this->environment = 'staging';

		$result = $this->site_health(
			[
				'gtm_id'                 => 'GTM-ABC123',
				'container_active'       => true,
				'load_on_non_production' => true,
			]
		)->test_container();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'adds its snippet to your pages', $result['description'] );
	}

	/**
	 * Consent Mode defaults on their own are enough to pass.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_consent
	 */
	public function test_consent_passes_on_defaults_alone(): void {
		$result = $this->site_health( [ 'gcm_default_settings' => true ] )->test_consent();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'gtmkit_consent', $result['test'] );
	}

	/**
	 * A detected consent platform passes on its own and is named.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_consent
	 */
	public function test_consent_passes_on_a_detected_platform_and_names_it(): void {
		$this->active_plugins = [ 'cookiebot/cookiebot.php' ];

		$result = $this->site_health( [ 'gcm_default_settings' => false ] )->test_consent();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Cookiebot', $result['description'] );
		$this->assertStringContainsString( 'coexist-with-a-cmp', $result['actions'] );
	}

	/**
	 * Defaults and a platform together pass and name the platform.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_consent
	 */
	public function test_consent_passes_with_both_and_names_the_platform(): void {
		$this->active_plugins = [ 'cookie-law-info/cookie-law-info.php' ];

		$result = $this->site_health( [ 'gcm_default_settings' => true ] )->test_consent();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'CookieYes', $result['description'] );
	}

	/**
	 * Neither defaults nor a platform is a recommendation, never critical.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_consent
	 */
	public function test_consent_recommends_when_nothing_owns_consent(): void {
		$result = $this->site_health( [ 'gcm_default_settings' => false ] )->test_consent();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'page=gtmkit_general#/consent', $result['actions'] );
		$this->assertStringContainsString( 'turn-on-google-consent-mode-v2-defaults', $result['actions'] );
		$this->assertStringContainsString( 'consent-default-must-run-before-the-container', $result['actions'] );
	}

	/**
	 * Both tests carry the same badge, so they group together in the list.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::build_result
	 */
	public function test_both_tests_share_one_badge(): void {
		$site_health = $this->site_health( [ 'gtm_id' => 'GTM-ABC123' ] );

		$container = $site_health->test_container();
		$consent   = $site_health->test_consent();

		$this->assertSame( $container['badge'], $consent['badge'] );
		$this->assertSame( SiteHealth::BADGE_COLOR, $container['badge']['color'] );
	}

	/**
	 * Both tests are registered as direct tests under their expected ids.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_status_tests
	 */
	public function test_status_tests_are_registered_as_direct_tests(): void {
		$tests = $this->site_health()->add_status_tests( [] );

		$this->assertArrayHasKey( 'gtmkit_container', $tests['direct'] );
		$this->assertArrayHasKey( 'gtmkit_consent', $tests['direct'] );
		$this->assertArrayNotHasKey( SiteHealth::REST_API_TEST_ID, $tests['direct'] );
	}

	/**
	 * The settings API check is queued asynchronously over admin-ajax and
	 * stays out of the weekly cron check.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_status_tests
	 */
	public function test_rest_api_check_is_registered_as_an_async_ajax_test(): void {
		$tests = $this->site_health()->add_status_tests( [] );

		$test = $tests['async'][ SiteHealth::REST_API_TEST_ID ];

		$this->assertSame( SiteHealth::REST_API_TEST_ACTION, $test['test'] );
		$this->assertFalse( $test['has_rest'] );
		$this->assertTrue( $test['skip_cron'] );
		$this->assertArrayNotHasKey( 'async_direct_test', $test );
		$this->assertStringNotContainsString( '_', $test['test'], 'Core rewrites only the first underscore of the action, so the action must carry none.' );
	}

	/**
	 * An add-on test cannot land in the async group through the filter.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_status_tests
	 */
	public function test_filtered_tests_stay_direct(): void {
		$site_health = $this->site_health();

		Filters\expectApplied( 'gtmkit_site_health_tests' )->andReturnUsing(
			static function ( array $tests ) {
				$tests['gtmkit_addon'] = [
					'label' => 'Add-on test',
					'test'  => '__return_true',
				];

				return $tests;
			}
		);

		$tests = $site_health->add_status_tests( [] );

		$this->assertSame( [ SiteHealth::REST_API_TEST_ID ], array_keys( $tests['async'] ) );
	}

	/**
	 * The request the check sends, captured by the stubbed `wp_remote_post`.
	 *
	 * @var array<string, mixed>
	 */
	private array $loopback = [];

	/**
	 * Stub the loopback request to answer with a given response.
	 *
	 * @param array<string, mixed>|\WP_Error $response The response or transport error to return.
	 *
	 * @return void
	 */
	private function stub_loopback( $response ): void {
		$this->loopback = [];

		Functions\when( 'rest_url' )->alias( static fn( $path = '' ) => 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ) );
		Functions\when( 'wp_create_nonce' )->justReturn( 'rest-nonce' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ) => $thing instanceof \WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['response']['code'] ?? '' );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] ?? '' );
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( $response ) {
				$this->loopback = [
					'url'  => $url,
					'args' => $args,
				];

				return $response;
			}
		);
	}

	/**
	 * Build a loopback response.
	 *
	 * @param int    $code The HTTP status code.
	 * @param string $body The response body.
	 *
	 * @return array<string, mixed>
	 */
	private static function response( int $code, string $body ): array {
		return [
			'response' => [ 'code' => $code ],
			'body'     => $body,
		];
	}

	/**
	 * The check posts to the plugin's own namespace, signed in the way the
	 * settings screen signs in, and passes when GTM Kit answers.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api
	 */
	public function test_rest_api_passes_when_gtm_kit_answers(): void {
		$this->stub_loopback( self::response( 200, '{"reachable":true}' ) );

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( SiteHealth::REST_API_TEST_ID, $result['test'] );
		$this->assertSame( 'GTM Kit', $result['badge']['label'] );
		$this->assertStringContainsString( 'from your server to itself', $result['description'] );

		$this->assertSame( 'https://example.test/wp-json/gtmkit/v1/health', $this->loopback['url'] );
		$this->assertSame( 'rest-nonce', $this->loopback['args']['headers']['X-WP-Nonce'] );
		$this->assertSame( 10, $this->loopback['args']['timeout'] );
	}

	/**
	 * A non-2xx answer is critical and names the status code, plus the
	 * reason when WordPress gave one.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api
	 */
	public function test_rest_api_is_critical_on_an_error_status(): void {
		$this->stub_loopback( self::response( 401, '{"code":"rest_forbidden","message":"No.","data":{"status":401}}' ) );

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertStringContainsString( '<code>401</code>', $result['description'] );
		$this->assertStringContainsString( '<code>rest_forbidden</code>', $result['description'] );
		$this->assertStringContainsString( 'from your server to itself', $result['description'] );
		$this->assertStringNotContainsString( 'general#/support', $result['actions'], 'The manual system-data route is Premium-only, so the check does not send everyone to it.' );
	}

	/**
	 * A 2xx answer that did not come from GTM Kit, such as a firewall
	 * challenge page, is not mistaken for a pass.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api
	 */
	public function test_rest_api_is_critical_when_the_answer_is_not_gtm_kits(): void {
		$this->stub_loopback( self::response( 200, '<html><body>Checking your browser</body></html>' ) );

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'GTM Kit\'s settings API returned an unexpected response', $result['label'] );
		$this->assertStringContainsString( 'from your server to itself', $result['description'] );
	}

	/**
	 * No answer within the timeout is critical and reported as a timeout,
	 * not as a status code.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api
	 */
	public function test_rest_api_reports_a_timeout_distinctly(): void {
		$this->stub_loopback( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 10001 milliseconds with 0 bytes received' ) );

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'GTM Kit\'s settings API did not respond', $result['label'] );
		$this->assertStringContainsString( 'no response within 10 seconds', $result['description'] );
		$this->assertStringNotContainsString( 'HTTP status', $result['description'] );
		$this->assertStringContainsString( 'from your server to itself', $result['description'] );
	}

	/**
	 * Any other transport failure is critical and carries the error message.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::test_rest_api
	 */
	public function test_rest_api_reports_other_transport_errors_with_their_message(): void {
		$this->stub_loopback( new \WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) );

		$result = $this->site_health()->test_rest_api();

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( 'GTM Kit\'s settings API could not be reached', $result['label'] );
		$this->assertStringContainsString( 'cURL error 7: Failed to connect', $result['description'] );
	}

	/**
	 * Add-ons register through the filter; malformed entries are ignored.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_status_tests
	 */
	public function test_add_ons_can_register_tests_through_the_filter(): void {
		$site_health = $this->site_health();

		Filters\expectApplied( 'gtmkit_site_health_tests' )->andReturnUsing(
			static function ( array $tests ) {
				$tests['gtmkit_addon']   = [
					'label' => 'Add-on test',
					'test'  => '__return_true',
				];
				$tests['gtmkit_invalid'] = [ 'label' => 'Missing its callback' ];

				return $tests;
			}
		);

		$tests = $site_health->add_status_tests( [] );

		$this->assertArrayHasKey( 'gtmkit_addon', $tests['direct'] );
		$this->assertArrayNotHasKey( 'gtmkit_invalid', $tests['direct'] );
	}

	/**
	 * The debug section reports a bare install without inventing detections.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_on_a_site_without_add_ons_or_ecommerce(): void {
		$fields = $this->site_health(
			[
				'gtm_id'                => 'GTM-ABC123',
				'container_active'      => true,
				'excluded_url_patterns' => [ '/checkout/*', '/cart/*' ],
				'exclude_user_roles'    => [ 'administrator' ],
			]
		)->add_debug_information( [] )['gtmkit']['fields'];

		$this->assertSame( 'GTM-ABC123', $fields['container_id']['value'] );
		$this->assertSame( '2', $fields['excluded_url_patterns']['value'] );
		$this->assertSame( 'administrator', $fields['excluded_user_roles']['value'] );
		$this->assertSame( 'None', $fields['add_ons']['value'] );
		$this->assertSame( 'None detected', $fields['ecommerce']['value'] );
		$this->assertSame( 'None detected', $fields['forms']['value'] );
		$this->assertSame( 'None detected', $fields['cmp']['value'] );
		$this->assertSame( 'Not configured', $fields['container_environment']['value'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $fields );
	}

	/**
	 * The Info tab agrees with the container check on a withheld container.
	 *
	 * The copied report and the check answer the same question, so a staging
	 * copy that has a container configured but deliberately left out must not
	 * be described on the Info tab as adding it to its pages. Support reads
	 * the copied report first.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_reports_a_container_withheld_by_the_environment(): void {
		$this->environment = 'staging';

		$fields = $this->site_health(
			[
				'gtm_id'           => 'GTM-ABC123',
				'container_active' => true,
			]
		)->add_debug_information( [] )['gtmkit']['fields'];

		$this->assertNotSame( 'Yes', $fields['container_output']['value'] );
		$this->assertStringContainsString( 'staging', $fields['container_output']['value'] );
	}

	/**
	 * A staging copy that opts back in is reported as adding the container.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_reports_output_when_a_staging_site_opts_in(): void {
		$this->environment = 'staging';

		$fields = $this->site_health(
			[
				'gtm_id'                 => 'GTM-ABC123',
				'container_active'       => true,
				'load_on_non_production' => true,
			]
		)->add_debug_information( [] )['gtmkit']['fields'];

		$this->assertSame( 'Yes', $fields['container_output']['value'] );
	}

	/**
	 * Active add-ons, ecommerce and form plugins are reported with versions.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_reports_active_plugins_with_versions(): void {
		$this->active_plugins = [
			'gtm-kit-woo/gtm-kit-woo.php',
			'woocommerce/woocommerce.php',
			'contact-form-7/wp-contact-form-7.php',
		];

		$fields = $this->site_health( [ 'sgtm_domain' => 'sgtm.example.test' ] )
			->add_debug_information( [] )['gtmkit']['fields'];

		$this->assertSame( 'GTM Kit Woo Add-On 1.7.0', $fields['add_ons']['value'] );
		$this->assertSame( 'WooCommerce 9.8.1', $fields['ecommerce']['value'] );
		$this->assertSame( 'Contact Form 7 1.7.0', $fields['forms']['value'] );
		$this->assertSame( 'sgtm.example.test', $fields['sgtm_domain']['value'] );
	}

	/**
	 * The container environment values stay out of the copied report.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_marks_the_container_environment_values_private(): void {
		$fields = $this->site_health(
			[
				'gtm_auth'    => 'secret-token',
				'gtm_preview' => 'env-3',
			]
		)->add_debug_information( [] )['gtmkit']['fields'];

		$this->assertSame( 'Configured', $fields['container_environment']['value'] );
		$this->assertTrue( $fields['gtm_auth']['private'] );
		$this->assertTrue( $fields['gtm_preview']['private'] );
		$this->assertArrayNotHasKey( 'private', $fields['container_id'] );
	}

	/**
	 * The section itself is registered with a counted GTM Kit panel.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SiteHealth::add_debug_information
	 */
	public function test_debug_section_is_registered_under_its_own_key(): void {
		$info = $this->site_health()->add_debug_information( [] );

		$this->assertArrayHasKey( 'gtmkit', $info );
		$this->assertTrue( $info['gtmkit']['show_count'] );
		$this->assertSame( 'GTM Kit', $info['gtmkit']['label'] );
	}
}
