<?php
/**
 * Unit tests for where the `<noscript>` container iframe is placed.
 *
 * The placement setting is stored as an integer, so the registration branch
 * has to compare integers. These tests pin each of the four placements to the
 * hook it belongs on, and pin the two placements that hook nothing at all so
 * a site that prints the iframe from its own template never gets a second one.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Placement tests for the `<noscript>` container iframe.
 */
final class FrontendNoscriptIframeTest extends TestCase {

	/**
	 * The `gtmkit` option as stored on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Stub the WordPress functions the frontend and its dependencies touch.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		$_SERVER['REQUEST_URI'] = '/';

		require_once \dirname( __DIR__, 4 ) . '/inc/frontend-functions.php';

		Functions\stubs(
			[
				'sanitize_text_field' => null,
				'wp_unslash'          => null,
				'is_plugin_active'    => false,
				'esc_attr'            => null,
				'esc_js'              => null,
			]
		);

		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
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
			static fn() => (object) [ 'roles' => [] ]
		);

		OptionsFactory::reset();
	}

	/**
	 * Drop the request path and the shared Options instance.
	 *
	 * @inheritDoc
	 */
	protected function tear_down(): void {
		unset( $_SERVER['REQUEST_URI'] );

		OptionsFactory::reset();

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
		$this->stored = [
			'general' => array_merge(
				[
					'container_active' => true,
					'gtm_id'           => 'GTM-TEST123',
				],
				$general
			),
		];

		OptionsFactory::reset();

		return new Options();
	}

	/**
	 * Capture what a Frontend built over the given options writes out.
	 *
	 * @param Options $options The options under test.
	 *
	 * @return string The captured markup.
	 */
	private function body_script_output( Options $options ): string {
		$frontend = new Frontend( $options );

		ob_start();
		$frontend->get_body_script();

		return (string) ob_get_clean();
	}

	/**
	 * The default placement puts the iframe on the opening body tag.
	 *
	 * Covers both an explicitly stored `0` and a site that has never touched
	 * the setting, because the schema default is the same integer and both
	 * have to reach the same hook.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_body_script
	 *
	 * @dataProvider data_default_placement
	 *
	 * @param array<string, mixed> $general The stored general options.
	 */
	public function test_the_default_placement_hooks_the_opening_body_tag( array $general ): void {
		Actions\expectAdded( 'wp_body_open' )->once();
		Actions\expectAdded( 'wp_footer' )->never();
		Actions\expectAdded( 'body_footer' )->never();

		$options = $this->options( $general );
		Frontend::register( $options );

		$output = $this->body_script_output( $options );

		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertStringContainsString( 'ns.html?id=GTM-TEST123', $output );
	}

	/**
	 * The stored-zero and never-saved cases.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function data_default_placement(): array {
		return [
			'stored as the integer zero' => [ [ 'noscript_implementation' => 0 ] ],
			'never saved at all'         => [ [] ],
		];
	}

	/**
	 * The footer placement hooks the footer and leaves the body tag alone.
	 *
	 * The hook has to be `wp_footer`. WordPress fires no hook named
	 * `body_footer`, and neither does any theme or plugin, so a site on this
	 * placement got no iframe at all for as long as that name was used. The
	 * dead name is pinned here so it cannot come back.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_the_footer_placement_hooks_the_footer_only(): void {
		Actions\expectAdded( 'wp_footer' )->once();
		Actions\expectAdded( 'body_footer' )->never();
		Actions\expectAdded( 'wp_body_open' )->never();

		Frontend::register( $this->options( [ 'noscript_implementation' => 1 ] ) );

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * The footer placement writes one iframe, pointing at the container.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_body_script
	 */
	public function test_the_footer_placement_writes_exactly_one_iframe(): void {
		$options = $this->options( [ 'noscript_implementation' => 1 ] );
		Frontend::register( $options );

		$output = $this->body_script_output( $options );

		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertStringContainsString( 'ns.html?id=GTM-TEST123', $output );
	}

	/**
	 * The template placement hooks nothing, so the template call stands alone.
	 *
	 * A site on this setting calls `gtmkit_the_noscript_tag()` from its own
	 * theme. If registration also hooked the iframe onto a placement, those
	 * sites would get two of them, which is the one regression this change
	 * could plausibly introduce.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 * @covers ::gtmkit_the_noscript_tag
	 */
	public function test_the_template_placement_hooks_nothing_and_prints_once(): void {
		Actions\expectAdded( 'wp_body_open' )->never();
		Actions\expectAdded( 'wp_footer' )->never();
		Actions\expectAdded( 'body_footer' )->never();

		Frontend::register( $this->options( [ 'noscript_implementation' => 2 ] ) );

		ob_start();
		gtmkit_the_noscript_tag();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
		$this->assertStringContainsString( 'ns.html?id=GTM-TEST123', $output );
	}

