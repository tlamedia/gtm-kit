<?php
/**
 * GTM Kit Plugin
 *
 * Plugin Name: GTM Kit
 * Version:     2.2.2
 * Plugin URI:  https://gtmkit.com/
 * Description: Google Tag Manager implementation focusing on flexibility and pagespeed.
 * Author:      GTM Kit
 * Author URI:  https://gtmkit.com/
 * Text Domain: gtm-kit
 * Domain Path: /languages/
 * License:     GPLv3
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * WC requires at least: 8.4
 * WC tested up to: 9.6
 *
 * @package GTM Kit
 * @copyright Copyright (C) 2021-2024, GTM Kit ApS
 */

if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

const GTMKIT_VERSION = '2.2.2';

if ( ! defined( 'GTMKIT_FILE' ) ) {
	define( 'GTMKIT_FILE', __FILE__ );
}

if ( ! defined( 'GTMKIT_PATH' ) ) {
	define( 'GTMKIT_PATH', plugin_dir_path( GTMKIT_FILE ) );
}

if ( ! defined( 'GTMKIT_BASENAME' ) ) {
	define( 'GTMKIT_BASENAME', plugin_basename( GTMKIT_FILE ) );
}

if ( ! defined( 'GTMKIT_URL' ) ) {
	define( 'GTMKIT_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'GTMKIT_ADMIN_SLUG' ) ) {
	define( 'GTMKIT_ADMIN_SLUG', 'gtmkit_' );
}

// Load the WordPress Google Tag Manager Kit plugin.
require_once dirname( GTMKIT_FILE ) . '/inc/main.php';
