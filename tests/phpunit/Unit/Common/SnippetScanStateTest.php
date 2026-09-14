<?php
/**
 * Unit tests for the tracking scan's state machine.
 *
 * The three result states are what every consumer of the scan reasons about,
 * and the whole value of the feature rests on `inconclusive` being produced
 * whenever the fetch could not answer the question. A scan that reported
 * "nothing is loading" after a failed request would tell people their
 * measurement is broken when it is not.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\SnippetScan}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Options\Options;
use WP_Error;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * State-machine and duplicate-classification tests.
 */
final class SnippetScanStateTest extends TestCase {

	/**
	 * The `gtmkit` option as stored on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * The response `wp_remote_get` is stubbed to return.
	 *
	 * @var array<string, mixed>|WP_Error
	 */
	private $response;

	/**
	 * Plugin files the stubbed `is_plugin_active` reports as active.
	 *
	 * @var array<int, string>
	 */
	private array $active_plugins = [];

	/**
	 * The result the engine last wrote.
	 *
	 * @var array<string, mixed>
	 */
	private array $written = [];

	/**
	 * The value the stubbed `wp_get_environment_type` returns.
	 *
	 * @var string
	 */
	private string $environment = 'production';

	/**
	 * Stub every WordPress function the engine and its dependencies touch.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->stored         = [
			'gtmkit' => [
				'general' => [
					'gtm_id'                    => 'GTM-ABCD123',
					'container_active'          => true,
					'sgtm_domain'               => '',
					'sgtm_container_identifier' => '',
					'excluded_url_patterns'     => [],
				],
			],
		];
		$this->active_plugins = [];
		$this->written        = [];
		$this->environment    = 'production';
		$this->response       = $this->html_response( '<html><body>nothing</body></html>' );

		Functions\stubs(
			[
				'__'           => null,
				'home_url'     => static fn( $path = '' ) => 'https://example.test' . $path,
				'sanitize_key' => static fn( $key ) => strtolower( (string) $key ),
			]
		);

		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );

		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- BrainMonkey stub stands in for wp_parse_url() with no WP available; PHP's native parse_url() is the only option here.
				return \parse_url( $url, $component );
			}
		);

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->stored[ $name ] = $value;
				$this->written         = is_array( $value ) ? $value : [];

				return true;
			}
		);

		Functions\when( 'is_plugin_active' )->alias(
			fn( $plugin_file ) => in_array( $plugin_file, $this->active_plugins, true )
		);

		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = [] ) => array_merge( (array) $defaults, (array) $args )
		);

		Functions\when( 'wp_remote_get' )->alias( fn() => $this->response );

		Functions\when( 'is_wp_error' )->alias( static fn( $thing ) => $thing instanceof WP_Error );

		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( $response ) => $response['response']['code'] ?? 0
		);

		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => $response['body'] ?? ''
		);

		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static fn( $response, $header ) => $response['headers'][ $header ] ?? ''
		);
	}

	/**
	 * Build a stubbed HTTP response.
	 *
	 * @param string $body The response body.
	 * @param int    $code The HTTP status code.
	 * @param string $content_type The response content type.
	 *
	 * @return array<string, mixed>
	 */
	private function html_response( string $body, int $code = 200, string $content_type = 'text/html; charset=UTF-8' ): array {
		return [
			'body'     => $body,
			'response' => [ 'code' => $code ],
			'headers'  => [ 'content-type' => $content_type ],
		];
	}

	/**
	 * Read a fixture page.
	 *
	 * @param string $name The fixture file name, without its extension.
	 *
	 * @return string The page HTML.
	 */
	private function fixture( string $name ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk; the remote-request alternative does not apply.
		return (string) file_get_contents( __DIR__ . '/fixtures/snippet-scan/' . $name . '.html' );
	}

	/**
	 * Run one scan against the stubbed response.
	 *
	 * @return array<string, mixed> The stored result.
	 */
	private function scan(): array {
		return ( new SnippetScan( new Options() ) )->run_scan();
	}

