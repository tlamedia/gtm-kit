<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional interface.
 */
interface Conditional {

	/**
	 * Returns whether this conditional is met.
	 *
	 * @return bool Whether the conditional is met.
	 */
	public function is_met(): bool;
}
