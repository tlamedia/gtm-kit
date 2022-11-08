<?php
/**
 * Google Tag Manager Kit.
 *
 * Plugin Name: GTM Kit
 * Version:     1.4.1
 * Plugin URI:  https://gtmkit.com/
 * Description: Google Tag Manager implementation focusing on flexibility and pagespeed.
 * Author:      TLA Media
 * Author URI:  https://www.tlamedia.dk/
 * Text Domain: gtmkit
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 6.0
 * Requires PHP: 7.2
 *
 * WC requires at least: 6.6
 * WC tested up to: 7.0
 *
 * @copyright Copyright (C) 2022, TLA Media ApS
 */


if ( ! function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

define( 'GTMKIT_VERSION', '1.4.1' );

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
