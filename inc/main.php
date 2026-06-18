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
use TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_Integration;
use TLA_Media\GTM_Kit\Admin\MetaBox;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\PluginAvailability;
use TLA_Media\GTM_Kit\Admin\SetupWizard;
use TLA_Media\GTM_Kit\Admin\Suggestions;
use TLA_Media\GTM_Kit\Common\Conditionals\ContactForm7Conditional;
use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use TLA_Media\GTM_Kit\Frontend\BasicDatalayerData;
use TLA_Media\GTM_Kit\Frontend\EngagementEvents;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Frontend\Stape;
use TLA_Media\GTM_Kit\Frontend\UserData;
use TLA_Media\GTM_Kit\Installation\Activation;
use TLA_Media\GTM_Kit\Installation\AutomaticUpdates;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Integration\ContactForm7;
use TLA_Media\GTM_Kit\Integration\EasyDigitalDownloads;
use TLA_Media\GTM_Kit\Integration\WooCommerce;
use TLA_Media\GTM_Kit\Integration\WooCommerceBlocks;


if ( ! defined( 'GTMKIT_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

require GTMKIT_PATH . 'vendor/autoload.php';

// Load Options backward compatibility (for gtm-kit-woo v1.4.0 and below).
require_once GTMKIT_PATH . 'src/Options/compatibility.php';

/**
 * Plugin activation hook.
 */
function gtmkit_plugin_activation(): void {
	$options = OptionsFactory::get_instance();
	new Activation( $options );
	do_action( 'gtmkit_activate' );
}

register_activation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\gtmkit_plugin_activation' );

/**
 * Plugin activation hook.
 */
function gtmkit_plugin_deactivation(): void {

	if ( function_exists( 'wp_set_option_autoload' ) ) {
		wp_set_option_autoload( 'gtmkit', false );
	}

	wp_clear_scheduled_hook( 'gtmkit_send_anonymous_data' );

	do_action( 'gtmkit_deactivate' );
}

register_deactivation_hook( GTMKIT_FILE, 'TLA_Media\GTM_Kit\gtmkit_plugin_deactivation' );

/**
 * Add plugin action links on Plugins page.
 *
 * @param array<string, string> $links Existing plugin action links.
 *
 * @return array<string, string>
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
 * @param array<string, string> $links Existing plugin action links.
 *
 * @return array<string, string>
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
 * Register the engagement-event hooks for any non-admin request.
 *
 * Runs on the frontend, AJAX, and REST contexts so the `wp_login`,
 * `user_register`, and `woocommerce_created_customer` actions still
 * write their handoff cookie when the login or registration flow
 * lives outside a normal frontend page render (e.g. JSON login
 * plugins, Woo Store API checkout).
 */
function gtmkit_engagement_events_init(): void {
	$options = OptionsFactory::get_instance();

	// `just_the_container` opts every dataLayer surface out, including
	// engagement events: the cookie that ends up pushed as a dataLayer
	// event is the same surface as a direct push.
	if ( $options->get( 'general', 'just_the_container' ) ) {
		return;
	}

	EngagementEvents::register( $options );
}

/**
 * Load frontend.
 */
function gtmkit_frontend_init(): void {
	$options         = OptionsFactory::get_instance();
	$rest_api_server = new RestAPIServer();
	$util            = new Util( $options, $rest_api_server );

	( new AdminAPI( $options, $util ) )->rest_init();

	$output_gate = Frontend::resolve_output_gate( $options );

	if ( ! $options->get( 'general', 'just_the_container' ) ) {
		BasicDatalayerData::register( $options );
		UserData::register( $options );

		// Integrations enqueue their own gtmkit-dependent bundles and call the
		// window.gtmkit runtime, so they must honor the same per-URL output
		// gate as the core runtime. On an excluded URL with no filter override
		// the gtmkit handle and its runtime are withheld, so registering an
		// integration here would enqueue a script that depends on a handle that
		// was never registered and calls a runtime that never loaded.
		if ( ! Frontend::is_output_suppressed( $output_gate ) ) {
			if ( $options->get( 'integrations', 'woocommerce_integration' ) && ( new WooCommerceConditional() )->is_met() ) {
				WooCommerce::register( $options, $util );
				WooCommerceBlocks::register( $options, WooCommerce::instance() );
			}
			if ( $options->get( 'integrations', 'cf7_integration' ) && ( new ContactForm7Conditional() )->is_met() ) {
				ContactForm7::register( $options, $util );
			}
			if ( $options->get( 'integrations', 'edd_integration' ) && ( new EasyDigitalDownloadsConditional() )->is_met() ) {
				EasyDigitalDownloads::register( $options, $util );
			}

			$engagement_enabled =
				$options->get( 'general', 'engagement_event_login_enabled' )
				|| $options->get( 'general', 'engagement_event_signup_enabled' )
				|| $options->get( 'general', 'engagement_event_search_enabled' );
			if ( $engagement_enabled ) {
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $util ): void {
						$util->enqueue_script( 'gtmkit-engagement-events', 'frontend/engagement-events.js' );
						wp_localize_script(
							'gtmkit-engagement-events',
							'gtmkitEngagementEvents',
							[
								'cookieName' => EngagementEvents::cookie_name(),
								'cookiePath' => EngagementEvents::cookie_path(),
							]
						);
					}
				);
			}
		}
	}

	Stape::register( $options );

	if ( $options->get( 'general', 'analytics_active' ) ) {
		Analytics::register( $options, $util );
	}

	Frontend::register( $options, $output_gate );
	require GTMKIT_PATH . 'inc/frontend-functions.php';
}

