<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when WooCommerce is active.
 */
class PremiumConditional implements Conditional {

	/**
	 * Returns `true` when the WooCommerce plugin is installed and activated.
	 *
	 * @return bool `true` when the WooCommerce plugin is installed and activated.
	 */
	public function is_met(): bool {
		return \defined( 'GTMKIT_WOO_FILE' );
	}
}
