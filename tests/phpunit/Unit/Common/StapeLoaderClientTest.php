<?php
/**
 * Unit tests for the Stape custom-loader API client.
 *
 * Pattern: BrainMonkey, with `wp_remote_post()` answering from a queue of
 * canned responses and recording every request it receives.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\StapeLoaderClient}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\StapeLoaderClient;
use TLA_Media\GTM_Kit\Options\Options;
use WP_Error;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Client tests over stubbed HTTP.
 */
final class StapeLoaderClientTest extends TestCase {

	/**
	 * The requests sent, in order.
	 *
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	private array $requests = [];

	/**
	 * The responses still to be returned, in order.
	 *
	 * @var array<int, mixed>
	 */
	private array $responses = [];

	/**
	 * Stub the WordPress HTTP functions the client uses.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->requests  = [];
		$this->responses = [];

		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ): bool {
				return $thing instanceof WP_Error;
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return is_array( $response ) ? $response['response']['code'] : '';
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return is_array( $response ) ? $response['body'] : '';
			}
		);
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) {
				$this->requests[] = [
					'url'  => $url,
					'args' => $args,
				];

				return array_shift( $this->responses );
			}
		);
	}

	/**
	 * A client on default settings, so the debug log is off.
	 *
	 * @return StapeLoaderClient
	 */
	private function client(): StapeLoaderClient {
		return new StapeLoaderClient( Options::create() );
	}

	/**
	 * The settings a loader is issued for.
	 *
	 * @param array<string, mixed> $overrides Values to change.
	 *
	 * @return array{identifier: string, gtm_id: string, domain: string, cookie_keeper: bool, datalayer_name: string}
	 */
	private static function inputs( array $overrides = [] ): array {
		return array_merge(
			[
				'identifier'     => '38i0hixjpkyq',
				'gtm_id'         => 'GTM-TW5FD4G7',
				'domain'         => 'collect.gtmkit.com',
				'cookie_keeper'  => false,
				'datalayer_name' => 'dataLayer',
			],
			$overrides
		);
	}

