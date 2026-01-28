<?php
/**
 * Frontend template functions
 *
 * @package GTM Kit
 */

use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Frontend\Frontend;

/**
 * The noscript tag
 *
 * @return void
 */
function gtmkit_the_noscript_tag(): void {
	// @phpstan-ignore-next-line staticMethod.deprecated
	$noscript_implementation = (int) Options::init()->get( 'general', 'noscript_implementation' );

	if ( $noscript_implementation === 2 ) {
		Frontend::get_body_script();
	}
}
