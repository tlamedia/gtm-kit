<?php
/**
 * Integration tests for the "Just the container" setting.
 *
 * Pattern: configure Options, call Frontend::register(), invoke the
 * enqueue callbacks that register() attached by walking the
 * wp_enqueue_scripts hook map (mirrors UrlExclusionGateTest), then print
 * the script queue. Assertions run on the printed output, because a
 * script can be enqueued and still never print when one of its
 * dependencies is missing.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Integration tests for the container output with "Just the container" on.
 */
final class JustTheContainerTest extends WP_UnitTestCase {

	/**
	 * GTM Kit script handles a test may register.
	 */
	private const HANDLES = [ 'gtmkit', 'gtmkit-container', 'gtmkit-datalayer', 'gtmkit-delay', 'gtmkit-consent-gating' ];

	/**
	 * Start every test with no GTM Kit scripts registered and a container fixture.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::HANDLES as $handle ) {
			wp_scripts()->remove( $handle );
		}
		wp_scripts()->done = [];

		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		remove_all_filters( 'gtmkit_container_active' );
		remove_all_filters( 'gtmkit_is_url_excluded' );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-JUST123' );
		$options->set_option( 'general', 'container_active', 1 );
		$options->set_option( 'general', 'excluded_url_patterns', [] );
		$options->set_option( 'general', 'just_the_container', 1 );
		$options->set_option( 'general', 'noscript_implementation', 0 );
		$options->set_option( 'general', 'script_implementation', 0 );
		$options->set_option( 'general', 'load_js_event', 0 );

		remove_all_actions( 'wp_enqueue_scripts' );
		remove_all_actions( 'wp_body_open' );
		remove_all_actions( 'wp_head' );
	}

	/**
	 * Restore the options the tests changed.
	 */
	public function tear_down(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'just_the_container', 0 );
		$options->set_option( 'general', 'load_js_event', 0 );

		parent::tear_down();
	}

	/**
	 * Invoke every GTM Kit callback attached to `wp_enqueue_scripts`, in priority order.
	 */
	private function invoke_gtmkit_enqueue_callbacks(): void {
		$this->invoke_gtmkit_callbacks( 'wp_enqueue_scripts' );
	}

	/**
	 * Invoke every GTM Kit callback attached to a hook, in priority order.
	 *
	 * @param string $hook_name The hook to walk.
	 */
	private function invoke_gtmkit_callbacks( string $hook_name ): void {
		$hook = $GLOBALS['wp_filter'][ $hook_name ] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return;
		}

		ksort( $hook->callbacks );
		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( ! is_array( $callback['function'] ) ) {
					continue;
				}
				$target = $callback['function'][0];
				if ( $target instanceof Frontend ) {
					$method = $callback['function'][1];
					$target->{ $method }();
				}
			}
		}
	}

	/**
	 * Print the GTM Kit scripts, head and footer, and return the output.
	 */
	private function print_scripts(): string {
		ob_start();
		wp_scripts()->do_items( self::HANDLES );
		return (string) ob_get_clean();
	}

	/**
	 * The GTM loader for the configured container prints in the page.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_container_loader_prints(): void {
		Frontend::register( OptionsFactory::get_instance() );
		$this->invoke_gtmkit_enqueue_callbacks();

		$output = $this->print_scripts();

		$this->assertStringContainsString( 'gtm.js', $output, 'The GTM loader must print.' );
		$this->assertStringContainsString( "'GTM-JUST123'", $output, 'The loader must carry the configured container ID.' );
		$this->assertStringContainsString( 'id="gtmkit-container-js-after"', $output );
	}

	/**
	 * The settings and dataLayer scripts stay switched off.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_settings_and_datalayer_scripts_stay_off(): void {
		Frontend::register( OptionsFactory::get_instance() );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertFalse( wp_script_is( 'gtmkit', 'registered' ), 'The settings script must not register.' );
		$this->assertFalse( wp_script_is( 'gtmkit-datalayer', 'registered' ), 'The dataLayer script must not register.' );
		$this->assertStringNotContainsString( 'window.gtmkit', $this->print_scripts() );
	}

	/**
	 * The noscript iframe still prints at `wp_body_open`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_body_script
	 */
	public function test_noscript_iframe_prints(): void {
		Frontend::register( OptionsFactory::get_instance() );

		ob_start();
		$this->invoke_gtmkit_callbacks( 'wp_body_open' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<iframe', $output );
		$this->assertStringContainsString( 'GTM-JUST123', $output );
	}

	/**
	 * With the delay event on, its push prints after the container loader.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_delay_js_script
	 */
	public function test_delay_event_prints_after_container(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'load_js_event', 1 );

		Frontend::register( $options );
		$this->invoke_gtmkit_enqueue_callbacks();

		$output    = $this->print_scripts();
		$container = strpos( $output, 'gtm.js' );
		$delay     = strpos( $output, 'load_delayed_js' );

		$this->assertNotFalse( $container, 'The GTM loader must print.' );
		$this->assertNotFalse( $delay, 'The delay event must print.' );
		$this->assertGreaterThan( $container, $delay, 'The delay event must print after the container.' );
	}

	/**
	 * With the setting off, the container still depends on the settings script.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::enqueue_header_script
	 */
	public function test_container_depends_on_settings_script_when_off(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'just_the_container', 0 );

		Frontend::register( $options );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertSame( [ 'gtmkit' ], wp_scripts()->registered['gtmkit-container']->deps );
	}
}
