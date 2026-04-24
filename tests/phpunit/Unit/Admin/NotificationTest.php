<?php
/**
 * Per-module geography test for src/Admin/.
 *
 * Exercises the value-object shape of {@see \TLA_Media\GTM_Kit\Admin\Notification}
 * by constructing one with explicit options and asserting its rendered
 * array. Stubs `wp_parse_args` with a deterministic merge so the test
 * does not depend on WordPress being loaded.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Admin\Notification;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Geography unit test for src/Admin/ via {@see Notification::render()}.
 */
final class NotificationTest extends TestCase {

	/**
	 * Notification::render() returns the id/header/message triple.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Notification::render
	 */
	public function test_render_exposes_id_header_and_message(): void {
		Functions\stubs(
			[
				'wp_parse_args' => static fn( $args, $defaults ) => array_merge( $defaults, (array) $args ),
			]
		);

		$notification = new Notification(
			'Configure your GTM container',
			'GTM Kit',
			[
				'id'      => 'setup-reminder',
				'user_id' => 1,
			]
		);

		$this->assertSame(
			[
				'id'      => 'setup-reminder',
				'header'  => 'GTM Kit',
				'message' => 'Configure your GTM container',
			],
			$notification->render()
		);
	}
}
