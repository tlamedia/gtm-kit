<?php
/**
 * Unit tests for the Frontend output-gate decision.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed()},
 * the pure predicate that gtmkit_frontend_init() and Frontend::register() share
 * to decide whether every GTM Kit enqueue (core runtime and integrations) is
 * withheld for the current request.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see Frontend::is_output_suppressed()}.
 */
final class FrontendOutputGateTest extends TestCase {

	/**
	 * Output is suppressed only when the URL is excluded and the container is
	 * not forced back on.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_suppressed_only_when_excluded_and_container_inactive(): void {
		$this->assertTrue(
			Frontend::is_output_suppressed(
				[
					'url_excluded'     => true,
					'container_active' => false,
				]
			),
			'An excluded URL with no override must suppress all output.'
		);
	}

	/**
	 * A `gtmkit_container_active` override keeps output on even for an excluded
	 * URL.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_not_suppressed_when_override_forces_container_on(): void {
		$this->assertFalse(
			Frontend::is_output_suppressed(
				[
					'url_excluded'     => true,
					'container_active' => true,
				]
			),
			'A filter that forces the container on must keep output enabled.'
		);
	}

	/**
	 * A non-excluded URL is never suppressed by this gate, even when the
	 * container is inactive for other reasons.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_not_suppressed_when_url_not_excluded(): void {
		$this->assertFalse(
			Frontend::is_output_suppressed(
				[
					'url_excluded'     => false,
					'container_active' => true,
				]
			),
			'A non-excluded URL with an active container is not suppressed.'
		);

		$this->assertFalse(
			Frontend::is_output_suppressed(
				[
					'url_excluded'     => false,
					'container_active' => false,
				]
			),
			'A globally inactive container on a non-excluded URL is a separate path, not exclusion suppression.'
		);
	}
}
