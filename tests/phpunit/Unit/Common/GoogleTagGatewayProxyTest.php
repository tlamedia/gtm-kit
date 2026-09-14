<?php
/**
 * Unit tests for the vendored Google tag gateway proxy.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Google\GoogleTagGatewayLibrary\Http\RequestHelper;
use Google\GoogleTagGatewayLibrary\Http\ServerRequestContext;
use Google\GoogleTagGatewayLibrary\Proxy\Measurement;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * The proxy forwards to the gateway service and nowhere else.
 *
 * The file forwards a visitor's measurement requests onward, so the question
 * that matters about it is not what it does with a well-formed request but
 * what it refuses. The tag id becomes the host it connects to, so anything
 * that could steer that host away from the gateway service would turn the
 * file into an open proxy sitting on the site's own domain.
 *
 * These assertions cover the vendored file rather than code written here, on
 * purpose: the file is re-synced from upstream, and this is what has to keep
 * being true after a re-sync.
 */
final class GoogleTagGatewayProxyTest extends TestCase {

	/**
	 * Load the proxy's class definitions without performing a request.
	 *
	 * @beforeClass
	 *
	 * @return void
	 */
	public static function load_proxy(): void {

		if ( ! defined( 'GTMKIT_GTG_ENDPOINT_HEALTH_CHECK' ) ) {
			define( 'GTMKIT_GTG_ENDPOINT_HEALTH_CHECK', true );
		}

		require_once dirname( __DIR__, 4 ) . '/gtg/measurement.php';
	}

	/**
	 * Build a request context around a set of query parameters.
	 *
	 * @param array<string, string> $query The query parameters.
	 * @param array<string, string> $server Extra server parameters, such as request headers.
	 *
	 * @return ServerRequestContext
	 */
	private function context( array $query, array $server = [] ): ServerRequestContext {
		return new ServerRequestContext(
			array_merge(
				[
					'SCRIPT_NAME'    => '/wp-content/plugins/gtm-kit/gtg/measurement.php',
					'REQUEST_METHOD' => 'GET',
				],
				$server
			),
			$query,
			''
		);
	}

	/**
	 * Run the proxy in a process of its own and report what it left behind.
	 *
	 * The file can only be loaded once per process, so the two load paths
	 * cannot both be exercised in the PHPUnit process. Each runs under
	 * `display_errors` explicitly on, which is the host configuration the
	 * suppression exists for, and a shutdown function reports the setting back
	 * so the reading survives the `exit` on the health-check path.
	 *
	 * @param string $prelude PHP statements to run before the file is loaded.
	 *
	 * @return array{output: string, display_errors: string}
	 */
	private function run_proxy_in_isolation( string $prelude ): array {

		$code = 'register_shutdown_function(static function () { echo "|" . ini_get("display_errors"); });'
			. $prelude
			. 'require $argv[1];';

		$command = escapeshellarg( PHP_BINARY )
			. ' -d display_errors=1 -d error_reporting=-1 -r '
			. escapeshellarg( $code )
			. ' -- '
			. escapeshellarg( dirname( __DIR__, 4 ) . '/gtg/measurement.php' )
			. ' 2>&1';

		$lines = [];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- A second process is the only way to load a file that can only be loaded once per process, and this runs under the test runner rather than on a site.
		exec( $command, $lines );

		$output = implode( "\n", $lines );

		$parts = explode( '|', $output );

		return [
			'output'         => $parts[0],
			'display_errors' => $parts[1] ?? '',
		];
	}

	/**
	 * A local port nothing is listening on.
	 *
	 * @return int
	 */
	private function free_port(): int {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		$this->assertIsResource( $socket );
		$name = (string) stream_socket_get_name( $socket, false );
		fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A socket, not a file; WP_Filesystem does not apply.

		return (int) substr( (string) strrchr( $name, ':' ), 1 );
	}

