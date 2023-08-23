<?php
/**
 * Uninstall all GTM Kit data.
 *
 * @package GTM Kit
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gtmkit' );
delete_option( 'gtmkit_version' );
delete_option( 'gtmkit_initial_version' );
delete_option( 'gtmkit_activation_prevent_redirect' );
