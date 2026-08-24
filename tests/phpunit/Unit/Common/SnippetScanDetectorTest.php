<?php
/**
 * Unit tests for the tracking-implementation parser.
 *
 * Every fixture is a page shape support has had to reason about by hand:
 * GTM Kit's own three output variants, the container-ID-less server-side
 * loader, a second GTM plugin, a Google tag added by another tool, and the
 * three ways a page ends up loading tracking twice.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\SnippetScanDetector}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\SnippetScanDetector;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Parser tests over fixture HTML.
 */
final class SnippetScanDetectorTest extends TestCase {

	/**
	 * GTM Kit configured with the standard Google-hosted loader.
	 *
	 * @var array<string, string>
	 */
	private const OWN_STANDARD = [
		'container' => 'GTM-ABCD123',
		'domain'    => 'www.googletagmanager.com',
		'loader'    => 'gtm',
	];

	/**
	 * GTM Kit configured with a server-side container.
	 *
	 * @var array<string, string>
	 */
	private const OWN_SGTM = [
		'container' => 'GTM-ABCD123',
		'domain'    => 'sgtm.example.com',
		'loader'    => 'loader',
	];

	/**
	 * Stub the one WordPress function the parser uses.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- BrainMonkey stub stands in for wp_parse_url() with no WP available; PHP's native parse_url() is the only option here.
				return \parse_url( $url, $component );
			}
		);
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
	 * Reduce a result set to the entries that count as a load of tracking.
	 *
	 * @param array<int, array<string, mixed>> $implementations The parser output.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function loads( array $implementations ): array {
		return array_values( array_filter( $implementations, [ SnippetScanDetector::class, 'counts_as_load' ] ) );
	}

	/**
	 * GTM Kit's standard output is one load plus its noscript fallback.
	 */
	public function test_standard_loader_is_identified_as_our_own(): void {
		$found = SnippetScanDetector::detect( $this->fixture( 'gtmkit-standard' ), self::OWN_STANDARD );

		$this->assertCount( 1, $this->loads( $found ) );
		$this->assertSame( SnippetScanDetector::SOURCE_INLINE, $this->loads( $found )[0]['source'] );
		$this->assertSame( 'GTM-ABCD123', $this->loads( $found )[0]['container'] );
		$this->assertSame( SnippetScanDetector::OWNER_GTMKIT, $this->loads( $found )[0]['owner'] );

		$noscript = array_values(
			array_filter( $found, static fn( $i ) => $i['source'] === SnippetScanDetector::SOURCE_NOSCRIPT )
		);
		$this->assertCount( 1, $noscript );
	}