	/**
	 * A request helper that records what it would have sent instead of sending it.
	 *
	 * @return RequestHelper
	 */
	private function recording_helper(): RequestHelper {
		return new class() extends RequestHelper {

			/**
			 * The headers of the last request.
			 *
			 * @var string[]
			 */
			public array $sent = [];

			/**
			 * Record the request and answer with an empty success.
			 *
			 * @param string      $method The request method.
			 * @param string      $url The request URL.
			 * @param string[]    $headers The request headers.
			 * @param string|null $body The request body.
			 *
			 * @return array{body: string, headers: string[], statusCode: int}
			 */
			public function sendRequest( string $method, string $url, array $headers = [], ?string $body = null ): array {
				$this->sent = $headers;

				return [
					'body'       => '',
					'headers'    => [],
					'statusCode' => 200,
				];
			}
		};
	}

	/**
	 * A request served by the proxy never displays PHP errors.
	 *
	 * Everything this file writes is read as code: the container body is
	 * JavaScript, and the health check accepts a body of exactly `ok`. A notice
	 * printed ahead of either one is swallowed by the response rather than
	 * reported, so the container dies on a syntax error, or a healthy site
	 * fails its own check. Neither shows up as a bad status code, which is all
	 * the checks around this file can see, so the response has to be kept clean
	 * whatever the notice turns out to be about.
	 *
	 * @return void
	 */
	public function test_a_request_does_not_display_php_errors(): void {

		$result = $this->run_proxy_in_isolation( '$_GET["healthCheck"] = "1";' );

		$this->assertSame( 'ok', $result['output'], 'The health check body must be exactly ok.' );
		$this->assertSame( '0', $result['display_errors'], 'A served request must suppress error display.' );
	}

	/**
	 * Loading the file for its class definitions changes nothing in the caller.
	 *
	 * The health check loads this file inside WordPress to borrow its HTTP
	 * client. That include has to be inert: suppressing error display there
	 * would silence diagnostics for the rest of the request, and for the rest
	 * of a test run. The early return therefore stays ahead of everything else
	 * in the modified block, which is the part of the arrangement a re-sync
	 * from upstream could quietly undo.
	 *
	 * @return void
	 */
	public function test_loading_for_class_definitions_leaves_error_display_alone(): void {

		$result = $this->run_proxy_in_isolation( 'define("GTMKIT_GTG_ENDPOINT_HEALTH_CHECK", true);' );

		$this->assertSame( '', $result['output'], 'Including the file must print nothing.' );
		$this->assertSame( '1', $result['display_errors'], 'Including the file must not change error display.' );
	}

	/**
	 * A container ID is accepted.
	 *
	 * @return void
	 */
	public function test_accepts_a_container_id(): void {
		$this->assertSame( 'GTM-ABC123', $this->context( [ 'id' => 'GTM-ABC123' ] )->getTagId() );
	}

	/**
	 * An id that could name another host is refused.
	 *
	 * Each of these is a way of writing a host or a path into the id. The
	 * empty string the proxy returns for them stops the request before any
	 * connection is attempted, because an empty tag id is treated as an
	 * invalid request.
	 *
	 * @dataProvider hostile_id_provider
	 *
	 * @param string $id The id to attempt.
	 * @param string $why What the id is trying to do.
	 *
	 * @return void
	 */
	public function test_refuses_an_id_that_names_another_host( string $id, string $why ): void {
		$this->assertSame( '', $this->context( [ 'id' => $id ] )->getTagId(), $why );
	}

