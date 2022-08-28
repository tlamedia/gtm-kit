<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\AdminNotice;
use TLA_Media\GTM_Kit\Admin\IntegrationsOptionsPage;
use TLA_Media\GTM_Kit\Frontend\BasicDatalayerData;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Admin\GeneralOptionsPage;
use TLA_Media\GTM_Kit\Installation\Installation;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Integration\WooCommerce;


if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

require GTMKIT_PATH . 'vendor/autoload.php';

/**
 * Plugin activation hook.
 */
function plugin_activation(): void {
	new Installation();
	do_action( 'gtmkit_activate' );
}
register_activation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\plugin_activation' );

/**
 * Add plugin action links on Plugins page.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array
 */
function add_plugin_action_link( array $links ): array {

	$custom['settings'] = sprintf(
		'<a href="%s" aria-label="%s">%s</a>',
		esc_url(  menu_page_url( 'gtmkit_settings', false ) ),
		esc_attr__( 'Go to GTK Kit Settings page', 'gtmkit' ),
		esc_html__( 'Settings', 'gtmkit' )
	);

	return array_merge( $custom, (array) $links );
}

/**
 * Load text domain for translation.
 */
function load_text_domain(): void {
	load_plugin_textdomain( 'gtmkit', false, dirname( GTMKIT_BASENAME ) . '/languages/' );
}

/**
 * Load frontend.
 */
function frontend_init(): void {
	$options = new Options();
	BasicDatalayerData::register( $options );
	Frontend::register( $options );
	require GTMKIT_PATH . 'inc/frontend-functions.php';
	if ( Options::init()->get( 'integrations', 'woocommerce_integration' ) && function_exists( 'WC' ) ) {
		WooCommerce::register( $options );
	};
}

/**
 * Load backend.
 */
function admin_init(): void {

	if ( version_compare( get_option( 'gtmkit_version' ), GTMKIT_VERSION, '<' ) ) {
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		new Upgrade();
	}

	$options = new Options();
	AdminNotice::register( $options );
	GeneralOptionsPage::register( $options );
	IntegrationsOptionsPage::register( $options );
}

/**
 * Load the plugin.
 */
if ( ! wp_installing() ) {

	if ( is_admin() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\load_text_domain' );
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\admin_init' );
		add_filter( 'plugin_action_links_' . plugin_basename( GTMKIT_FILE ), 'TLA_Media\GTM_Kit\add_plugin_action_link', 10, 1 );
	} elseif ( ! wp_doing_ajax() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\frontend_init' );

	}
}
