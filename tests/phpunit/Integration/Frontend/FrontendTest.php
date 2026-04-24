<?php
/**
 * Integration test template for the gtm-kit frontend snippet emitter.
 *
 * Pattern: boots WordPress via `yoast/wp-test-utils`, configures Options,
 * calls the snippet renderer directly, captures output via ob_start,
 * asserts the emitted script contains the configured container ID and
 * dataLayer name.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\Frontend::get_gtm_script()} at
 * src/Frontend/Frontend.php. This is the pure emitter invoked by the
 * `wp_enqueue_scripts` → `enqueue_header_script()` chain and attached to
 * `<head>` via `wp_add_inline_script()`. Future tests that need to
 * exercise the full hook pipeline can wrap `do_action( 'wp_head' )`
 * around the Options setup; this starter intentionally tests the emitter
 * in isolation so the pattern stays compact.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Integration tests for {@see Frontend::get_gtm_script()}.
 */
final class FrontendTest extends WP_UnitTestCase {

	/**
	 * The rendered snippet carries the configured container ID and dataLayer name.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_gtm_script
	 */
	public function test_get_gtm_script_renders_container_id_and_datalayer_name(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'datalayer_name', 'customDataLayer' );

		$frontend = new Frontend( $options );

		ob_start();
		$frontend->get_gtm_script( 'GTM-TEST123' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'GTM-TEST123', $output, 'Emitted snippet should carry the configured container ID.' );
		$this->assertStringContainsString( "'customDataLayer'", $output, 'Emitted snippet should carry the configured dataLayer name.' );
		$this->assertStringContainsString( 'www.googletagmanager.com', $output, 'Emitted snippet should default to the googletagmanager.com host.' );
	}
}