	/**
	 * Ids that attempt to reach a host other than the gateway service.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function hostile_id_provider(): array {
		return [
			'a bare host'            => [ 'evil.example.com', 'A dotted host would end the subdomain and name another domain.' ],
			'a trailing dot'         => [ 'gtm-abc.evil.com', 'A dotted id would resolve somewhere other than the service.' ],
			'a path escape'          => [ 'gtm-abc/../..', 'A path segment would climb out of the service host.' ],
			'a scheme'               => [ 'https://evil.example.com', 'A scheme would replace the whole URL.' ],
			'an authority separator' => [ 'evil.example.com#', 'A fragment would truncate the service suffix.' ],
			'a credential separator' => [ 'user@evil.example.com', 'An userinfo separator would move the host.' ],
			'a port'                 => [ 'gtm-abc:8080', 'A port would change where the connection goes.' ],
			'a query start'          => [ 'gtm-abc?x=1', 'A query would truncate the service suffix.' ],
			'a newline'              => [ "gtm-abc\nHost: evil.example.com", 'A newline would allow header injection.' ],
			'an encoded slash'       => [ 'gtm-abc%2Fevil', 'A percent sequence is not part of the allowed set.' ],
			'an underscore wildcard' => [ 'gtm_abc', 'An underscore is outside the allowed character set.' ],
			'empty'                  => [ '', 'An absent id is not a request the proxy can serve.' ],
		];
	}

	/**
	 * The destination keeps the parameters the container needs and drops the proxy's own.
	 *
	 * The environment parameters travel to the container this way, which is
	 * what lets a container bound to an environment resolve through the
	 * gateway. The proxy's own parameters must not be passed on, or they
	 * would arrive at the service as container parameters.
	 *
	 * @return void
	 */
	public function test_destination_forwards_container_parameters_and_drops_its_own(): void {

		$destination = $this->context(
			[
				'id'          => 'GTM-ABC123',
				'geo'         => 'DK',
				'mpath'       => 'ignored',
				's'           => '',
				'gtm_auth'    => 'aB3',
				'gtm_preview' => 'env-7',
			]
		)->getDestination();

		$this->assertStringContainsString( 'gtm_auth=aB3', $destination );
		$this->assertStringContainsString( 'gtm_preview=env-7', $destination );
		$this->assertStringNotContainsString( 'id=', $destination );
		$this->assertStringNotContainsString( 'geo=', $destination );
		$this->assertStringNotContainsString( 'mpath=', $destination );
	}

	/**
	 * Only Google's measurement cookies reach the gateway service.
	 *
	 * The proxy sits under the plugins directory, the path WordPress scopes
	 * its admin authentication cookie to, so a logged-in administrator's
	 * request carries a working admin session here. Forwarding the cookie
	 * header whole would hand that session, and every shopper's cart session,
	 * to a third party on each tag and measurement request.
	 *
	 * @return void
	 */
	public function test_only_measurement_cookies_reach_the_gateway_service(): void {

		$helper  = $this->recording_helper();
		$context = $this->context(
			[ 'id' => 'GTM-ABC123' ],
			[
				'HTTP_COOKIE'     => 'wordpress_sec_abc=admin%7C1%7Ctoken; _ga=GA1.1.123.456; wordpress_logged_in_abc=admin; wp_woocommerce_session_abc=1; _ga_ABC123=GS1.1; FPID=FPID2.2; _gcl_au=1.1; PHPSESSID=x',
				'HTTP_USER_AGENT' => 'Test agent',
			]
		);

		( new Measurement( $helper, $context ) )->run();

		$this->assertSame(
			[ 'cookie: _ga=GA1.1.123.456; _ga_ABC123=GS1.1; FPID=FPID2.2; _gcl_au=1.1' ],
			array_values( preg_grep( '/^cookie:/i', $helper->sent ) )
		);
		$this->assertContains( 'user-agent: Test agent', $helper->sent );
	}

	/**
	 * A request carrying only site cookies sends no cookie header at all.
	 *
	 * @return void
	 */
	public function test_a_request_with_only_site_cookies_sends_none(): void {

		$helper  = $this->recording_helper();
		$context = $this->context(
			[ 'id' => 'GTM-ABC123' ],
			[ 'HTTP_COOKIE' => 'wordpress_sec_abc=admin; wordpress_logged_in_abc=admin' ]
		);

		( new Measurement( $helper, $context ) )->run();

		$this->assertSame( [], preg_grep( '/^cookie:/i', $helper->sent ) );
	}

