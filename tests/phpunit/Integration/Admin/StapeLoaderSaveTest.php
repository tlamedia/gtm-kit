<?php
/**
 * Integration tests for fetching the Stape-issued loader on save, refresh and paste.
 *
 * Every request goes through the real REST routes, and every request to Stape
 * is answered by a `pre_http_request` stub that records it.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::set_options()},
 * {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::refresh_sgtm_loader()} and
 * {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::paste_sgtm_loader()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

/**
 * Stape is asked only when it should be, and a failure never blocks a save.
 */
final class StapeLoaderSaveTest extends WP_UnitTestCase {

	/**
	 * The requests sent to Stape, in order.
	 *
	 * @var array<int, array{url: string, body: mixed}>
	 */
	private array $requests = [];

	/**
	 * Responses still to be returned, as status and body. A 200 with the captured loader once empty.
	 *
	 * @var array<int, array{0: int, 1: string}>
	 */
	private array $responses = [];

	/**
	 * Seed a site on a Stape container with the issued loader switched off.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		OptionsFactory::get_instance()->set(
			[
				'general' => [
					'gtm_id'                    => 'GTM-TW5FD4G7',
					'console_log'               => false,
					'datalayer_name'            => '',
					'sgtm_domain'               => 'collect.gtmkit.com',
					'sgtm_container_identifier' => '38i0hixjpkyq',
					'sgtm_cookie_keeper'        => false,
					'sgtm_stape_issued_loader'  => false,
					'google_tag_gateway'        => false,
				],
			]
		);

		delete_option( StapeLoader::OPTION );
		delete_transient( StapeLoader::THROTTLE_TRANSIENT );

		$this->requests  = [];
		$this->responses = [];
		add_filter( 'pre_http_request', [ $this, 'answer_stape' ], 10, 3 );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Remove the HTTP stub.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'answer_stape' ], 10 );

		parent::tear_down();
	}

	/**
	 * Record a request to Stape and answer it from the queue.
	 *
	 * @param false|array<string, mixed> $pre  The short-circuit value.
	 * @param array<string, mixed>       $args The request arguments.
	 * @param string                     $url  The request URL.
	 *
	 * @return false|array<string, mixed>
	 */
	public function answer_stape( $pre, $args, $url ) {
		if ( strpos( (string) $url, 'stape.io' ) === false ) {
			return $pre;
		}

		$this->requests[] = [
			'url'  => (string) $url,
			'body' => json_decode( (string) $args['body'], true ),
		];

		$next = array_shift( $this->responses ) ?? [ 200, self::captured( 'cookie-keeper-off' ) ];

		return [
			'headers'  => [],
			'body'     => $next[1],
			'response' => [
				'code'    => $next[0],
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * A captured Stape response body.
	 *
	 * @param string $name The fixture name.
	 *
	 * @return string
	 */
	private static function captured( string $name ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk.
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/Unit/Common/fixtures/stape-loader/' . $name . '.json' );
	}

	/**
	 * Save the general settings the way the settings app does, with some changed.
	 *
	 * @param array<string, mixed> $changes The settings to change.
	 *
	 * @return WP_REST_Response
	 */
	private function save( array $changes ): WP_REST_Response {
		$general = array_merge( OptionsFactory::get_instance()->get_all_raw()['general'], $changes );

		return $this->post( '/gtmkit/v1/set-options', [ 'general' => $general ] );
	}

	/**
	 * Post to a route.
	 *
	 * @param string                    $route The route.
	 * @param array<string, mixed>|null $body  The JSON body.
	 *
	 * @return WP_REST_Response
	 */
	private function post( string $route, ?array $body = null ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/json' );
		if ( null !== $body ) {
			$request->set_body( (string) wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Switching on fetches once, stores the loader and says so.
	 *
	 * @return void
	 */
	public function test_switching_on_fetches_once(): void {
		$data = $this->save( [ 'sgtm_stape_issued_loader' => true ] )->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertStringStartsWith( 'https://api.app.stape.io/', $this->requests[0]['url'] );
		$this->assertSame( 'fetched', $data['sgtm_loader']['status'] );
		$this->assertSame( 'api', $data['sgtm_loader']['source'] );
		$this->assertSame( 'global', $data['sgtm_loader']['region'] );
		$this->assertTrue( $data['data']['general']['sgtm_stape_issued_loader'] );
		$this->assertSame( '38i0hixjpkyq', get_option( StapeLoader::OPTION )['path'] );
	}

	/**
	 * A save that changes none of the loader's settings does not ask Stape again.
	 *
	 * @return void
	 */
	public function test_an_unrelated_save_does_not_fetch(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$data = $this->save( [ 'console_log' => true ] )->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'unchanged', $data['sgtm_loader']['status'] );
		$this->assertSame( 'api', $data['sgtm_loader']['source'] );
	}

	/**
	 * Changing one of the loader's settings asks again, with the new value.
	 *
	 * @return void
	 */
	public function test_a_changed_input_fetches_again(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$this->responses = [ [ 200, self::captured( 'custom-datalayer' ) ] ];

		$data = $this->save( [ 'datalayer_name' => 'gtmkitLayer' ] )->get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'gtmkitLayer', $this->requests[1]['body']['dataLayerObjectName'] );
		$this->assertSame( 'fetched', $data['sgtm_loader']['status'] );
	}

	/**
	 * The stored container ID goes to Stape as it is, carrying the `GTM-` prefix Stape requires.
	 *
	 * Stape answers an ID without the prefix with HTTP 400, so a change to how
	 * the ID is stored must not strip it on the way out.
	 *
	 * @return void
	 */
	public function test_the_stored_container_id_is_sent_with_its_prefix(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$this->assertSame( OptionsFactory::get_instance()->get( 'general', 'gtm_id' ), $this->requests[0]['body']['webGtmId'] );
		$this->assertStringStartsWith( 'GTM-', $this->requests[0]['body']['webGtmId'] );
	}

	/**
	 * A failed fetch still saves, and the response says why the loader did not arrive.
	 *
	 * @return void
	 */
	public function test_a_failed_fetch_still_saves(): void {
		$this->responses = [ [ 500, '' ] ];

		$response = $this->save(
			[
				'console_log'              => true,
				'sgtm_stape_issued_loader' => true,
			]
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'failed', $data['sgtm_loader']['status'] );
		$this->assertSame( 'http_500', $data['sgtm_loader']['reason'] );
		$this->assertSame( 'standard', $data['sgtm_loader']['source'] );
		$this->assertTrue( get_option( Options::OPTION_NAME )['general']['console_log'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * When the settings change and the new fetch fails, the old loader goes too.
	 *
	 * @return void
	 */
	public function test_a_failed_fetch_after_a_change_removes_the_old_loader(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$this->responses = [ [ 500, '' ] ];

		$this->save( [ 'sgtm_cookie_keeper' => true ] );

		$this->assertCount( 2, $this->requests );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * After a failed fetch, a save that changes none of the loader's settings does not ask Stape again.
	 *
	 * Nothing is stored after a failure, so without this every later save would
	 * wait on Stape, whatever it changed.
	 *
	 * @return void
	 */
	public function test_an_unrelated_save_after_a_failure_does_not_fetch(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$data = $this->save( [ 'console_log' => true ] )->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'unchanged', $data['sgtm_loader']['status'] );
		$this->assertSame( 'standard', $data['sgtm_loader']['source'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * After a failed fetch, changing one of the loader's settings asks Stape again.
	 *
	 * @return void
	 */
	public function test_a_changed_input_after_a_failure_fetches_again(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$data = $this->save( [ 'sgtm_container_identifier' => '38i0kphixjpkyq' ] )->get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'fetched', $data['sgtm_loader']['status'] );
	}

	/**
	 * A container the global region does not know is fetched from the EU region.
	 *
	 * @return void
	 */
	public function test_an_eu_container_is_fetched_from_the_eu_region(): void {
		$this->responses = [ [ 404, '{"error":{"code":404,"error":"Not Found"}}' ], [ 200, self::captured( 'cookie-keeper-off' ) ] ];

		$data = $this->save( [ 'sgtm_stape_issued_loader' => true ] )->get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertStringStartsWith( 'https://api.app.eu.stape.io/', $this->requests[1]['url'] );
		$this->assertSame( 'eu', $data['sgtm_loader']['region'] );
	}

	/**
	 * Switching off removes the stored loader without asking Stape.
	 *
	 * @return void
	 */
	public function test_switching_off_removes_the_loader(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$data = $this->save( [ 'sgtm_stape_issued_loader' => false ] )->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'removed', $data['sgtm_loader']['status'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Refresh always asks, but not twice within a minute.
	 *
	 * @return void
	 */
	public function test_refresh_is_throttled(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$first  = $this->post( '/gtmkit/v1/sgtm-loader-refresh' )->get_data();
		$second = $this->post( '/gtmkit/v1/sgtm-loader-refresh' )->get_data();

		$this->assertSame( 'fetched', $first['data']['status'] );
		$this->assertSame( 'throttled', $second['data']['status'] );
		$this->assertCount( 2, $this->requests );
	}

	/**
	 * Refresh is refused for administrators' eyes only.
	 *
	 * @return void
	 */
	public function test_refresh_requires_the_settings_permission(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->post( '/gtmkit/v1/sgtm-loader-refresh' );

		$this->assertGreaterThanOrEqual( 401, $response->get_status() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Pasted code is stored as values, without asking Stape.
	 *
	 * @return void
	 */
	public function test_pasted_code_is_stored(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$code = json_decode( self::captured( 'cookie-keeper-on' ), true )['body']['jsCode'];
		$data = $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => $code ] )->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'stored', $data['data']['status'] );
		$this->assertSame( 'pasted', $data['data']['source'] );
		$this->assertSame( '3bsw', get_option( StapeLoader::OPTION )['param'] );
	}

	/**
	 * Pasted code that cannot be read is refused and nothing is stored.
	 *
	 * @return void
	 */
	public function test_unreadable_pasted_code_is_refused(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );

		$data = $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => '<script>alert(1)</script>' ] )->get_data();

		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'unparseable', $data['data']['reason'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * A failed refresh keeps the loader Stape issued, and the response says it is still in use.
	 *
	 * @return void
	 */
	public function test_a_failed_refresh_keeps_the_issued_loader(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$stored          = get_option( StapeLoader::OPTION );
		$this->responses = [ [ 500, '' ] ];

		$data = $this->post( '/gtmkit/v1/sgtm-loader-refresh' )->get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'http_500', $data['data']['reason'] );
		$this->assertSame( 'api', $data['data']['source'] );
		$this->assertIsArray( $stored );
		$this->assertSame( $stored, get_option( StapeLoader::OPTION ) );
	}

	/**
	 * A failed refresh keeps a pasted loader, and the response says it is still in use.
	 *
	 * @return void
	 */
	public function test_a_failed_refresh_keeps_the_pasted_loader(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$code = json_decode( self::captured( 'cookie-keeper-on' ), true )['body']['jsCode'];
		$this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => $code ] );
		$stored          = get_option( StapeLoader::OPTION );
		$this->responses = [ [ 500, '' ] ];

		$data = $this->post( '/gtmkit/v1/sgtm-loader-refresh' )->get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'pasted', $data['data']['source'] );
		$this->assertIsArray( $stored );
		$this->assertSame( $stored, get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Unreadable pasted code leaves a stored loader in place, and the response says it is still in use.
	 *
	 * @return void
	 */
	public function test_unreadable_pasted_code_keeps_the_issued_loader(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$stored = get_option( StapeLoader::OPTION );

		$data = $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => '<script>alert(1)</script>' ] )->get_data();

		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'unparseable', $data['data']['reason'] );
		$this->assertSame( 'api', $data['data']['source'] );
		$this->assertIsArray( $stored );
		$this->assertSame( $stored, get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Paste a captured snippet, with the loader from Stape unavailable so nothing is stored first.
	 *
	 * @param string $datalayer_name The data layer name GTM Kit is configured with.
	 * @param string $fixture        The captured response to paste the snippet from.
	 *
	 * @return array<string, mixed> The response data.
	 */
	private function paste_for_layer( string $datalayer_name, string $fixture ): array {
		$this->responses = [ [ 500, '' ] ];
		$this->save(
			[
				'datalayer_name'           => $datalayer_name,
				'sgtm_stape_issued_loader' => true,
			]
		);
		$code = json_decode( self::captured( $fixture ), true )['body']['jsCode'];

		return $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => $code ] )->get_data()['data'];
	}

	/**
	 * Snippets pasted for a data layer name GTM Kit does not use.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_mismatched_pastes(): array {
		return [
			'custom layer pasted, default configured' => [ '', 'custom-datalayer' ],
			'custom layer with Cookie Keeper pasted, default configured' => [ '', 'custom-datalayer-cookie-keeper' ],
			'default layer pasted, custom configured' => [ 'gtmkitLayer', 'cookie-keeper-off' ],
			'default layer with Cookie Keeper pasted, custom configured' => [ 'gtmkitLayer', 'cookie-keeper-on' ],
			'custom layer pasted, another custom configured' => [ 'otherLayer', 'custom-datalayer' ],
		];
	}

	/**
	 * A snippet issued for another data layer name is refused and nothing is stored.
	 *
	 * @dataProvider data_mismatched_pastes
	 *
	 * @param string $datalayer_name The configured data layer name.
	 * @param string $fixture        The captured response pasted.
	 *
	 * @return void
	 */
	public function test_a_snippet_for_another_data_layer_is_refused( string $datalayer_name, string $fixture ): void {
		$data = $this->paste_for_layer( $datalayer_name, $fixture );

		$this->assertSame( 'failed', $data['status'] );
		$this->assertSame( 'datalayer_mismatch', $data['reason'] );
		$this->assertSame( 'standard', $data['source'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Snippets pasted for the data layer name GTM Kit uses.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_matching_pastes(): array {
		return [
			'default layer'                   => [ '', 'cookie-keeper-off' ],
			'default layer, Cookie Keeper on' => [ '', 'cookie-keeper-on' ],
			'default layer named explicitly'  => [ 'dataLayer', 'cookie-keeper-off' ],
			'custom layer'                    => [ 'gtmkitLayer', 'custom-datalayer' ],
			'custom layer, Cookie Keeper on'  => [ 'gtmkitLayer', 'custom-datalayer-cookie-keeper' ],
		];
	}

	/**
	 * A snippet issued for the data layer name GTM Kit uses is stored.
	 *
	 * @dataProvider data_matching_pastes
	 *
	 * @param string $datalayer_name The configured data layer name.
	 * @param string $fixture        The captured response pasted.
	 *
	 * @return void
	 */
	public function test_a_snippet_for_the_same_data_layer_is_stored( string $datalayer_name, string $fixture ): void {
		$data = $this->paste_for_layer( $datalayer_name, $fixture );

		$this->assertSame( 'stored', $data['status'] );
		$this->assertSame( 'pasted', $data['source'] );
	}

	/**
	 * A snippet for another data layer leaves the issued loader in place, and the response says it is still in use.
	 *
	 * @return void
	 */
	public function test_a_snippet_for_another_data_layer_keeps_the_issued_loader(): void {
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		$stored = get_option( StapeLoader::OPTION );
		$code   = json_decode( self::captured( 'custom-datalayer' ), true )['body']['jsCode'];

		$data = $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => $code ] )->get_data();

		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'datalayer_mismatch', $data['data']['reason'] );
		$this->assertSame( 'api', $data['data']['source'] );
		$this->assertIsArray( $stored );
		$this->assertSame( $stored, get_option( StapeLoader::OPTION ) );
	}

	/**
	 * A snippet that names no data layer is refused as unreadable.
	 *
	 * @return void
	 */
	public function test_a_snippet_naming_no_data_layer_is_refused(): void {
		$this->responses = [ [ 500, '' ] ];
		$this->save( [ 'sgtm_stape_issued_loader' => true ] );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- A pasted snippet under test, not a script this plugin outputs.
		$code = '<script async src="https://collect.gtmkit.com/38i0hixjpkyq.js?3bsw=GB1WNz41RDwmTC00Xj9eTgdEWV5bXg0GTB4fHQERHUYSFgY%3D"></script>';

		$data = $this->post( '/gtmkit/v1/sgtm-loader-paste', [ 'code' => $code ] )->get_data();

		$this->assertSame( 'failed', $data['data']['status'] );
		$this->assertSame( 'unparseable', $data['data']['reason'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * A loader issued for another data layer name is refused on save, and nothing is stored.
	 *
	 * @return void
	 */
	public function test_a_fetched_loader_for_another_data_layer_is_refused(): void {
		$this->responses = [ [ 200, self::captured( 'cookie-keeper-off' ) ] ];

		$data = $this->save(
			[
				'datalayer_name'           => 'gtmkitLayer',
				'sgtm_stape_issued_loader' => true,
			]
		)->get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'failed', $data['sgtm_loader']['status'] );
		$this->assertSame( 'datalayer_mismatch', $data['sgtm_loader']['reason'] );
		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}
}
