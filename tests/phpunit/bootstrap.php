<?php
/**
 * PHPUnit bootstrap for the gtm-kit plugin.
 *
 * Dispatches to one of three harnesses based on the `--testsuite` PHPUnit
 * argument:
 *
 *  - `unit`            — BrainMonkey via `yoast/wp-test-utils`. No WP boot,
 *                        no database. Fast and isolated.
 *  - `integration`     — WordPress native test suite via `yoast/wp-test-utils`.
 *                        Requires `bin/install-wp-tests.sh` to have been run
 *                        and `WP_TESTS_DIR` to point at the WP test install.
 *                        Loads gtm-kit.php via the `muplugins_loaded` hook so
 *                        the plugin's constants and classes are available in
 *                        integration tests.
 *  - `integration-woo` — as `integration`, plus a real WooCommerce booted
 *                        ahead of the plugin and a seeded store config, for
 *                        the WooCommerce integration surface. WooCommerce is
 *                        provisioned into the WP test install by
 *                        `bin/install-wp-tests.sh`, whose WC version argument
 *                        lets the CI matrix run both the newest WooCommerce
 *                        and the oldest the plugin header supports.
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

if ( $gtmkit_suite === 'integration' || $gtmkit_suite === 'integration-woo' ) {
	require_once $gtmkit_plugin_dir . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

	$gtmkit_wp_tests_dir = \Yoast\WPTestUtils\WPIntegration\get_path_to_wp_test_dir();
	if ( $gtmkit_wp_tests_dir === false ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem is unavailable in a bare PHPUnit bootstrap; stderr is the appropriate surface for a setup failure message.
		fwrite( STDERR, "ERROR: WP test suite not found. Set WP_TESTS_DIR or run bin/install-wp-tests.sh.\n" );
		exit( 1 );
	}

	// Load test helpers so tests_add_filter() is available before WP boots.
	require_once $gtmkit_wp_tests_dir . 'includes/functions.php';

	$gtmkit_load_woo = ( $gtmkit_suite === 'integration-woo' );

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $gtmkit_plugin_dir, $gtmkit_load_woo ): void {
			if ( $gtmkit_load_woo ) {
				$woo_main = ABSPATH . 'wp-content/plugins/woocommerce/woocommerce.php';
				if ( ! file_exists( $woo_main ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem is unavailable in a bare PHPUnit bootstrap; stderr is the appropriate surface for a setup failure message.
					fwrite( STDERR, "ERROR: WooCommerce not installed in WP test install. Re-run bin/install-wp-tests.sh.\n" );
					exit( 1 );
				}
				require $woo_main;
			}

			require $gtmkit_plugin_dir . '/gtm-kit.php';

			if ( $gtmkit_load_woo ) {
				// Seed the store config BEFORE plugins_loaded fires: the
				// WooCommerce integration caches both the integration
				// toggle and the store currency on that hook.
				update_option(
					'gtmkit',
					[
						'general'      => [
							'gtm_id'           => 'GTM-TEST123',
							'container_active' => '1',
							'datalayer_name'   => 'dataLayer',
						],
						'integrations' => [
							'woocommerce_integration' => '1',
							'woocommerce_view_item_list_limit' => 12,
						],
					],
					true
				);

				update_option( 'woocommerce_currency', 'USD' );
				update_option( 'woocommerce_default_country', 'US:CA' );
				update_option( 'woocommerce_price_thousand_sep', ',' );
				update_option( 'woocommerce_price_decimal_sep', '.' );
				update_option( 'woocommerce_price_num_decimals', 2 );
			}
		}
	);

	// bootstrap_it() loads phpunit-polyfills, boots WP (which fires muplugins_loaded
	// and in turn our plugin loader above), and registers the PHP 8 MockObject autoloader.
	\Yoast\WPTestUtils\WPIntegration\bootstrap_it();

	if ( $gtmkit_load_woo && class_exists( 'WC_Install' ) ) {
		\WC_Install::install();
		// install() only runs maybe_create_pages() behind gates that do not
		// fire under PHPUnit. Call it directly so is_cart() / is_checkout()
		// can resolve their target pages.
		\WC_Install::create_pages();
		if ( function_exists( 'WC' ) && method_exists( WC(), 'wpdb_table_fix' ) ) {
			WC()->wpdb_table_fix();
		}

		$gtmkit_helpers_dir = ABSPATH . 'wc-test-helpers';
		if ( is_dir( $gtmkit_helpers_dir ) ) {
			$gtmkit_helper_files = glob( $gtmkit_helpers_dir . '/class-wc-helper-*.php' );
			foreach ( is_array( $gtmkit_helper_files ) ? $gtmkit_helper_files : [] as $gtmkit_helper_file ) {
				require_once $gtmkit_helper_file;
			}
			unset( $gtmkit_helper_files, $gtmkit_helper_file );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- WP_Filesystem is unavailable in a bare PHPUnit bootstrap; stderr is the appropriate surface for a setup failure message.
			fwrite( STDERR, "WARNING: Woo test helpers not found at $gtmkit_helpers_dir. Re-run bin/install-wp-tests.sh.\n" );
		}
		unset( $gtmkit_helpers_dir );
	}

	unset( $gtmkit_wp_tests_dir, $gtmkit_load_woo );
}

if ( $gtmkit_suite === 'unit' ) {
	require_once $gtmkit_plugin_dir . '/vendor/yoast/wp-test-utils/src/BrainMonkey/bootstrap.php';

	// Bare class stubs for WP types referenced in SUT typehints so the
	// unit suite can reflect over signatures without booting WordPress.
	require_once $gtmkit_plugin_dir . '/tests/phpunit/Unit/stubs/wp-user.php';
	require_once $gtmkit_plugin_dir . '/tests/phpunit/Unit/stubs/wp-query.php';
	require_once $gtmkit_plugin_dir . '/tests/phpunit/Unit/stubs/wp-error.php';

	// Test helpers that aren't autoloaded (small subclasses of SUT
	// classes used as test seams). PHPUnit's test files themselves are
	// loaded by the runner, but supporting classes referenced from
	// `set_up()` need to be wired explicitly here.
	require_once $gtmkit_plugin_dir . '/tests/phpunit/Unit/Frontend/HeadersOpenEngagementEvents.php';
}

unset( $gtmkit_plugin_dir, $gtmkit_suite, $gtmkit_argv_index, $gtmkit_argv_value );
