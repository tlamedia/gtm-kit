<?php
/**
 * Unit tests for what a site that is not production still outputs.
 *
 * Covers the two halves of the promise made to a developer working on a copy
 * of a site: the data layer is still built, and the console says why the
 * container is not there.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Output tests for a site that reports itself as something other than production.
 */
final class FrontendSiteKindOutputTest extends TestCase {

	/**
	 * The `gtmkit` option as stored on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * The value the stubbed `wp_get_environment_type` returns.
	 *
	 * @var string
	 */
	private string $environment = 'production';

	/**
	 * Roles the stubbed current user holds.
	 *
	 * @var array<int, string>
	 */
	private array $user_roles = [];

	/**
	 * Stub the WordPress functions the frontend and its dependencies touch.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		$_SERVER['REQUEST_URI'] = '/';

		Functions\stubs(
			[
				'sanitize_text_field' => null,
				'wp_unslash'          => null,
				'is_plugin_active'    => false,
				'esc_js'              => null,
			]
		);

		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );
		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => ( Options::OPTION_NAME === $name ) ? $this->stored : $default_value
		);
		Functions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- BrainMonkey stub stands in for wp_parse_url() with no WP available; PHP's native parse_url() is the only option here.
				return \parse_url( $url, $component );
			}
		);
		Functions\when( 'wp_get_current_user' )->alias(
			fn() => (object) [ 'roles' => $this->user_roles ]
		);
	}

	/**
	 * Reset the request path the gate reads.
	 *
	 * @inheritDoc
	 */
	protected function tear_down(): void {
		unset( $_SERVER['REQUEST_URI'] );

		parent::tear_down();
	}

	/**
	 * Store a `general` option group and hand back the Options over it.
	 *
	 * @param array<string, mixed> $general The stored general options.
	 *
	 * @return Options The options under test.
	 */
	private function options( array $general = [] ): Options {
		$this->stored = [ 'general' => array_merge( [ 'container_active' => true ], $general ) ];

		return new Options();
	}

	/**
	 * The data layer and the runtime still enqueue when the container does not.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_the_data_layer_still_enqueues_when_the_container_does_not(): void {
		$this->environment = 'staging';

		Actions\expectAdded( 'wp_enqueue_scripts' )
			->twice();

		Frontend::register( $this->options() );

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * The container script is never enqueued on a site that is not production.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_the_container_script_is_not_enqueued(): void {
		$this->environment = 'staging';

		Actions\expectAdded( 'wp_body_open' )->never();
		Actions\expectAdded( 'wp_head' )->never();

		Frontend::register( $this->options( [ 'noscript_implementation' => 0 ] ) );

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * The console names the site's own report as the reason.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::container_disabled
	 */
	public function test_the_console_names_the_reason(): void {
		$this->environment = 'staging';

		$frontend = new Frontend( $this->options() );

		ob_start();
		$frontend->container_disabled();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'WordPress reports this site as staging', $output );
		$this->assertStringNotContainsString( 'container is disabled', $output );
	}

	/**
	 * A container switched off on a production site keeps the wording it had.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::container_disabled
	 */
	public function test_a_container_switched_off_keeps_its_wording(): void {
		$this->environment = 'production';

		$frontend = new Frontend( $this->options( [ 'container_active' => false ] ) );

		ob_start();
		$frontend->container_disabled();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Google Tag Manager container is disabled.', $output );
		$this->assertStringNotContainsString( 'WordPress reports this site', $output );
	}

	/**
	 * An excluded role keeps its own line, whatever else is going on.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::container_disabled
	 */
	public function test_an_excluded_role_keeps_its_own_line(): void {
		$this->environment = 'production';
		$this->user_roles  = [ 'administrator' ];

		$frontend = new Frontend( $this->options( [ 'exclude_user_roles' => [ 'administrator' ] ] ) );

		ob_start();
		$frontend->container_disabled();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Google Tag Manager container is disabled.', $output );
		$this->assertStringContainsString( 'current user role is excluded from tracking', $output );
	}
}
