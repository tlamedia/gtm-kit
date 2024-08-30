<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when Easy Digital Downloads is active.
 */
class EasyDigitalDownloadsConditional implements Conditional {

	/**
	 * Returns `true` when the Easy Digital Downloads plugin is installed and activated.
	 *
	 * @return bool `true` when the Easy Digital Downloads plugin is installed and activated.
	 */
	public function is_met(): bool {
		return \class_exists( 'Easy_Digital_Downloads' );
	}
}
