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

	if ( $noscript_implementation !== 2 ) {
		return;
	}

	// Placed by the theme rather than by a hook, so the gates the other
	// placements get from Frontend::register() have to be asked for here.
	// Without them a template placement would keep pointing at the live
	// container on a site the rest of GTM Kit has already decided to leave
	// alone: a staging or development copy, an excluded URL, an excluded user
	// role, or a container switched off outright.
	if ( ! Frontend::will_register_container( $options ) ) {
		return;
	}

	$frontend = new Frontend( $options );
	$frontend->get_body_script();
}
