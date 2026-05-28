<?php
/**
 * Unit tests for the Contact Form 7 integration's hook-registration
 * behaviour.
 *
 * The integration ships a "Load JavaScript" setting with two modes:
 *
 *  - `1` — "Only on pages where the Contact Form 7 script is registered
 *    (recommended)". Hooks into the `wpcf7_enqueue_scripts` action
 *    Contact Form 7 fires from inside its own enqueue path, so the
 *    integration script lands whether CF7 enqueued the normal way
 *    (during `wp_enqueue_scripts`) or the late way (during shortcode
 *    render, used by performance plugins like WP Rocket that filter
 *    `wpcf7_load_js` to false).
 *  - `2` — "On all pages". Hooks `wp_enqueue_scripts` directly so the
 *    integration loads unconditionally.
 *
 * These tests assert the hook wiring picked by each mode.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Integration;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\ContactForm7;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see ContactForm7::register()}.
 */
final class ContactForm7Test extends TestCase {

	/**
	 * Stub `get_option` so the in-memory `Options` instance returns
	 * the supplied `cf7_load_js` value.
	 *
	 * @param int $cf7_load_js Setting value to read back.
	 */
	private function stub_options_with_load_mode( int $cf7_load_js ): void {
		Functions\stubs(
			[
				'get_option'        => [
					'integrations' => [
						'cf7_load_js' => $cf7_load_js,
					],
				],
				'add_filter'        => null,
				// Stubbed so `Util::load_plugin_api()` short-circuits
				// without trying to require `wp-admin/includes/plugin.php`,
				// which is unavailable in the unit harness.
				'is_plugin_active'  => false,
				'wp_normalize_path' => static fn( $path ) => $path,
			]
		);
	}

	/**
	 * Build a `Util` instance against the stubbed options.
	 *
	 * @return array{0: Options, 1: Util}
	 */
	private function dependencies(): array {
		if ( ! defined( 'GTMKIT_PATH' ) ) {
			define( 'GTMKIT_PATH', '/fake/plugin/path/' );
		}
		if ( ! defined( 'GTMKIT_URL' ) ) {
			define( 'GTMKIT_URL', 'https://example.test/wp-content/plugins/gtm-kit/' );
		}

		$options = Options::create();
		$util    = new Util( $options, new RestAPIServer() );

		return [ $options, $util ];
	}

	/**
	 * The recommended mode (`cf7_load_js = 1`) attaches to
	 * `wpcf7_enqueue_scripts` so the integration loads whenever CF7
	 * actually enqueues, including the late shortcode-time path used
	 * by WP Rocket.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\ContactForm7::register
	 */
	public function test_recommended_mode_hooks_wpcf7_enqueue_scripts(): void {
		$this->stub_options_with_load_mode( 1 );
		[ $options, $util ] = $this->dependencies();

		Actions\expectAdded( 'wpcf7_enqueue_scripts' )->once();

		ContactForm7::register( $options, $util );

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * The "On all pages" mode (`cf7_load_js = 2`) attaches directly
	 * to `wp_enqueue_scripts` so the integration loads on every
	 * frontend request.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\ContactForm7::register
	 */
	public function test_all_pages_mode_hooks_wp_enqueue_scripts(): void {
		$this->stub_options_with_load_mode( 2 );
		[ $options, $util ] = $this->dependencies();

		Actions\expectAdded( 'wp_enqueue_scripts' )->once();

		ContactForm7::register( $options, $util );

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * An unrecognised mode value falls through to the recommended
	 * path so misconfigured installs still receive the integration
	 * via the action route.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\ContactForm7::register
	 */
	public function test_unknown_mode_falls_back_to_recommended(): void {
		$this->stub_options_with_load_mode( 99 );
		[ $options, $util ] = $this->dependencies();

		Actions\expectAdded( 'wpcf7_enqueue_scripts' )->once();

		ContactForm7::register( $options, $util );

		self::assertTrue( true ); // assertion is the action expectation above.
	}
}
