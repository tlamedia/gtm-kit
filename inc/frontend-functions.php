<?php
/**
 * Frontend template functions
 *
 * @package GTM Kit
 */

use TLA_Media\GTM_Kit\Options\OptionsFactory;
use TLA_Media\GTM_Kit\Frontend\Frontend;

/**
 * The noscript tag
 *
 * @return void
 */
function gtmkit_the_noscript_tag(): void {
	$options                 = OptionsFactory::get_instance();
	$noscript_implementation = (int) $options->get( 'general', 'noscript_implementation' );

	if ( $noscript_implementation === 2 ) {
		$frontend = new Frontend( $options );
		$frontend->get_body_script();
	}
}
