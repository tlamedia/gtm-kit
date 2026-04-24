<?php
/**
 * PHPUnit bootstrap for the gtm-kit plugin.
 *
 * Dispatches to one of two harnesses based on the `--testsuite` PHPUnit
 * argument:
 *
 *  - `unit`        — BrainMonkey via `yoast/wp-test-utils`. No WP boot,
 *                    no database. Fast and isolated.
 *  - `integration` — WordPress native test suite via `yoast/wp-test-utils`.
 *                    Requires `bin/install-wp-tests.sh` to have been run
 *                    and `WP_TESTS_DIR` to point at the WP test install.
 *                    Loads gtm-kit.php via the `muplugins_loaded` hook so
 *                    the plugin's constants and classes are available in
 *                    integration tests.
 *
 * Running `vendor/bin/phpunit` without `--testsuite` defaults to `unit`
 * so the lightest harness runs when in doubt.
 *
 * @package TLA_Media\GTM_Kit
 */

$GTMKIT_PLUGIN_DIR = dirname( __DIR__, 2 );

if ( ! file_exists( $GTMKIT_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
	fwrite( STDERR, "ERROR: Run `composer install` from the plugin root before running tests.\n" );
	exit( 1 );
}

$GTMKIT_SUITE = 'unit';
foreach ( $_SERVER['argv'] ?? [] as $index => $arg ) {
	if ( $arg === '--testsuite' && isset( $_SERVER['argv'][ $index + 1 ] ) ) {
		$GTMKIT_SUITE = $_SERVER['argv'][ $index + 1 ];
		break;
	}
	if ( strpos( $arg, '--testsuite=' ) === 0 ) {
		$GTMKIT_SUITE = substr( $arg, strlen( '--testsuite=' ) );
		break;
	}
}

require_once $GTMKIT_PLUGIN_DIR . '/vendor/autoload.php';

if ( $GTMKIT_SUITE === 'integration' ) {
	require_once $GTMKIT_PLUGIN_DIR . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

	$GTMKIT_WP_TESTS_DIR = \Yoast\WPTestUtils\WPIntegration\get_path_to_wp_test_dir();
	if ( $GTMKIT_WP_TESTS_DIR === false ) {
		fwrite( STDERR, "ERROR: WP test suite not found. Set WP_TESTS_DIR or run bin/install-wp-tests.sh.\n" );
		exit( 1 );
	}

	// Load test helpers so tests_add_filter() is available before WP boots.
	require_once $GTMKIT_WP_TESTS_DIR . 'includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $GTMKIT_PLUGIN_DIR ): void {
			require $GTMKIT_PLUGIN_DIR . '/gtm-kit.php';
		}
	);

	// bootstrap_it() loads phpunit-polyfills, boots WP (which fires muplugins_loaded
	// and in turn our plugin loader above), and registers the PHP 8 MockObject autoloader.
	\Yoast\WPTestUtils\WPIntegration\bootstrap_it();

	unset( $GTMKIT_WP_TESTS_DIR );
} else {
	require_once $GTMKIT_PLUGIN_DIR . '/vendor/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';
}

unset( $GTMKIT_PLUGIN_DIR, $GTMKIT_SUITE );
