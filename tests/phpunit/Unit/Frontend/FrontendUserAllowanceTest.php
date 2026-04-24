<?php
/**
 * Per-module geography test for src/Frontend/ (additional, beyond FrontendTest.php).
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Frontend\Frontend::is_user_allowed_for()} —
 * the static guard that decides whether to emit tracking for the current
 * user. When no excluded roles are configured, the short-circuit returns
 * true before any WP user function is called; that path is what this
 * starter tests.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

final class FrontendUserAllowanceTest extends TestCase {

	/**
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_user_allowed_for
	 */
	public function test_is_user_allowed_for_returns_true_when_no_roles_are_excluded(): void {
		Functions\stubs(
			[
				'get_option'       => [ 'general' => [ 'exclude_user_roles' => [] ] ],
				'add_filter'       => null,
				// Stubbing is_plugin_active satisfies function_exists() inside
				// OptionSchema::get_option_schema() so it does not require_once
				// wp-admin/includes/plugin.php (which does not exist without WP).
				'is_plugin_active' => false,
			]
		);

		$this->assertTrue( Frontend::is_user_allowed_for( Options::create() ) );
	}
}
