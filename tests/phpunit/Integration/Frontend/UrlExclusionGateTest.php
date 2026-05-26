<?php
/**
 * Integration tests for the URL-exclusion gate folded into Frontend::register().
 *
 * Pattern: configure Options, call Frontend::register(), invoke the
 * enqueue callbacks that register() attached by walking the
 * wp_enqueue_scripts hook map (mirrors DataLayerDependencyTest), then
 * read state from `wp_scripts()`. Behaviour-based assertions are robust
 * against unrelated WP core / theme callbacks on shared hooks.
 *
 * Covers:
 *
 *  - empty pattern list → container + dependent scripts register and the
 *    wp_body_open / body_footer noscript callbacks attach.
 *  - configured pattern matches the current request → none of those
 *    callbacks attach, no scripts register.
 *  - `gtmkit_container_active` filter forces the container back on → the
 *    container fires even when the URL pattern matched.
 *  - `will_register_container()` mirrors the gate.
 *  - `gtmkit_is_url_excluded` filter is final.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Integration tests for the URL-exclusion gate in {@see Frontend::register()}.
 */
final class UrlExclusionGateTest extends WP_UnitTestCase {

	/**
	 * Saved REQUEST_URI to restore after each test.
	 *
	 * @var string|null
	 */
	private $saved_request_uri;

	/**
	 * Reset state and stash REQUEST_URI between tests.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->saved_request_uri = $_SERVER['REQUEST_URI'] ?? null;

		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		remove_all_filters( 'gtmkit_container_active' );
		remove_all_filters( 'gtmkit_excluded_url_patterns' );
		remove_all_filters( 'gtmkit_is_url_excluded' );

		foreach ( [ 'gtmkit', 'gtmkit-container', 'gtmkit-datalayer', 'gtmkit-delay' ] as $handle ) {
			wp_scripts()->remove( $handle );
		}

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TEST123' );
		$options->set_option( 'general', 'container_active', 1 );
		$options->set_option( 'general', 'excluded_url_patterns', [] );
		$options->set_option( 'general', 'just_the_container', 0 );
		// Frontend::register() compares with `=== '0'` to enable the
		// wp_body_open noscript branch, so the string form is what the React
		// admin app effectively persists for this radio setting.
		$options->set_option( 'general', 'noscript_implementation', '0' );

		remove_all_actions( 'wp_enqueue_scripts' );
		remove_all_actions( 'wp_body_open' );
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'body_footer' );
	}

	/**
	 * Restore the REQUEST_URI tests touched.
	 */
	public function tear_down(): void {
		if ( $this->saved_request_uri === null ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->saved_request_uri;
		}

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'excluded_url_patterns', [] );