	/**
	 * A page carrying a container is `found`.
	 */
	public function test_a_page_with_a_container_is_found(): void {
		$this->response = $this->html_response( $this->fixture( 'gtmkit-standard' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_FOUND, $result['state'] );
		$this->assertSame( '', $result['reason'] );
		$this->assertSame( '', $result['duplicate']['type'] );
	}

	/**
	 * A site that is not production records that no container was intended.
	 *
	 * Site Health reads this field to tell an expected absence from a
	 * container that something stripped out of the page.
	 */
	public function test_a_site_that_is_not_production_intends_no_container(): void {
		$this->environment = 'staging';
		$this->response    = $this->html_response( $this->fixture( 'no-tracking' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_NOT_FOUND, $result['state'] );
		$this->assertFalse( $result['container_output'] );
	}

	/**
	 * With the setting switched on, a container is intended again.
	 */
	public function test_the_setting_restores_the_intended_container(): void {
		$this->environment = 'staging';
		$this->stored['gtmkit']['general']['load_on_non_production'] = true;
		$this->response = $this->html_response( $this->fixture( 'no-tracking' ) );

		$this->assertTrue( $this->scan()['container_output'] );
	}

	/**
	 * A complete page carrying nothing is `not_found`.
	 */
	public function test_a_complete_page_without_tracking_is_not_found(): void {
		$this->response = $this->html_response( $this->fixture( 'no-tracking' ) );

		$this->assertSame( SnippetScan::STATE_NOT_FOUND, $this->scan()['state'] );
	}

	/**
	 * The result is written to the option, not autoloaded, and readable back.
	 */
	public function test_the_result_round_trips_through_the_option(): void {
		$this->response = $this->html_response( $this->fixture( 'gtmkit-standard' ) );

		$engine = new SnippetScan( new Options() );
		$engine->run_scan();

		$this->assertSame( SnippetScan::STATE_FOUND, $this->written['state'] );
		$this->assertSame( SnippetScan::SCHEMA_VERSION, $this->written['schema'] );
		$this->assertTrue( $engine->has_container() );
		$this->assertFalse( $engine->has_duplicate_tracking() );
	}

	/**
	 * A stored result from another schema version is ignored rather than read.
	 */
	public function test_a_result_from_another_schema_version_is_ignored(): void {
		$this->stored[ SnippetScan::OPTION ] = [
			'schema' => SnippetScan::SCHEMA_VERSION + 1,
			'state'  => SnippetScan::STATE_FOUND,
		];

		$engine = new SnippetScan( new Options() );

		$this->assertNull( $engine->get_result() );
		$this->assertNull( $engine->has_container() );
	}

	/**
	 * A transport error against the site's own URL reports a blocked loopback.
	 */
	public function test_a_failed_request_is_inconclusive(): void {
		$this->response = new WP_Error( 'http_request_failed' );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_LOOPBACK_BLOCKED, $result['reason'] );
	}

	/**
	 * A transport error of another kind is reported as a plain failure.
	 */
	public function test_another_transport_error_is_reported_as_a_request_failure(): void {
		$this->response = new WP_Error( 'http_no_url' );

		$this->assertSame( SnippetScan::REASON_REQUEST_FAILED, $this->scan()['reason'] );
	}

	/**
	 * A non-2xx response is inconclusive and records the status.
	 */
	public function test_a_non_2xx_response_is_inconclusive(): void {
		$this->response = $this->html_response( '<html><body>Service unavailable</body></html>', 503 );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_HTTP_STATUS, $result['reason'] );
		$this->assertSame( 503, $result['status'] );
	}

