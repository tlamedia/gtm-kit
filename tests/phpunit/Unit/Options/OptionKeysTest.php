<?php
/**
 * Per-module geography test for src/Options/.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Options\OptionKeys} — pure static
 * helpers over the constant table. Cheapest possible coverage for the
 * Options namespace; serves as the template for richer Options tests.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Options;

use TLA_Media\GTM_Kit\Options\OptionKeys;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Geography unit test for src/Options/ via OptionKeys::parse() and ::exists().
 */
final class OptionKeysTest extends TestCase {

	/**
	 * Splits a dot-separated key into group and key parts.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::parse
	 */
	public function test_parse_splits_group_and_key(): void {
		$this->assertSame(
			[
				'group' => 'general',
				'key'   => 'gtm_id',
			],
			OptionKeys::parse( OptionKeys::GENERAL_GTM_ID )
		);
	}

	/**
	 * Distinguishes known constants from arbitrary strings.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::exists
	 */
	public function test_exists_returns_true_for_known_key_and_false_for_unknown(): void {
		$this->assertTrue( OptionKeys::exists( OptionKeys::GENERAL_GTM_ID ) );
		$this->assertFalse( OptionKeys::exists( 'nonexistent.key' ) );
	}
}
