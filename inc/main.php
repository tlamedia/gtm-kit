<?php

namespace TLA_Media\GTM_Kit;

use TLA_Media\GTM_Kit\Admin\AdminNotice;
use TLA_Media\GTM_Kit\Admin\Analytics;
use TLA_Media\GTM_Kit\Admin\IntegrationsOptionsPage;
use TLA_Media\GTM_Kit\Admin\MetaBox;
use TLA_Media\GTM_Kit\Frontend\BasicDatalayerData;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Frontend\UserData;
use TLA_Media\GTM_Kit\Admin\GeneralOptionsPage;
use TLA_Media\GTM_Kit\Installation\Installation;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Integration\ContactForm7;
use TLA_Media\GTM_Kit\Integration\EasyDigitalDownloads;
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
function gtmkit_plugin_activation(): void {
	new Installation();
	do_action( 'gtmkit_activate' );
}

register_activation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\gtmkit_plugin_activation' );

/**
 * Add plugin action links on Plugins page.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array
 */
function gtmkit_add_plugin_action_link( array $links ): array {

	$custom['settings'] = sprintf(
		'<a href="%s" aria-label="%s">%s</a>',
		esc_url( menu_page_url( 'gtmkit_general', false ) ),
		esc_attr__( 'Go to GTM Kit Settings page', 'gtm-kit' ),
		esc_html__( 'Settings', 'gtm-kit' )
	);

	return array_merge( $custom, (array) $links );
}

/**
 * Load text domain for translation.
 */
function gtmkit_load_text_domain(): void {
	load_plugin_textdomain( 'gtm-kit', false, dirname( GTMKIT_BASENAME ) . '/languages/' );
}

/**
 * Load frontend.
 */
function gtmkit_frontend_init(): void {
	$options = new Options();
	BasicDatalayerData::register( $options );
	UserData::register( $options );
	Frontend::register( $options );
	require GTMKIT_PATH . 'inc/frontend-functions.php';
	if ( Options::init()->get( 'integrations', 'woocommerce_integration' ) && function_exists( 'WC' ) ) {
		WooCommerce::register( $options );
	}
	if ( Options::init()->get( 'integrations', 'cf7_integration' ) && class_exists('WPCF7') ) {
		ContactForm7::register( $options );
	}
	if ( Options::init()->get( 'integrations', 'edd_integration' ) && class_exists('EDD_Requirements_Check') ) {
		EasyDigitalDownloads::register( $options );
	}

}

/**
 * Load backend.
 */
function gtmkit_admin_init(): void {

	if ( version_compare( get_option( 'gtmkit_version' ), GTMKIT_VERSION, '<' ) ) {
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}

		new Upgrade();
	}

	add_action( 'before_woocommerce_init', function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GTMKIT_FILE, true );
		}
	} );

	$options = new Options();
	MetaBox::register( $options );
	AdminNotice::register( $options );
	Analytics::register();
	GeneralOptionsPage::register( $options );
	IntegrationsOptionsPage::register( $options );
}

/**
 * Load the plugin.
 */
if ( ! wp_installing() ) {

	if ( is_admin() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_load_text_domain' );
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_admin_init' );
		add_filter( 'plugin_action_links_' . plugin_basename( GTMKIT_FILE ), 'TLA_Media\GTM_Kit\gtmkit_add_plugin_action_link', 10, 1 );
	} elseif ( ! wp_doing_ajax() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_frontend_init' );

	}
}