	/**
	 * A server-side loader carrying the container in `st` is still ours.
	 */
	public function test_server_side_loader_is_identified_as_our_own(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'gtmkit-sgtm-st' ), self::OWN_SGTM ) );

		$this->assertCount( 1, $loads );
		$this->assertSame( 'GTM-ABCD123', $loads[0]['container'] );
		$this->assertSame( 'sgtm.example.com', $loads[0]['host'] );
		$this->assertSame( SnippetScanDetector::OWNER_GTMKIT, $loads[0]['owner'] );
	}

	/**
	 * The Cookie Keeper variant strips the container prefix from the loader,
	 * so the bootstrap is recognised by its host rather than by an ID.
	 */
	public function test_cookie_keeper_loader_is_identified_by_its_host(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'gtmkit-cookie-keeper' ), self::OWN_SGTM ) );

		$this->assertCount( 1, $loads );
		$this->assertSame( 'sgtm.example.com', $loads[0]['host'] );
		$this->assertSame( SnippetScanDetector::OWNER_GTMKIT, $loads[0]['owner'] );
	}

	/**
	 * A loader with no container ID anywhere is found through its bootstrap.
	 */
	public function test_loader_without_a_container_id_is_still_found(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'sgtm-no-container-id' ), self::OWN_SGTM ) );

		$this->assertCount( 1, $loads );
		$this->assertSame( '', $loads[0]['container'] );
		$this->assertSame( SnippetScanDetector::SOURCE_INLINE, $loads[0]['source'] );
		$this->assertSame( SnippetScanDetector::OWNER_GTMKIT, $loads[0]['owner'] );
	}

	/**
	 * Another plugin's container carries a container ID that is not ours.
	 */
	public function test_another_plugins_container_is_foreign(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'foreign-gtm-plugin' ), self::OWN_STANDARD ) );

		$this->assertCount( 1, $loads );
		$this->assertSame( 'GTM-ZZZZ999', $loads[0]['container'] );
		$this->assertSame( SnippetScanDetector::OWNER_FOREIGN, $loads[0]['owner'] );
	}

	/**
	 * A Google tag added by another tool is identified as a gtag implementation.
	 */
	public function test_google_tag_from_another_tool_is_identified(): void {
		$loads = $this->loads(
			SnippetScanDetector::detect(
				$this->fixture( 'site-kit-gtag' ),
				[
					'container' => '',
					'domain'    => 'www.googletagmanager.com',
					'loader'    => 'gtm',
				]
			)
		);

		$this->assertCount( 1, $loads );
		$this->assertSame( SnippetScanDetector::TYPE_GTAG, $loads[0]['type'] );
		$this->assertSame( 'G-1A2B3C4D5E', $loads[0]['container'] );
		$this->assertSame( SnippetScanDetector::SOURCE_SCRIPT, $loads[0]['source'] );
	}

	/**
	 * A Google tag beside a container is two implementations, not one.
	 */
	public function test_google_tag_beside_a_container_is_two_implementations(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'gtmkit-plus-stray-gtag' ), self::OWN_STANDARD ) );

		$this->assertCount( 2, $loads );

		$types = array_column( $loads, 'type' );
		$this->assertContains( SnippetScanDetector::TYPE_GTM, $types );
		$this->assertContains( SnippetScanDetector::TYPE_GTAG, $types );
	}

	/**
	 * Two containers are two loads carrying two different IDs.
	 */
	public function test_two_containers_are_two_loads(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'two-containers' ), self::OWN_STANDARD ) );

		$this->assertCount( 2, $loads );
		$this->assertSame( [ 'GTM-ABCD123', 'GTM-ZZZZ999' ], array_column( $loads, 'container' ) );
	}

	/**
	 * The same container loaded twice is two loads carrying one ID.
	 */
	public function test_the_same_container_twice_is_two_loads(): void {
		$loads = $this->loads( SnippetScanDetector::detect( $this->fixture( 'same-container-twice' ), self::OWN_STANDARD ) );

		$this->assertCount( 2, $loads );
		$this->assertSame( [ 'GTM-ABCD123', 'GTM-ABCD123' ], array_column( $loads, 'container' ) );
	}

	/**
	 * The noscript iframe is never a second load of its own container.
	 */
	public function test_the_noscript_iframe_is_not_a_load(): void {
		$found = SnippetScanDetector::detect( $this->fixture( 'gtmkit-standard' ), self::OWN_STANDARD );

		$noscript = array_values(
			array_filter( $found, static fn( $i ) => $i['source'] === SnippetScanDetector::SOURCE_NOSCRIPT )
		);

		$this->assertCount( 1, $noscript );
		$this->assertFalse( SnippetScanDetector::counts_as_load( $noscript[0] ) );
	}

	/**
	 * A page with no tracking yields nothing, jQuery and console calls included.
	 */
	public function test_a_page_without_tracking_yields_nothing(): void {
		$this->assertSame( [], SnippetScanDetector::detect( $this->fixture( 'no-tracking' ), self::OWN_STANDARD ) );
	}

	/**
	 * A truncated page with nothing in it yields nothing. What that absence
	 * means is decided by the engine, not here.
	 */
	public function test_a_truncated_page_yields_nothing(): void {
		$this->assertSame( [], SnippetScanDetector::detect( $this->fixture( 'truncated' ), self::OWN_STANDARD ) );
	}

	/**
	 * A response that is not HTML at all yields nothing.
	 */
	public function test_a_non_html_response_yields_nothing(): void {
		$this->assertSame( [], SnippetScanDetector::detect( '{"gtm":"GTM_ABCD"}', self::OWN_STANDARD ) );
	}

	/**
	 * A container ID present with nothing loading it is a weak signal only.
	 */
	public function test_a_bare_container_id_is_recorded_as_weak(): void {
		$found = SnippetScanDetector::detect(
			'<html><body><p>Our container is GTM-QWER456.</p></body></html>',
			self::OWN_STANDARD
		);

		$this->assertCount( 1, $found );
		$this->assertSame( SnippetScanDetector::SOURCE_LITERAL, $found[0]['source'] );
		$this->assertTrue( $found[0]['weak'] );
		$this->assertFalse( SnippetScanDetector::counts_as_load( $found[0] ) );
	}
}
