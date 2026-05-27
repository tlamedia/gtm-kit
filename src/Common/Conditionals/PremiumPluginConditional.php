<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common\Conditionals;

/**
 * Conditional that is only met when the GTM Kit Premium plugin is active.
 *
 * Unlike PremiumConditional, which is met by any paid add-on (Woo or Premium),
 * this conditional is met exclusively by GTM Kit Premium. The admin app uses it
 * to tell the Premium tier apart from the Woo tier when deciding what to gate.
 */
class PremiumPluginConditional implements Conditional {

	/**
	 * Returns `true` when the GTM Kit Premium plugin is installed and activated.
	 *
	 * Mirrors PremiumConditional by checking that the plugin's functionality has
	 * actually loaded (its text-domain loader is defined), not merely that the
	 * plugin file is present, to avoid false positives when a plugin is "active"
	 * but short-circuited by its own version checks.
	 *
	 * @return bool `true` when the Premium plugin is installed and activated.
	 */
	public function is_met(): bool {
		return \function_exists( 'TLA_Media\GTM_Kit\Premium\load_text_domain' );
	}
}
