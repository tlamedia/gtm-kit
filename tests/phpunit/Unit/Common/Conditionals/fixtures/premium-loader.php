<?php
/**
 * Test fixture: stubs the GTM Kit Premium text-domain loader so
 * PremiumPluginConditional can observe it via function_exists().
 *
 * Required at runtime from the positive test only, never collected as a test.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Premium;

if ( ! function_exists( 'TLA_Media\GTM_Kit\Premium\load_text_domain' ) ) {
	/**
	 * No-op stand-in for the Premium plugin's loader.
	 *
	 * @return void
	 */
	function load_text_domain(): void {}
}
