<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when the Bricks theme is active.
 */
class BricksConditional implements Conditional {

	/**
	 * Returns `true` when the Bricks theme is installed and activated.
	 *
	 * @return bool `true` when the Bricks theme is installed and activated.
	 */
	public function is_met(): bool {
		return ( \wp_get_theme()->get( 'Name' ) === 'Bricks' || wp_get_theme()->get( 'Template' ) === 'bricks' );
	}
}