	/**
	 * Headers that describe the upstream exchange are not re-sent to the visitor.
	 *
	 * The HTTP client has already removed the chunking from the body, and the
	 * body may have been rewritten, so a forwarded `Transfer-Encoding` or
	 * `Content-Length` describes a body that is not the one being sent. The
	 * web server then serves a corrupt tag or fails the request.
	 *
	 * @dataProvider response_header_provider
	 *
	 * @param string $header The upstream response header.
	 * @param bool   $forwarded Whether it is re-sent to the visitor.
	 *
	 * @return void
	 */
	public function test_only_content_headers_are_re_sent( string $header, bool $forwarded ): void {
		$this->assertSame( $forwarded, RequestHelper::isForwardableResponseHeader( $header ) );
	}

	/**
	 * Upstream response headers and whether each is re-sent.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function response_header_provider(): array {
		return [
			'transfer encoding' => [ 'Transfer-Encoding: chunked', false ],
			'content length'    => [ 'Content-Length: 1234', false ],
			'connection'        => [ 'Connection: keep-alive', false ],
			'keep alive'        => [ 'Keep-Alive: timeout=5', false ],
			'alternate service' => [ 'Alt-Svc: h3=":443"; ma=2592000', false ],
			'a status line'     => [ 'HTTP/1.1 200 OK', false ],
			'content type'      => [ 'Content-Type: application/javascript; charset=UTF-8', true ],
			'cache control'     => [ 'Cache-Control: private, max-age=900', true ],
			'a cookie it sets'  => [ 'Set-Cookie: FPID=FPID2.2; Path=/; Secure', true ],
		];
	}

	/**
	 * A gateway service that never answers is given up on, with a 502.
	 *
	 * Without a limit, curl waits up to five minutes on a host that silently
	 * drops outbound traffic, with a PHP worker held for every visitor. The
	 * helper's limits are lowered here so the test takes a second, not ten.
	 *
	 * @return void
	 */
	public function test_a_service_that_never_answers_is_given_up_on(): void {

		$port   = $this->free_port();
		$router = sys_get_temp_dir() . '/gtmkit-gtg-slow-' . uniqid() . '.php';
		file_put_contents( $router, '<?php sleep( 5 );' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A throwaway router script for the test server; WP_Filesystem is not loaded in unit tests.

		// Pipes rather than files for the server's output: the test suite's
		// stream wrapper cannot hand a file descriptor to a child process.
		$pipes = [];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- A local server that never answers is the only faithful stand-in for a host dropping traffic, and this runs under the test runner rather than on a site.
		$server = proc_open(
			[ PHP_BINARY, '-S', '127.0.0.1:' . $port, $router ],
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes
		);
		$this->assertIsResource( $server );

		for ( $attempt = 0; $attempt < 50; $attempt++ ) {
			$probe = @fsockopen( '127.0.0.1', $port ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen -- A refused connection is the expected answer until the local test server is up; this is a socket probe, not file access.
			if ( $probe ) {
				fclose( $probe ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A socket, not a file.
				break;
			}
			usleep( 100000 );
		}

		$helper = new class() extends RequestHelper {

			/**
			 * Lowered for the test.
			 *
			 * @var int
			 */
			protected int $connectTimeout = 1; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- Overrides the vendored proxy's own property, whose name is set upstream.

			/**
			 * Lowered for the test.
			 *
			 * @var int
			 */
			protected int $timeout = 1;
		};

		$started  = microtime( true );
		$response = $helper->sendRequest( 'GET', 'http://127.0.0.1:' . $port . '/' );
		$elapsed  = microtime( true ) - $started;

		foreach ( $pipes as $pipe ) {
			fclose( $pipe ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- A process pipe, not a file.
		}
		proc_terminate( $server );
		proc_close( $server );
		unlink( $router ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removing the throwaway router script.

		$this->assertLessThan( 4, $elapsed, 'The request must stop at its time limit.' );
		$this->assertSame( 502, $response['statusCode'] );
		$this->assertSame( '', $response['body'] );
	}

	/**
	 * A refused connection answers 502, not a status of 0.
	 *
	 * @return void
	 */
	public function test_a_refused_connection_answers_502(): void {

		$response = ( new RequestHelper() )->sendRequest( 'GET', 'http://127.0.0.1:' . $this->free_port() . '/' );

		$this->assertSame( 502, $response['statusCode'] );
		$this->assertSame( [], $response['headers'] );
	}
}
