<?php
/**
 * Unit tests for the site-kind half of the output gate.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate()}
 * across the kinds of site WordPress can report, the setting that loads the
 * container anyway, the `gtmkit_container_active` escape hatch, and the
 * combination with URL exclusion.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Gate tests for a site that reports itself as something other than production.
 */
final class FrontendSiteKindGateTest extends TestCase {

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
	 * Stub the WordPress functions the gate and its dependencies touch.
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
	 * Resolve the gate over a given `general` option group.
	 *
	 * @param array<string, mixed> $general The stored general options.
	 *
	 * @return array{url_excluded: bool, container_active: bool, environment_suppressed?: bool} The resolved gate.
	 */
	private function gate( array $general = [] ): array {
		$this->stored = [ 'general' => array_merge( [ 'container_active' => true ], $general ) ];

		return Frontend::resolve_output_gate( new Options() );
	}

	/**
	 * A production site loads the container, as it always has.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 */
	public function test_a_production_site_loads_the_container(): void {
		$this->environment = 'production';

		$gate = $this->gate();

		$this->assertTrue( $gate['container_active'] );
		$this->assertFalse( $gate['environment_suppressed'] );
	}

	/**
	 * Staging, development and local withhold the container by default.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 */
	public function test_a_site_that_is_not_production_withholds_the_container(): void {
		foreach ( [ 'staging', 'development', 'local' ] as $type ) {
			$this->environment = $type;

			$gate = $this->gate();

			$this->assertFalse(
				$gate['container_active'],
				sprintf( 'A site reporting itself as %s must not load the container.', $type )
			);
			$this->assertTrue( $gate['environment_suppressed'] );
		}
	}

	/**
	 * The setting loads the container on a site that is not production.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 */
	public function test_the_setting_loads_the_container_anyway(): void {
		foreach ( [ 'staging', 'development', 'local' ] as $type ) {
			$this->environment = $type;

			$gate = $this->gate( [ 'load_on_non_production' => true ] );

			$this->assertTrue(
				$gate['container_active'],
				sprintf( 'The setting must load the container on a site reporting itself as %s.', $type )
			);
			$this->assertFalse( $gate['environment_suppressed'] );
		}
	}

	/**
	 * The filter still forces the container back on, since it runs last.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 */
	public function test_the_filter_can_force_the_container_back_on(): void {
		$this->environment = 'staging';

		Filters\expectApplied( 'gtmkit_container_active' )
			->once()
			->with( false )
			->andReturn( true );

		$gate = $this->gate();

		$this->assertTrue( $gate['container_active'], 'A filter must be able to override the withheld container.' );
		$this->assertTrue( $gate['environment_suppressed'], 'The reason stays visible even when a filter overrides it.' );
	}

	/**
	 * A withheld container is not a withheld request: the data layer still ships.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_a_withheld_container_still_leaves_the_data_layer_in_place(): void {
		$this->environment = 'staging';

		$gate = $this->gate();

		$this->assertFalse(
			Frontend::is_output_suppressed( $gate ),
			'Withholding the container must not withhold the data layer and the runtime with it.'
		);
	}

	/**
	 * An excluded URL still withholds everything on a site that is not production.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::resolve_output_gate
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::is_output_suppressed
	 */
	public function test_an_excluded_url_still_withholds_everything(): void {
		$this->environment      = 'staging';
		$_SERVER['REQUEST_URI'] = '/checkout-embed/step-one';

		$gate = $this->gate(
			[
				'excluded_url_patterns' => [
					[
						'pattern' => '/checkout-embed/*',
						'mode'    => 'glob',
					],
				],
			]
		);

		$this->assertTrue( $gate['url_excluded'] );
		$this->assertFalse( $gate['container_active'] );
		$this->assertTrue(
			Frontend::is_output_suppressed( $gate ),
			'URL exclusion keeps its own meaning regardless of what the site reports.'
		);
	}
}
