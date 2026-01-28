<?php
/**
 * Options Backward Compatibility
 *
 * Provides backward compatibility for gtm-kit-woo and other add-ons
 * that use the old Options namespace.
 *
 * @package GTM Kit
 */

// Create class alias for old namespace.
// This allows old code using `TLA_Media\GTM_Kit\Options` to work.
if ( ! class_exists( 'TLA_Media\GTM_Kit\Options' ) ) {
	class_alias( 'TLA_Media\GTM_Kit\Options\Options', 'TLA_Media\GTM_Kit\Options' );
}