		parent::tear_down();
	}

	/**
	 * Invoke every callback Frontend::register() attached to
	 * `wp_enqueue_scripts`, in priority order, by walking the hook map and
	 * filtering to GTM Kit callbacks. Mirrors the production order
	 * (priority 5 settings/data + datalayer, priority 6 container) without
	 * firing the shared hook itself, which avoids triggering unrelated
	 * core callbacks and keeps phpcs from flagging the core hook name.
	 */
	private function invoke_gtmkit_enqueue_callbacks(): void {
		$hook = $GLOBALS['wp_filter']['wp_enqueue_scripts'] ?? null;
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
	 * Capture the output of every GTM Kit callback attached to `wp_body_open`.
	 */
	private function capture_gtmkit_body_open(): string {
		$hook = $GLOBALS['wp_filter']['wp_body_open'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return '';
		}

		ob_start();
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
		return (string) ob_get_clean();
	}

	/**
	 * With no patterns configured, every emit surface fires as before.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_empty_pattern_list_emits_every_surface(): void {
		$_SERVER['REQUEST_URI'] = '/any-page';

		Frontend::register( OptionsFactory::get_instance() );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertTrue(
			wp_script_is( 'gtmkit-container', 'registered' ),
			'gtmkit-container must be registered when no pattern excludes the path.'
		);
		$this->assertTrue(
			wp_script_is( 'gtmkit', 'registered' ),
			'gtmkit settings/data script must be registered when not excluded.'
		);
		$this->assertTrue(
			wp_script_is( 'gtmkit-datalayer', 'registered' ),
			'gtmkit-datalayer script must be registered when not excluded.'
		);
		$this->assertStringContainsString(
			'<iframe',
			$this->capture_gtmkit_body_open(),
			'wp_body_open must render the GTM Kit noscript iframe when not excluded.'
		);
	}

	/**
	 * A matching glob pattern withholds every emit surface together.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_matching_pattern_withholds_every_emit_path(): void {
		$_SERVER['REQUEST_URI'] = '/checkout-embed/payment';
		$options                = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'excluded_url_patterns',
			[
				[
					'pattern' => '/checkout-embed/*',
					'mode'    => 'glob',
				],
			]
		);

		Frontend::register( $options );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertFalse(
			wp_script_is( 'gtmkit-container', 'registered' ),
			'gtmkit-container must not register on an excluded URL.'
		);
		$this->assertFalse(
			wp_script_is( 'gtmkit', 'registered' ),
			'gtmkit settings/data script must not register on an excluded URL.'
		);
		$this->assertFalse(
			wp_script_is( 'gtmkit-datalayer', 'registered' ),
			'gtmkit-datalayer script must not register on an excluded URL.'
		);
		$this->assertSame(
			'',
			$this->capture_gtmkit_body_open(),
			'wp_body_open must not render the GTM Kit noscript iframe on an excluded URL.'
		);
	}

	/**
	 * The `gtmkit_container_active` filter still has the final word.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_container_active_filter_overrides_exclusion(): void {
		$_SERVER['REQUEST_URI'] = '/checkout-embed/payment';
		$options                = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'excluded_url_patterns',
			[
				[
					'pattern' => '/checkout-embed/*',
					'mode'    => 'glob',
				],
			]
		);

		add_filter( 'gtmkit_container_active', '__return_true' );

		Frontend::register( $options );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertTrue(
			wp_script_is( 'gtmkit-container', 'registered' ),
			'A gtmkit_container_active=true filter must keep gtmkit-container registered even on an excluded URL.'
		);
		$this->assertStringContainsString(
			'<iframe',
			$this->capture_gtmkit_body_open(),
			'A gtmkit_container_active=true filter must keep the noscript iframe rendering even on an excluded URL.'
		);
	}

	/**
	 * `will_register_container()` mirrors the gate so dependent enqueue helpers stay consistent.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::will_register_container
	 */
	public function test_will_register_container_returns_false_when_excluded(): void {
		$_SERVER['REQUEST_URI'] = '/checkout-embed/payment';
		$options                = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'excluded_url_patterns',
			[
				[
					'pattern' => '/checkout-embed/*',
					'mode'    => 'glob',
				],
			]
		);

		$this->assertFalse( Frontend::will_register_container( $options ) );
	}

	/**
	 * The `gtmkit_is_url_excluded` filter is final.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_is_url_excluded_filter_can_force_exclusion(): void {
		$_SERVER['REQUEST_URI'] = '/normally-on';
		add_filter( 'gtmkit_is_url_excluded', '__return_true' );

		Frontend::register( OptionsFactory::get_instance() );
		$this->invoke_gtmkit_enqueue_callbacks();

		$this->assertFalse(
			wp_script_is( 'gtmkit-container', 'registered' ),
			'gtmkit_is_url_excluded=true must withhold gtmkit-container.'
		);
	}

	/**
	 * The shared output gate reports "not suppressed" on a normal URL, so
	 * gtmkit_frontend_init() registers the integrations alongside the runtime.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_output_gate_not_suppressed_on_normal_url(): void {
		$_SERVER['REQUEST_URI'] = '/any-page';

		$gate = Frontend::resolve_output_gate( OptionsFactory::get_instance() );

		$this->assertFalse( $gate['url_excluded'], 'A normal URL is not excluded.' );
		$this->assertTrue( $gate['container_active'], 'An active container stays active on a normal URL.' );
		$this->assertFalse(
			Frontend::is_output_suppressed( $gate ),
			'Integrations must register on a non-excluded URL.'
		);
	}

	/**
	 * On an excluded URL the shared gate reports "suppressed", which is the
	 * signal gtmkit_frontend_init() uses to skip the WooCommerce / CF7 / EDD
	 * integration enqueues alongside the withheld core runtime.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_output_gate_suppressed_on_excluded_url(): void {
		$_SERVER['REQUEST_URI'] = '/checkout-embed/payment';
		$options                = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'excluded_url_patterns',
			[
				[
					'pattern' => '/checkout-embed/*',
					'mode'    => 'glob',
				],
			]
		);

		$gate = Frontend::resolve_output_gate( $options );

		$this->assertTrue( $gate['url_excluded'], 'The configured pattern matches the request path.' );
		$this->assertFalse( $gate['container_active'], 'An excluded URL deactivates the container.' );
		$this->assertTrue(
			Frontend::is_output_suppressed( $gate ),
			'Integration enqueues must be skipped on an excluded URL.'
		);
	}

	/**
	 * A `gtmkit_container_active` override on an excluded URL flips the gate
	 * back to "not suppressed", so the integrations register again in tandem
	 * with the core runtime.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_output_gate_override_keeps_integrations_on_excluded_url(): void {
		$_SERVER['REQUEST_URI'] = '/checkout-embed/payment';
		$options                = OptionsFactory::get_instance();
		$options->set_option(
			'general',
			'excluded_url_patterns',
			[
				[
					'pattern' => '/checkout-embed/*',
					'mode'    => 'glob',
				],
			]
		);
		add_filter( 'gtmkit_container_active', '__return_true' );

		$gate = Frontend::resolve_output_gate( $options );

		$this->assertTrue( $gate['container_active'], 'The override forces the container active.' );
		$this->assertFalse(
			Frontend::is_output_suppressed( $gate ),
			'An override that re-enables the container must also re-enable integrations.'
		);
	}
}
