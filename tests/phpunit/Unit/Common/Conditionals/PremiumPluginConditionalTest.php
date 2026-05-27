<?php
/**
 * Unit test for PremiumPluginConditional.
 *
 * Guards that the Premium-specific entitlement signal is met only when the
 * GTM Kit Premium plugin's loader is defined, so the admin app can tell the
 * Premium tier apart from the Woo tier.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common\Conditionals;

use TLA_Media\GTM_Kit\Common\Conditionals\PremiumPluginConditional;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Detection test for the Premium plugin.
 *
 * The negative case must run before the positive case: a PHP function cannot
 * be undefined once declared, so the absent state is only observable until the
 * positive case loads the loader stub. PHPUnit runs methods in definition
 * order, so the negative assertion is defined first.
 */
final class PremiumPluginConditionalTest extends TestCase {

	/**
	 * Not met while the Premium loader is undefined.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\Conditionals\PremiumPluginConditional::is_met
	 */
	public function test_not_met_when_premium_loader_absent(): void {
		$this->assertFalse(
			( new PremiumPluginConditional() )->is_met(),
			'Should not be met before the Premium loader is defined.'
		);
	}

	/**
	 * Met once the exact Premium loader symbol exists.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\Conditionals\PremiumPluginConditional::is_met
	 */
	public function test_met_when_premium_loader_present(): void {
		// Simulate the Premium plugin defining its loader at runtime. The stub
		// lives in a fixture so it is created only here, never leaking into the
		// absent-state test above.
		require __DIR__ . '/fixtures/premium-loader.php';

		$this->assertTrue(
			( new PremiumPluginConditional() )->is_met(),
			'Should be met once the Premium loader is defined.'
		);
	}
}
