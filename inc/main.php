<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use TLA_Media\GTM_Kit\Admin\AdminAPI;
use TLA_Media\GTM_Kit\Admin\Analytics;
use TLA_Media\GTM_Kit\Admin\GeneralOptionsPage;
use TLA_Media\GTM_Kit\Admin\HelpOptionsPage;
use TLA_Media\GTM_Kit\Admin\IntegrationsOptionsPage;
use TLA_Media\GTM_Kit\Admin\MetaBox;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\SetupWizard;
use TLA_Media\GTM_Kit\Admin\TemplatesOptionsPage;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Frontend\BasicDatalayerData;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Frontend\Stape;
use TLA_Media\GTM_Kit\Frontend\UserData;
use TLA_Media\GTM_Kit\Installation\Activation;
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
	new Activation();
	do_action( 'gtmkit_activate' );
}

register_activation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\gtmkit_plugin_activation' );

/**
 * Plugin activation hook.
 */
function gtmkit_plugin_deactivation(): void {

	if ( function_exists( 'wp_set_option_autoload' ) ) {
		wp_set_option_autoload( 'gtmkit', 'no' );
	} else {
		global $wpdb;
		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'gtmkit'" );
	}

	wp_clear_scheduled_hook( 'gtmkit_send_anonymous_data' );

	do_action( 'gtmkit_deactivate' );
}

register_deactivation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\gtmkit_plugin_deactivation' );

/**
 * Add plugin action links on Plugins page.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array
 */
function gtmkit_add_plugin_action_link( array $links ): array {

	$custom['settings'] = sprintf(
		'<!--suppress HtmlUnknownTarget -->
		<a href="%s" aria-label="%s">%s</a>',
		esc_url( menu_page_url( 'gtmkit_general', false ) ),
		esc_attr__( 'Go to GTM Kit Settings page', 'gtm-kit' ),
		esc_html__( 'Settings', 'gtm-kit' )
	);

	return array_merge( $custom, $links );
}

/**
 * Remove deactivation link.
 *
 * @param array $links Existing plugin action links.
 *
 * @return array
 */
function gtmkit_remove_deactivation_link( array $links ): array {

	unset( $links['deactivate'] );
	$no_deactivation_explanation = '<span style="color: #2C3338">' . sprintf(
		/* translators: %s is GTM Kit Add-On. */
		__( 'Required by %s', 'gtm-kit' ),
		'GTM Kit Add-On'
	) . '</span>';

	array_unshift( $links, $no_deactivation_explanation );

	return $links;
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
	$options         = new Options();
	$rest_api_server = new RestAPIServer();
	$util            = new Util( $options, $rest_api_server );

	( new AdminAPI( $options, $util ) )->rest_init();

	if ( ! $options->get( 'general', 'just_the_container' ) ) {
		BasicDatalayerData::register( $options );
		UserData::register( $options );

		if ( $options->get( 'integrations', 'woocommerce_integration' ) && function_exists( 'WC' ) ) {
			WooCommerce::register( $options, $util );
		}
		if ( $options->get( 'integrations', 'cf7_integration' ) && class_exists( 'WPCF7' ) ) {
			ContactForm7::register( $options, $util );
		}
		if ( $options->get( 'integrations', 'edd_integration' ) && class_exists( 'EDD_Requirements_Check' ) ) {
			EasyDigitalDownloads::register( $options, $util );
		}
	}

	Stape::register( $options );

	if ( $options->get( 'general', 'analytics_active' ) ) {
		Analytics::register( $options, $util );
	}

	Frontend::register( $options );
	require GTMKIT_PATH . 'inc/frontend-functions.php';
}

/**
 * Load backend.
 */
function gtmkit_admin_init(): void {

	if ( version_compare( get_option( 'gtmkit_version' ), GTMKIT_VERSION, '<' ) ) {
		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
		}

		new Upgrade();
	}

	$options         = new Options();
	$rest_api_server = new RestAPIServer();
	$util            = new Util( $options, $rest_api_server );

	NotificationsHandler::get();
	Analytics::register( $options, $util );
	MetaBox::register( $options );
	SetupWizard::register( $options, $util );
	GeneralOptionsPage::register( $options, $util );
	IntegrationsOptionsPage::register( $options, $util );
	if ( ! $util->is_premium() ) {
		TemplatesOptionsPage::register( $options, $util );
	} else {
		add_filter( 'plugin_action_links_' . plugin_basename( GTMKIT_FILE ), 'TLA_Media\GTM_Kit\gtmkit_remove_deactivation_link', 11, 1 );
	}
	HelpOptionsPage::register( $options, $util );

	add_filter( 'plugin_action_links_' . plugin_basename( GTMKIT_FILE ), 'TLA_Media\GTM_Kit\gtmkit_add_plugin_action_link', 10, 1 );

	do_action( 'gtmkit_admin_init', $options, $util );
}

/**
 * Load the plugin.
 */
if ( ! wp_installing() ) {

	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', GTMKIT_FILE );
				FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', GTMKIT_FILE );
			}
		}
	);

	if ( is_admin() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_load_text_domain' );
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_admin_init' );
	} elseif ( ! wp_doing_ajax() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_frontend_init' );
	}
}
