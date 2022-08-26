<?php
/**
 * Uninstall all GTM Kit data.
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gtmkit' );
delete_option( 'gtmkit_version' );