	/**
	 * A canned HTTP response.
	 *
	 * @param int    $code The status code.
	 * @param string $body The body.
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
	 * A captured response body.
	 *
	 * @return string
	 */
	private static function captured(): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk.
		return (string) file_get_contents( __DIR__ . '/fixtures/stape-loader/cookie-keeper-off.json' );
	}

	/**
	 * A container the global region does not know is asked for in the EU region.
	 *
	 * @return void
	 */
	public function test_a_404_is_retried_in_the_eu_region(): void {
		$this->responses = [ self::response( 404, '{"error":{"code":404,"error":"Not Found"}}' ), self::response( 200, self::captured() ) ];

		$result = $this->client()->fetch( self::inputs() );

		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'https://api.app.stape.io/api/v2/container/38i0hixjpkyq/custom-loader', $this->requests[0]['url'] );
		$this->assertSame( 'https://api.app.eu.stape.io/api/v2/container/38i0hixjpkyq/custom-loader', $this->requests[1]['url'] );
		$this->assertSame( $this->requests[0]['args']['body'], $this->requests[1]['args']['body'] );
		$this->assertSame( 'eu', $result['region'] );
		$this->assertSame( '38i0hixjpkyq', $result['loader']['path'] ?? null );
	}

	/**
	 * A container neither region knows is reported, after one retry.
	 *
	 * @return void
	 */
	public function test_a_404_in_both_regions_fails(): void {
		$this->responses = [ self::response( 404, '' ), self::response( 404, '' ) ];

		$result = $this->client()->fetch( self::inputs() );

		$this->assertCount( 2, $this->requests );
		$this->assertNull( $result['loader'] );
		$this->assertSame( 'http_404', $result['reason'] );
	}

	/**
	 * Failures that are not a 404 are reported without a retry.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public function data_failures(): array {
		return [
			'server error'   => [ self::response( 500, '' ), 'http_500' ],
			'bad request'    => [ self::response( 400, '{"body":{"errors":{"webGtmId":["This value is not valid"]}}}' ), 'http_400' ],
			'network error'  => [ new WP_Error( 'http_request_failed', 'timed out' ), StapeLoaderClient::REASON_NETWORK ],
			'not JSON'       => [ self::response( 200, '<html>' ), StapeLoaderClient::REASON_INVALID_JSON ],
			'no loader'      => [ self::response( 200, '{"body":{},"error":{"code":200}}' ), StapeLoaderClient::REASON_NO_LOADER ],
			'foreign domain' => [ self::response( 200, str_replace( 'collect.gtmkit.com', 'evil.example.com', self::captured() ) ), StapeLoaderClient::REASON_UNPARSEABLE ],
		];
	}

	/**
	 * A loader issued for another data layer name is refused, whichever way the names differ.
	 *
	 * @return void
	 */
	public function test_a_loader_for_another_data_layer_is_refused(): void {
		$this->responses = [ self::response( 200, self::captured() ) ];

		$result = $this->client()->fetch( self::inputs( [ 'datalayer_name' => 'gtmkitLayer' ] ) );

		$this->assertNull( $result['loader'] );
		$this->assertSame( StapeLoaderClient::REASON_DATALAYER_MISMATCH, $result['reason'] );
	}

	/**
	 * A loader that names no data layer is refused as unreadable, not as a mismatch.
	 *
	 * @return void
	 */
	public function test_a_loader_naming_no_data_layer_is_refused_as_unreadable(): void {
		$body            = (string) wp_json_encode(
			[
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- A loader under test, not a script this plugin outputs.
				'body'  => [ 'jsCode' => '<script async src="https://collect.gtmkit.com/38i0hixjpkyq.js?3bsw=GB1WNz41RDwmTC00Xj9eTgdEWV5bXg0GTB4fHQERHUYSFgY%3D"></script>' ],
				'error' => [ 'code' => 200 ],
			]
		);
		$this->responses = [ self::response( 200, $body ) ];

		$result = $this->client()->fetch( self::inputs() );

		$this->assertNull( $result['loader'] );
		$this->assertSame( StapeLoaderClient::REASON_UNPARSEABLE, $result['reason'] );
	}

	/**
	 * A loader issued for the data layer name that was asked for is used.
	 *
	 * @return void
	 */
	public function test_a_loader_for_the_same_data_layer_is_used(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk.
		$custom          = (string) file_get_contents( __DIR__ . '/fixtures/stape-loader/custom-datalayer.json' );
		$this->responses = [ self::response( 200, $custom ) ];

		$result = $this->client()->fetch( self::inputs( [ 'datalayer_name' => 'gtmkitLayer' ] ) );

		$this->assertIsArray( $result['loader'] );
		$this->assertSame( '', $result['reason'] );
	}

	/**
	 * Each failure names its reason and yields no loader.
	 *
	 * @dataProvider data_failures
	 *
	 * @param mixed  $response The canned response.
	 * @param string $reason   The expected reason.
	 *
	 * @return void
	 */
	public function test_failures_are_reported( $response, string $reason ): void {
		$this->responses = [ $response ];

		$result = $this->client()->fetch( self::inputs() );

		$this->assertCount( 1, $this->requests );
		$this->assertNull( $result['loader'] );
		$this->assertSame( $reason, $result['reason'] );
	}

	/**
	 * The request goes as JSON, with certificate checks left on and a bounded wait.
	 *
	 * @return void
	 */
	public function test_request_shape(): void {
		$this->responses = [ self::response( 200, self::captured() ) ];

		$this->client()->fetch( self::inputs() );

		$args = $this->requests[0]['args'];
		$this->assertSame( 'application/json', $args['headers']['content-type'] );
		$this->assertSame( StapeLoaderClient::TIMEOUT, $args['timeout'] );
		$this->assertArrayNotHasKey( 'sslverify', $args );
	}

	/**
	 * The body carries the prefixed container ID and the configured data layer name.
	 *
	 * @return void
	 */
	public function test_body_without_cookie_keeper(): void {
		$this->assertSame(
			[
				'webGtmId'            => 'GTM-TW5FD4G7',
				'domain'              => 'collect.gtmkit.com',
				'source'              => 'wordpress',
				'dataLayerObjectName' => 'gtmkitLayer',
			],
			StapeLoaderClient::request_body( self::inputs( [ 'datalayer_name' => 'gtmkitLayer' ] ) )
		);
	}

	/**
	 * With Cookie Keeper on, the body names the Cookie Keeper cookie.
	 *
	 * @return void
	 */
	public function test_body_with_cookie_keeper(): void {
		$body = StapeLoaderClient::request_body( self::inputs( [ 'cookie_keeper' => true ] ) );

		$this->assertSame( 'cookie', $body['userIdentifierType'] );
		$this->assertSame( '_sbp', $body['userIdentifierValue'] );
	}
}
