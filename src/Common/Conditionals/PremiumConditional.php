<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when GTM Kit Premium is active.
 */
class PremiumConditional implements Conditional {

	/**
	 * Returns `true` when the GTM Kit Premium plugin is installed and activated.
	 *
	 * @return bool `true` when the Premium plugin is installed and activated.
	 */
	public function is_met(): bool {
		return \defined( 'GTMKIT_WOO_FILE' ) || \defined( 'GTMKIT_PREMIUM_FILE' );
	}
}