/**
 * Load backend.
 */
function gtmkit_admin_init(): void {

	$options = OptionsFactory::get_instance();

	if ( version_compare( get_option( 'gtmkit_version' ), GTMKIT_VERSION, '<' ) ) {
		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
		}

		new Upgrade( $options );
	}

	$rest_api_server     = new RestAPIServer();
	$util                = new Util( $options, $rest_api_server );
	$plugin_availability = new PluginAvailability();

	$notifications_handler = NotificationsHandler::get();

	AutomaticUpdates::register( $options );
	Suggestions::register( $notifications_handler, $plugin_availability, $options, $util );
	Analytics::register( $options, $util );
	MetaBox::register( $options );
	SetupWizard::register( $options, $util );
	GeneralOptionsPage::register( $options, $util );
	if ( ( new PremiumConditional() )->is_met() ) {
		add_filter( 'plugin_action_links_' . plugin_basename( GTMKIT_FILE ), 'TLA_Media\GTM_Kit\gtmkit_remove_deactivation_link', 11, 1 );
	}
	Introductions_Integration::register( $options, $util );

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
				FeaturesUtil::declare_compatibility( 'product_instance_caching', GTMKIT_FILE );
			}
		}
	);

	// Priority 0 so the textdomain is loaded before any other init callback that might call __() against it (WP 6.7+ warns when JIT loading triggers before init).
	add_action( 'init', 'TLA_Media\GTM_Kit\gtmkit_load_text_domain', 0 );

	// Engagement-event hooks (`wp_login`, `user_register`,
	// `woocommerce_created_customer`) fire on AJAX and REST requests
	// too (e.g. JSON login plugins, Woo blocks checkout). Register
	// outside the admin / frontend / AJAX split so the cookie write
	// path is attached on every request type the action can trigger
	// from. The cookie is the only side effect; no enqueues happen.
	if ( ! is_admin() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_engagement_events_init' );
	}

	if ( is_admin() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_admin_init' );
	} elseif ( ! wp_doing_ajax() ) {
		add_action( 'plugins_loaded', 'TLA_Media\GTM_Kit\gtmkit_frontend_init' );
	}
}
