<?php
/**
 * Test subclass that drops the `headers_sent()` guard from the SUT.
 *
 * Patchwork (used by the BrainMonkey unit harness) cannot redefine PHP
 * internals without an opt-in `patchwork.json`; subclassing the SUT
 * keeps the unit-test rig free of that extra configuration.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use TLA_Media\GTM_Kit\Frontend\EngagementEvents;

/**
 * Engagement-events SUT with the headers-sent guard permanently open.
 */
final class HeadersOpenEngagementEvents extends EngagementEvents {

	/**
	 * Treat headers as always open so the cookie writer is exercised in
	 * unit tests regardless of the surrounding PHPUnit output state.
	 *
	 * @return bool
	 */
	protected function headers_already_sent(): bool {
		return false;
	}
}