	/**
	 * An empty body is inconclusive.
	 */
	public function test_an_empty_response_is_inconclusive(): void {
		$this->response = $this->html_response( '   ' );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_EMPTY_RESPONSE, $result['reason'] );
	}

	/**
	 * A response that is not HTML is inconclusive.
	 */
	public function test_a_non_html_response_is_inconclusive(): void {
		$this->response = $this->html_response( '{"ok":true}', 200, 'application/json' );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_NOT_HTML, $result['reason'] );
	}

	/**
	 * A truncated page proves nothing about what it does not contain.
	 */
	public function test_a_truncated_response_is_inconclusive(): void {
		$this->response = $this->html_response( $this->fixture( 'truncated' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_INCOMPLETE_RESPONSE, $result['reason'] );
	}

	/**
	 * Evidence found before the response was cut off still counts.
	 */
	public function test_a_truncated_response_carrying_a_container_is_still_found(): void {
		$this->response = $this->html_response(
			'<html><head><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":1});})(window,document,"script","dataLayer","GTM-ABCD123");</script><p>cut off'
		);

		$this->assertSame( SnippetScan::STATE_FOUND, $this->scan()['state'] );
	}

	/**
	 * A scan URL GTM Kit deliberately outputs nothing on proves nothing.
	 */
	public function test_an_excluded_scan_url_is_inconclusive(): void {
		$this->stored['gtmkit']['general']['excluded_url_patterns'] = [
			[
				'pattern' => '/',
				'mode'    => 'glob',
			],
		];
		$this->response = $this->html_response( $this->fixture( 'no-tracking' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $result['state'] );
		$this->assertSame( SnippetScan::REASON_URL_EXCLUDED, $result['reason'] );
	}

	/**
	 * An active consent platform is recorded with the finding, so a consumer
	 * can tell a deliberate setup from a broken one.
	 */
	public function test_an_active_consent_platform_is_recorded_with_the_finding(): void {
		$this->active_plugins = [ 'cookiebot/cookiebot.php' ];
		$this->response       = $this->html_response( $this->fixture( 'no-tracking' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::STATE_NOT_FOUND, $result['state'] );
		$this->assertSame( 'cookiebot', $result['cmp'] );
	}

	/**
	 * Two containers are classified as two containers.
	 */
	public function test_two_containers_are_classified_as_such(): void {
		$this->response = $this->html_response( $this->fixture( 'two-containers' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_CONTAINERS, $result['duplicate']['type'] );
		$this->assertSame( [ 'GTM-ABCD123', 'GTM-ZZZZ999' ], $result['duplicate']['containers'] );
	}

	/**
	 * The same container twice is classified as a repeated container.
	 */
	public function test_the_same_container_twice_is_classified_as_repeated(): void {
		$this->response = $this->html_response( $this->fixture( 'same-container-twice' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_REPEATED, $result['duplicate']['type'] );
		$this->assertSame( [ 'GTM-ABCD123' ], $result['duplicate']['containers'] );
	}

	/**
	 * A Google tag beside the container is classified separately, because the
	 * fix differs from a second container.
	 */
	public function test_a_google_tag_beside_the_container_is_classified_separately(): void {
		$this->response = $this->html_response( $this->fixture( 'gtmkit-plus-stray-gtag' ) );

		$this->assertSame( SnippetScan::DUPLICATE_GTAG, $this->scan()['duplicate']['type'] );
	}

	/**
	 * The noscript iframe never turns a single load into a duplicate.
	 */
	public function test_the_noscript_iframe_is_not_a_duplicate(): void {
		$this->response = $this->html_response( $this->fixture( 'gtmkit-standard' ) );

		$this->assertSame( '', $this->scan()['duplicate']['type'] );
	}

	/**
	 * An active conflicting plugin names the culprit.
	 */
	public function test_an_active_conflicting_plugin_names_the_culprit(): void {
		$this->active_plugins = [ 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php' ];
		$this->response       = $this->html_response( $this->fixture( 'two-containers' ) );

		$this->assertSame( 'Google Tag Manager for WordPress', $this->scan()['duplicate']['culprit'] );
	}

	/**
	 * With no plugin to blame, a shipped signature names the culprit.
	 */
	public function test_a_signature_names_the_culprit_when_no_plugin_is_active(): void {
		$this->response = $this->html_response( $this->fixture( 'same-container-twice' ) );

		$this->assertSame( 'WPCode', $this->scan()['duplicate']['culprit'] );
	}

	/**
	 * An active plugin whose own Google tag is in the page names the culprit.
	 */
	public function test_an_active_google_tag_source_names_the_culprit(): void {
		$this->active_plugins = [ 'google-listings-and-ads/google-listings-and-ads.php' ];
		$this->response       = $this->html_response( $this->fixture( 'gtmkit-plus-google-for-woocommerce' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_GTAG, $result['duplicate']['type'] );
		$this->assertSame( 'Google for WooCommerce', $result['duplicate']['culprit'] );
	}

	/**
	 * With that plugin inactive, its marker alone names nothing.
	 */
	public function test_an_inactive_google_tag_source_is_not_named(): void {
		$this->response = $this->html_response( $this->fixture( 'gtmkit-plus-google-for-woocommerce' ) );

		$this->assertSame( '', $this->scan()['duplicate']['culprit'] );
	}

	/**
	 * An active plugin that is not outputting its tag is not blamed for another one.
	 *
	 * The plugin can be active without adding any tag, for example before it
	 * is connected to an account, so a Google tag without its marker came
	 * from somewhere else.
	 */
	public function test_an_active_google_tag_source_without_its_tag_is_not_named(): void {
		$this->active_plugins = [ 'google-listings-and-ads/google-listings-and-ads.php' ];
		$this->response       = $this->html_response( $this->fixture( 'gtmkit-plus-stray-gtag' ) );

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_GTAG, $result['duplicate']['type'] );
		$this->assertSame( '', $result['duplicate']['culprit'] );
	}

	/**
	 * A signature in the page still names its tool while that plugin is merely active.
	 */
	public function test_a_signature_is_not_overridden_by_an_idle_google_tag_source(): void {
		$this->active_plugins = [ 'google-listings-and-ads/google-listings-and-ads.php' ];
		$this->response       = $this->html_response(
			str_replace(
				'<p>Hello world.</p>',
				'<!-- snippet added by WPCode --><p>Hello world.</p>',
				$this->fixture( 'gtmkit-plus-stray-gtag' )
			)
		);

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_GTAG, $result['duplicate']['type'] );
		$this->assertSame( 'WPCode', $result['duplicate']['culprit'] );
	}

	/**
	 * A plugin that only loads a Google tag cannot explain a second container.
	 */
	public function test_a_google_tag_source_is_not_blamed_for_a_second_container(): void {
		$this->active_plugins = [ 'google-listings-and-ads/google-listings-and-ads.php' ];
		$this->response       = $this->html_response(
			'<html><head>'
			. '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":1});})(window,document,"script","dataLayer","GTM-ABCD123");</script>'
			. '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":1});})(window,document,"script","dataLayer","GTM-ZZZZ999");</script>'
			. '</head><body><p>hi</p></body></html>'
		);

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_CONTAINERS, $result['duplicate']['type'] );
		$this->assertSame( '', $result['duplicate']['culprit'] );
	}

	/**
	 * With nothing to go on, the finding is reported without a name.
	 */
	public function test_an_unrecognised_culprit_is_left_unnamed(): void {
		$this->response = $this->html_response(
			'<html><head>'
			. '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":1});})(window,document,"script","dataLayer","GTM-ABCD123");</script>'
			. '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":1});})(window,document,"script","dataLayer","GTM-ZZZZ999");</script>'
			. '</head><body><p>hi</p></body></html>'
		);

		$result = $this->scan();

		$this->assertSame( SnippetScan::DUPLICATE_CONTAINERS, $result['duplicate']['type'] );
		$this->assertSame( '', $result['duplicate']['culprit'] );
	}

	/**
	 * An inconclusive scan never reports a duplicate.
	 */
	public function test_an_inconclusive_scan_reports_no_duplicate(): void {
		$this->response = new WP_Error( 'http_request_failed' );

		$engine = new SnippetScan( new Options() );
		$engine->run_scan();

		$this->assertSame( '', $engine->get_result()['duplicate']['type'] );
		$this->assertNull( $engine->has_container() );
		$this->assertNull( $engine->has_duplicate_tracking() );
	}
}
