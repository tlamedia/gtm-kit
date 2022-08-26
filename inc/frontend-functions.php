<?php
/**
 * Frontend template functions
 */

use TLA\GTM_Kit\Options;
use TLA\GTM_Kit\Frontend\Frontend;

function gtmkit_the_noscript_tag(): void {
	$noscript_implementation = (int) Options::init()->get( 'general', 'noscript_implementation' );

	if ($noscript_implementation == 2) Frontend::get_body_script();
}

