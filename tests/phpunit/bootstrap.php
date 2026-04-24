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

$gtmkit_plugin_dir = dirname( __DIR__, 2 );

if ( ! file_exists( $gtmkit_plugin_dir . '/vendor/autoload.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem is unavailable in a bare PHPUnit bootstrap; stderr is the appropriate surface for a setup failure message.
	fwrite( STDERR, "ERROR: Run `composer install` from the plugin root before running tests.\n" );
	exit( 1 );
}

$gtmkit_suite = 'unit';
foreach ( $_SERVER['argv'] ?? [] as $gtmkit_argv_index => $gtmkit_argv_value ) {
	if ( $gtmkit_argv_value === '--testsuite' && isset( $_SERVER['argv'][ $gtmkit_argv_index + 1 ] ) ) {
		$gtmkit_suite = $_SERVER['argv'][ $gtmkit_argv_index + 1 ];
		break;
	}
	if ( strpos( $gtmkit_argv_value, '--testsuite=' ) === 0 ) {
		$gtmkit_suite = substr( $gtmkit_argv_value, strlen( '--testsuite=' ) );
		break;
	}
}

require_once $gtmkit_plugin_dir . '/vendor/autoload.php';

if ( $gtmkit_suite === 'integration' ) {
	require_once $gtmkit_plugin_dir . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

	$gtmkit_wp_tests_dir = \Yoast\WPTestUtils\WPIntegration\get_path_to_wp_test_dir();
	if ( $gtmkit_wp_tests_dir === false ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem is unavailable in a bare PHPUnit bootstrap; stderr is the appropriate surface for a setup failure message.
		fwrite( STDERR, "ERROR: WP test suite not found. Set WP_TESTS_DIR or run bin/install-wp-tests.sh.\n" );
		exit( 1 );
	}

	// Load test helpers so tests_add_filter() is available before WP boots.
	require_once $gtmkit_wp_tests_dir . 'includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $gtmkit_plugin_dir ): void {
			require $gtmkit_plugin_dir . '/gtm-kit.php';
		}
	);

	// bootstrap_it() loads phpunit-polyfills, boots WP (which fires muplugins_loaded
	// and in turn our plugin loader above), and registers the PHP 8 MockObject autoloader.
	\Yoast\WPTestUtils\WPIntegration\bootstrap_it();

	unset( $gtmkit_wp_tests_dir );
}

if ( $gtmkit_suite !== 'integration' ) {
	require_once $gtmkit_plugin_dir . '/vendor/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';
}

unset( $gtmkit_plugin_dir, $gtmkit_suite, $gtmkit_argv_index, $gtmkit_argv_value );