	/**
	 * The disabled placement hooks nothing and the template call stays silent.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 * @covers ::gtmkit_the_noscript_tag
	 */
	public function test_the_disabled_placement_prints_nothing_anywhere(): void {
		Actions\expectAdded( 'wp_body_open' )->never();
		Actions\expectAdded( 'wp_footer' )->never();
		Actions\expectAdded( 'body_footer' )->never();

		Frontend::register( $this->options( [ 'noscript_implementation' => 3 ] ) );

		ob_start();
		gtmkit_the_noscript_tag();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * An excluded user role places no iframe on either hooked placement.
	 *
	 * The iframe is container output, so it answers to the role exclusion the
	 * header script answers to. A visitor whose role is excluded and who has
	 * JavaScript switched off would otherwise still be counted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 *
	 * @dataProvider data_hooked_placements
	 *
	 * @param int $placement The stored placement setting.
	 */
	public function test_an_excluded_role_places_no_iframe( int $placement ): void {
		Functions\when( 'wp_get_current_user' )->alias(
			static fn() => (object) [ 'roles' => [ 'administrator' ] ]
		);

		Actions\expectAdded( 'wp_body_open' )->never();
		Actions\expectAdded( 'wp_footer' )->never();

		Frontend::register(
			$this->options(
				[
					'noscript_implementation' => $placement,
					'exclude_user_roles'      => [ 'administrator' ],
				]
			)
		);
	}

	/**
	 * The placements that register a hook of their own.
	 *
	 * @return array<string, array{int}>
	 */
	public function data_hooked_placements(): array {
		return [
			'opening body tag' => [ 0 ],
			'footer'           => [ 1 ],
		];
	}

	/**
	 * The template placement stays silent wherever the container is withheld.
	 *
	 * The theme calls this one directly, so it never passes through
	 * registration and has to ask for the same gates itself. Without them a
	 * hand-placed fallback would keep pointing at the live container on a
	 * staging copy, for an excluded role, or with container output switched
	 * off outright.
	 *
	 * @covers ::gtmkit_the_noscript_tag
	 *
	 * @dataProvider data_withheld_container
	 *
	 * @param array<string, mixed> $general     The stored general options.
	 * @param string               $environment What WordPress reports the site as.
	 * @param string[]             $roles       The current user's roles.
	 */
	public function test_the_template_placement_honours_the_container_gates(
		array $general,
		string $environment,
		array $roles
	): void {
		Functions\when( 'wp_get_environment_type' )->justReturn( $environment );
		Functions\when( 'wp_get_current_user' )->alias(
			static fn() => (object) [ 'roles' => $roles ]
		);

		$this->options( array_merge( [ 'noscript_implementation' => 2 ], $general ) );

		ob_start();
		gtmkit_the_noscript_tag();
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * The states in which no container belongs on the page.
	 *
	 * @return array<string, array{array<string, mixed>, string, string[]}>
	 */
	public function data_withheld_container(): array {
		return [
			'container switched off' => [ [ 'container_active' => false ], 'production', [] ],
			'no container id'        => [ [ 'gtm_id' => '' ], 'production', [] ],
			'staging site'           => [ [], 'staging', [] ],
			'excluded user role'     => [ [ 'exclude_user_roles' => [ 'administrator' ] ], 'production', [ 'administrator' ] ],
		];
	}

	/**
	 * A staging site that opts back in still gets its template placement.
	 *
	 * The gate has to withhold the iframe, not disable the placement, so the
	 * opt-in that brings the container back brings the fallback with it.
	 *
	 * @covers ::gtmkit_the_noscript_tag
	 */
	public function test_the_template_placement_returns_when_a_staging_site_opts_in(): void {
		Functions\when( 'wp_get_environment_type' )->justReturn( 'staging' );

		$this->options(
			[
				'noscript_implementation' => 2,
				'load_on_non_production'  => true,
			]
		);

		ob_start();
		gtmkit_the_noscript_tag();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
	}

	/**
	 * A switched-off container places no iframe, whatever the placement is.
	 *
	 * The switch is enforced at registration: no hook is added, so neither
	 * placement can reach the callback. The template placement is not covered
	 * here because it does not go through registration at all.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 *
	 * @dataProvider data_every_placement
	 *
	 * @param int $placement The stored placement setting.
	 */
	public function test_a_switched_off_container_places_no_iframe( int $placement ): void {
		Actions\expectAdded( 'wp_body_open' )->never();
		Actions\expectAdded( 'wp_footer' )->never();
		Actions\expectAdded( 'body_footer' )->never();

		Frontend::register(
			$this->options(
				[
					'container_active'        => false,
					'noscript_implementation' => $placement,
				]
			)
		);

		self::assertTrue( true ); // assertion is the action expectation above.
	}

	/**
	 * Every placement the setting offers.
	 *
	 * @return array<string, array{0: int}>
	 */
	public static function data_every_placement(): array {
		return [
			'opening body tag' => [ 0 ],
			'footer'           => [ 1 ],
			'template call'    => [ 2 ],
			'disabled'         => [ 3 ],
		];
	}

	/**
	 * A second run of the callback in one request adds no second iframe.
	 *
	 * A theme is free to fire its footer hook more than once, and the
	 * registered callback would run each time. Two iframes read as two
	 * container loads, so the instance writes at most one.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_body_script
	 */
	public function test_a_repeated_call_writes_only_one_iframe(): void {
		$frontend = new Frontend( $this->options( [ 'noscript_implementation' => 1 ] ) );

		ob_start();
		$frontend->get_body_script();
		$frontend->get_body_script();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, '<iframe' ) );
	}

	/**
	 * A site with no container ID writes nothing.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::get_body_script
	 */
	public function test_no_container_id_writes_nothing(): void {
		$output = $this->body_script_output(
			$this->options(
				[
					'gtm_id'                  => '',
					'noscript_implementation' => 0,
				]
			)
		);

		$this->assertSame( '', $output );
	}

	/**
	 * A placement left behind as a string by an older release still resolves.
	 *
	 * Older installs can hold the setting as a numeric string. Those sites
	 * have to reach the same hook as an install storing a real integer.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\Frontend::register
	 */
	public function test_a_legacy_string_placement_behaves_like_its_integer(): void {
		Actions\expectAdded( 'wp_body_open' )->once();
		Actions\expectAdded( 'wp_footer' )->never();
		Actions\expectAdded( 'body_footer' )->never();

		Frontend::register( $this->options( [ 'noscript_implementation' => '0' ] ) );

		self::assertTrue( true ); // assertion is the action expectation above.
	}
}
