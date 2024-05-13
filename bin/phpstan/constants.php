<?php
/**
 * Constants for PHPStan static analysis.
 *
 * @package GTM Kit
 */

$gtmkit_file = dirname( __DIR__ ) . '/gtm-kit.php';
define( 'GTMKIT_FILE', $gtmkit_file );
define( 'GTMKIT_PATH', plugin_dir_path( GTMKIT_FILE ) );
define( 'GTMKIT_BASENAME', plugin_basename( GTMKIT_FILE ) );
define( 'GTMKIT_URL', plugin_dir_url( $gtmkit_file ) );
define( 'GTMKIT_ADMIN_SLUG', 'gtmkit_' );
