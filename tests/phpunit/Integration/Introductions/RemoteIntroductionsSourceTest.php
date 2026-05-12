<?php
/**
 * Integration tests for the remote introductions source.
 *
 * Covers:
 *
 *  - Happy path: a valid response yields one Remote_Introduction per item.
 *  - Schema validation drops invalid blocks and intros without breaking the run for valid ones.
 *  - Network failure: an empty array is returned and no exception leaks out.
 *  - Caching: the second call within the 12h transient window does not hit the network.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Remote_Introductions_Source}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Introductions;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Remote_Introduction;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Remote_Introductions_Source;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Covers fetching, validating, and caching remote introductions.
 */
final class RemoteIntroductionsSourceTest extends WP_UnitTestCase {

	/**
	 * The source under test.
	 *
	 * @var Remote_Introductions_Source
	 */
	private Remote_Introductions_Source $source;

	/**
	 * Tracker for the number of HTTP requests intercepted in a test.
	 *
	 * @var int
	 */
	private int $http_request_count = 0;

	/**
	 * Reset state and build a fresh source.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( Remote_Introductions_Source::TRANSIENT );
		remove_all_filters( 'pre_http_request' );
		$this->http_request_count = 0;

		$options      = OptionsFactory::get_instance();
		$util         = new Util( $options, new RestAPIServer() );
		$this->source = new Remote_Introductions_Source( $util );
	}

	/**
	 * Clear the transient and stubs after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_transient( Remote_Introductions_Source::TRANSIENT );
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	/**
	 * Valid JSON in, two Remote_Introduction instances out.
	 *
	 * @return void
	 */
	public function test_happy_path_returns_remote_introductions(): void {
		$this->stub_remote_response( $this->load_fixture() );

		$intros = $this->source->get();

		$this->assertCount( 2, $intros );
		$this->assertContainsOnlyInstancesOf( Remote_Introduction::class, $intros );
		$this->assertSame( 'release-2026-q2-free', $intros[0]->get_id() );
		$this->assertSame( 500, $intros[0]->get_priority() );
		$this->assertSame( 'blocks', $intros[0]->get_render_mode() );
		$this->assertNotEmpty( $intros[0]->get_blocks() );
	}

	/**
	 * Items with no valid blocks are dropped, while sibling items in the same response still come
	 * through.
	 *
	 * @return void
	 */
	public function test_invalid_schema_item_is_dropped(): void {
		$this->stub_remote_response(
			[
				[
					'id'       => 'broken',
					'priority' => 100,
					'blocks'   => [ [ 'type' => 'unknown-block' ], 'not-an-object' ],
				],
				[
					'id'       => 'valid',
					'priority' => 200,
					'blocks'   => [
						[
							'type' => 'heading',
							'text' => 'Hello',
						],
					],
				],
			]
		);

		$intros = $this->source->get();

		$this->assertCount( 1, $intros );
		$this->assertSame( 'valid', $intros[0]->get_id() );
	}

	/**
	 * Each block type is validated and rejected when the shape is wrong.
	 *
	 * @return void
	 */
	public function test_invalid_block_shapes_are_dropped_individually(): void {
		$this->stub_remote_response(
			[
				[
					'id'       => 'mixed',
					'priority' => 100,
					'blocks'   => [
						[ 'type' => 'heading' ], // Missing text.
						[
							'type' => 'heading',
							'text' => 'Kept',
						],
						[
							'type'    => 'cta',
							'label'   => 'No variant',
							'variant' => 'bogus',
						],
						[
							'type'    => 'cta',
							'label'   => 'Dismiss',
							'variant' => 'dismiss',
						],
						[
							'type' => 'image',
							'url'  => 'https://example.test/x.png',
							'alt'  => 'X',
						],
						[
							'type' => 'image',
							'url'  => 'https://example.test/y.png',
						], // Missing alt.
					],
				],
			]
		);

		$intros = $this->source->get();
		$this->assertCount( 1, $intros );

		$types = array_map(
			static fn ( array $b ) => $b['type'],
			$intros[0]->get_blocks()
		);
		$this->assertSame( [ 'heading', 'cta', 'image' ], $types );
	}

