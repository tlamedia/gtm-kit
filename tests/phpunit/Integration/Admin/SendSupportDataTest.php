<?php
/**
 * Integration tests for sending system data to the support team.
 *
 * Requests go through the real `send-support-data` route. The support
 * server is simulated at the HTTP layer, so each answer it can give, and
 * each way of not answering, is covered.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\AdminAPI::send_support_data()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Common\SupportSync;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers how each outcome of a share is reported to the settings app.
 */
final class SendSupportDataTest extends WP_UnitTestCase {

	/**
	 * Requests made to the support server during the test.
	 *
	 * @var array<int, string>
	 */
	private array $requested = [];

	/**
	 * Sign in an administrator and start a fresh REST server with no sync session.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		delete_option( SupportSync::OPTION );
		$this->requested = [];

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Resetting WordPress's own REST server global so routes register afresh for this test.
		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
	}

	/**
	 * Share system data for a ticket, with the support server answering as given.
	 *
	 * @param array<string, mixed>|WP_Error $answer What the support server returns.
	 * @param string                        $ticket The ticket entered.
	 *
	 * @return array<string, mixed> The response body the settings app reads.
	 */
	private function send( $answer, string $ticket = 'FS123-ABC45' ): array {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $answer ) {
				if ( false === strpos( $url, 'support.gtmkit.com' ) ) {
					return $pre;
				}

				$this->requested[] = $url;

				return $answer;
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'POST', '/gtmkit/v1/send-support-data' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $ticket ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		return $response->get_data();
	}

	/**
	 * Build a support server answer.
	 *
	 * @param int    $code The HTTP status code.
	 * @param string $body The response body.
	 *
	 * @return array<string, mixed>
	 */
	private static function answer( int $code, string $body = '' ): array {
		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'cookies'  => [],
		];
	}

	/**
	 * No answer from the support server is reported as the sending not
	 * working, not as a ticket that does not exist.
	 */
	public function test_an_unreachable_support_server_is_reported_as_such(): void {
		$body = $this->send( new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) );

		$this->assertFalse( $body['success'] );
		$this->assertSame( 'unreachable', $body['data']['reason'] );
		$this->assertStringContainsString( 'could not reach the GTM Kit support server', $body['data']['message'] );
		$this->assertFalse( get_option( SupportSync::OPTION ), 'No sync session starts.' );
	}

	/**
	 * A server error from the support server is reported the same way.
	 */
	public function test_a_support_server_error_is_reported_as_unreachable(): void {
		$body = $this->send( self::answer( 503 ) );

		$this->assertFalse( $body['success'] );
		$this->assertSame( 'unreachable', $body['data']['reason'] );
	}

	/**
	 * A closed ticket keeps its own message, which is not a sending failure.
	 */
	public function test_a_closed_ticket_is_not_a_sending_failure(): void {
		$body = $this->send( self::answer( 410 ) );

		$this->assertFalse( $body['success'] );
		$this->assertIsString( $body['data'] );
		$this->assertStringContainsString( 'closed', $body['data'] );
	}

	/**
	 * An unknown ticket keeps its own message, which is not a sending failure.
	 */
	public function test_an_unknown_ticket_is_not_a_sending_failure(): void {
		$body = $this->send( self::answer( 404 ) );

		$this->assertFalse( $body['success'] );
		$this->assertIsString( $body['data'] );
		$this->assertStringContainsString( 'not found', $body['data'] );
	}

	/**
	 * A malformed ticket is refused without contacting the support server.
	 */
	public function test_a_malformed_ticket_makes_no_request(): void {
		$body = $this->send( self::answer( 200, '{"success":true}' ), 'not-a-ticket' );

		$this->assertFalse( $body['success'] );
		$this->assertStringContainsString( 'not found', $body['data'] );
		$this->assertSame( [], $this->requested );
	}

	/**
	 * An accepted share reports success and starts the sync session.
	 */
	public function test_an_accepted_share_starts_the_sync_session(): void {
		$body = $this->send( self::answer( 200, '{"success":true}' ) );

		$this->assertTrue( $body['success'] );
		$this->assertTrue( $body['data']['supportSync']['active'] );
		$this->assertSame( 'FS123-ABC45', $body['data']['supportSync']['ticket'] );
	}
}
