<?php
/**
 * Unit tests for reading a loader address out of a snippet issued by Stape.
 *
 * The fixtures are responses Stape's API returned for a live container, with
 * Cookie Keeper off and on, and with the default and a custom data layer name.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\StapeLoader::parse()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Parser tests over captured responses and hostile variants of them.
 */
final class StapeLoaderParseTest extends TestCase {

	/**
	 * The sGTM domain the fixtures were issued for.
	 *
	 * @var string
	 */
	private const DOMAIN = 'collect.gtmkit.com';

	/**
	 * The query value issued for the default data layer.
	 *
	 * @var string
	 */
	private const VALUE = 'GB1WNz41RDwmTC00Xj9eTgdEWV5bXg0GTB4fHQERHUYSFgY%3D';

	/**
	 * The query value issued for the `gtmkitLayer` data layer.
	 *
	 * @var string
	 */
	private const CUSTOM_VALUE = 'GB1WNz41RDwmTC00Xj9eTgdEWV5bXgVVFg0GGwMMJQkIHBlWDhZUDwUUABkeVgoHHA%3D%3D';

	/**
	 * Read the snippet from a captured response.
	 *
	 * @param string $name The fixture name.
	 *
	 * @return string
	 */
	private static function js_code( string $name ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture from disk.
		$decoded = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/stape-loader/' . $name . '.json' ), true );

		return (string) $decoded['body']['jsCode'];
	}

	/**
	 * Captured responses and the query value each carries.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_captured_responses(): array {
		return [
			'Cookie Keeper off'                   => [ 'cookie-keeper-off', self::VALUE ],
			'Cookie Keeper on'                    => [ 'cookie-keeper-on', self::VALUE ],
			'custom data layer'                   => [ 'custom-datalayer', self::CUSTOM_VALUE ],
			'custom data layer, Cookie Keeper on' => [ 'custom-datalayer-cookie-keeper', self::CUSTOM_VALUE ],
		];
	}

	/**
	 * Both shapes Stape writes yield the same three values.
	 *
	 * @dataProvider data_captured_responses
	 *
	 * @param string $fixture The fixture name.
	 * @param string $value   The query value it carries.
	 *
	 * @return void
	 */
	public function test_captured_responses_are_read( string $fixture, string $value ): void {
		$this->assertSame(
			[
				'path'  => '38i0hixjpkyq',
				'param' => '3bsw',
				'value' => $value,
			],
			StapeLoader::parse( self::js_code( $fixture ), self::DOMAIN )
		);
	}

	/**
	 * The domain comparison ignores case, as hosts do.
	 *
	 * @return void
	 */
	public function test_domain_comparison_ignores_case(): void {
		$this->assertNotNull( StapeLoader::parse( self::js_code( 'cookie-keeper-off' ), 'Collect.GTMKit.com' ) );
	}

	/**
	 * A pasted snippet may carry the whole address in one attribute, beside a noscript iframe.
	 *
	 * @return void
	 */
	public function test_a_pasted_address_with_a_noscript_iframe_is_read(): void {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- A pasted snippet under test, not a script this plugin outputs.
		$code = '<script async src="https://collect.gtmkit.com/38i0hixjpkyq.js?3bsw=' . self::VALUE . '"></script>'
			. '<noscript><iframe src="https://collect.gtmkit.com/ns.html?id=GTM-TW5FD4G7" height="0" width="0"></iframe></noscript>';

		$this->assertSame( '38i0hixjpkyq', StapeLoader::parse( $code, self::DOMAIN )['path'] ?? null );
	}

	/**
	 * Snippets that must not be trusted.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_rejected(): array {
		$off = self::js_code( 'cookie-keeper-off' );
		$on  = self::js_code( 'cookie-keeper-on' );

		return [
			'foreign domain'                => [ str_replace( 'collect.gtmkit.com', 'evil.example.com', $off ) ],
			'foreign Cookie Keeper origin'  => [ str_replace( 'collect.gtmkit.com', 'evil.example.com', $on ) ],
			'plain http'                    => [ str_replace( 'https://', 'http://', $off ) ],
			'two loader addresses'          => [ $off . '<script>var x="https://collect.gtmkit.com/other1.js?";</script>' ],
			'extra query pair in the value' => [ str_replace( "'3bsw=" . self::VALUE . "'", "'3bsw=" . self::VALUE . "&x=1'", $off ) ],
			'a second query pair'           => [ $off . "<script>var z='abcd=1234';</script>" ],
			'script break in the value'     => [ str_replace( self::VALUE, 'abc"</script><script>alert(1)</script>', $off ) ],
			'script break in the path'      => [ str_replace( '38i0hixjpkyq.js', '38i0"</script>.js', $off ) ],
			'script break in the parameter' => [ str_replace( "'3bsw=", "'3b</script>sw=", $off ) ],
			'oversized value'               => [ str_replace( self::VALUE, str_repeat( 'A', 513 ), $off ) ],
			'oversized snippet'             => [ $off . str_repeat( ' ', StapeLoader::MAX_CODE_LENGTH ) ],
			'query pair with no address'    => [ "'3bsw=" . self::VALUE . "'" ],
			'empty'                         => [ '' ],
		];
	}

	/**
	 * Anything that is not exactly one address on the configured domain is refused.
	 *
	 * @dataProvider data_rejected
	 *
	 * @param string $code The snippet.
	 *
	 * @return void
	 */
	public function test_untrustworthy_snippets_are_refused( string $code ): void {
		$this->assertNull( StapeLoader::parse( $code, self::DOMAIN ) );
	}

	/**
	 * Without a configured domain nothing can be checked, so nothing is read.
	 *
	 * @return void
	 */
	public function test_no_domain_reads_nothing(): void {
		$this->assertNull( StapeLoader::parse( self::js_code( 'cookie-keeper-off' ), '' ) );
	}
}
