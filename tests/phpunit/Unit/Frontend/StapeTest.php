<?php
/**
 * Unit tests for the Cookie Keeper loader file name.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Stape::cookie_keeper_loader()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use TLA_Media\GTM_Kit\Frontend\Stape;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Cookie Keeper loader name tests.
 */
final class StapeTest extends TestCase {

	/**
	 * Identifiers on each side of the eight-character boundary.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_cookie_keeper_loader(): array {
		return [
			'exactly eight characters: prefix the whole identifier' => [ 'hixjpkyq', 'kphixjpkyq' ],
			'longer than eight: prefix the last eight characters'   => [ '38i0hixjpkyq', '38i0kphixjpkyq' ],
		];
	}

	/**
	 * The `kp` prefix lands where Stape's loader server expects it.
	 *
	 * @dataProvider data_cookie_keeper_loader
	 *
	 * @param string $loader   The custom loader identifier.
	 * @param string $expected The Safari loader file name.
	 */
	public function test_cookie_keeper_loader( string $loader, string $expected ): void {
		$this->assertSame( $expected, Stape::cookie_keeper_loader( $loader ) );
	}
}