	/**
	 * Network failure resolves to an empty list with no exception.
	 *
	 * @return void
	 */
	public function test_network_failure_returns_empty_array(): void {
		add_filter(
			'pre_http_request',
			function () {
				++$this->http_request_count;
				return new \WP_Error( 'test_network_failure', 'Simulated failure.' );
			}
		);

		$intros = $this->source->get();

		$this->assertSame( [], $intros );
		$this->assertSame( 1, $this->http_request_count );
	}

	/**
	 * A second call within the transient window does not hit the network. The transient is
	 * bypassed unconditionally when `WP_DEBUG` is on, so this test only runs against a non-debug
	 * test bootstrap. The intent is documented end-to-end coverage of the cache-hit path; the
	 * cache-miss path is exercised by every other test in this file.
	 *
	 * @return void
	 */
	public function test_transient_caches_subsequent_calls(): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->markTestSkipped( 'Util::get_data() bypasses the transient when WP_DEBUG is true.' );
		}

		// Manually seed the transient so the cached path is taken.
		set_transient(
			Remote_Introductions_Source::TRANSIENT,
			$this->load_fixture(),
			12 * HOUR_IN_SECONDS
		);

		add_filter(
			'pre_http_request',
			function () {
				++$this->http_request_count;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '[]',
				];
			}
		);

		$intros = $this->source->get();

		$this->assertCount( 2, $intros );
		$this->assertSame( 'release-2026-q2-free', $intros[0]->get_id() );
		$this->assertSame( 0, $this->http_request_count, 'Transient hit should not trigger an HTTP request.' );
	}

	/**
	 * The outgoing request includes a `version=<plugin version>` query arg so the endpoint can
	 * apply per-intro min/max version gates.
	 *
	 * @return void
	 */
	public function test_request_includes_plugin_version_arg(): void {
		$captured_url = '';
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				unset( $preempt, $args );
				$captured_url = (string) $url;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '[]',
				];
			},
			10,
			3
		);

		$this->source->get();

		$this->assertStringContainsString( 'version=' . rawurlencode( GTMKIT_VERSION ), $captured_url );
		$this->assertStringContainsString( 'plugins%5B', $captured_url );
	}

	/**
	 * The `version` query arg is scoped to the introductions request. Other `Util::get_data`
	 * callers must not have `version` appended on their behalf.
	 *
	 * @return void
	 */
	public function test_version_arg_is_not_sent_by_other_callers(): void {
		$captured_url = '';
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$captured_url ) {
				unset( $preempt, $args );
				$captured_url = (string) $url;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '[]',
				];
			},
			10,
			3
		);

		$util = new Util( OptionsFactory::get_instance(), new RestAPIServer() );
		$util->get_data( '/some-other-endpoint', 'gtmkit_some_other_transient' );

		$this->assertStringNotContainsString( 'version=', $captured_url );
		$this->assertStringContainsString( 'plugins%5B', $captured_url );

		delete_transient( 'gtmkit_some_other_transient' );
	}

	/**
	 * Short-circuit `wp_remote_get` with the given decoded payload.
	 *
	 * @param array<int, array<string, mixed>> $payload Decoded payload.
	 *
	 * @return void
	 */
	private function stub_remote_response( array $payload ): void {
		add_filter(
			'pre_http_request',
			function () use ( $payload ) {
				++$this->http_request_count;
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( $payload ),
				];
			}
		);
	}

	/**
	 * Load the JSON fixture shipped with the test suite.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function load_fixture(): array {
		$path = dirname( __DIR__, 2 ) . '/../fixtures/remote-introductions.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local fixture file from the test suite; WP_Filesystem is unnecessary here.
		$data = json_decode( (string) file_get_contents( $path ), true );
		$this->assertIsArray( $data, 'Fixture should decode to an array.' );
		return $data;
	}
}
