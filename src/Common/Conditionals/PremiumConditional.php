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
	 * This checks if the plugin's inc/main.php file has actually loaded, not just
	 * if the main plugin file is present. This prevents false positives when a
	 * plugin is "active" but its functionality hasn't loaded due to version checks.
	 *
	 * @return bool `true` when the Premium plugin is installed and activated.
	 */
	public function is_met(): bool {
		return \function_exists( 'TLA_Media\GTM_Kit\Woo\load_text_domain' )
			|| \function_exists( 'TLA_Media\GTM_Kit\Premium\load_text_domain' );
	}
}
