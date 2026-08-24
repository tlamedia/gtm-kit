<?php
/**
 * Unit tests for the site-kind resolver.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\SiteEnvironment}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\SiteEnvironment;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Tests for what WordPress reports about a site and what GTM Kit does with it.
 */
final class SiteEnvironmentTest extends TestCase {

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
	 * The address the stubbed `home_url` returns.
	 *
	 * @var string
	 */
	private string $home_url = 'https://example.com';

	/**
	 * Stub the WordPress functions the resolver touches.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		Functions\stubs(
			[
				'sanitize_key' => static fn( $key ) => strtolower( (string) $key ),
			]
		);

		Functions\when( 'is_plugin_active' )->alias( static fn() => false );
		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );
		Functions\when( 'home_url' )->alias( fn() => $this->home_url );
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
	 * Build an Options instance over a given `general` option group.
	 *
	 * @param array<string, mixed> $general The stored general options.
	 *
	 * @return Options The options under test.
	 */
	private function options( array $general = [] ): Options {
		$this->stored = [ 'general' => $general ];

		return new Options();
	}

	/**
	 * The resolver reports what WordPress reports, unchanged.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::get_type
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::is_production
	 */
	public function test_reports_what_wordpress_reports(): void {
		foreach ( [ 'production', 'staging', 'development', 'local' ] as $type ) {
			$this->environment = $type;

			$this->assertSame( $type, SiteEnvironment::get_type() );
			$this->assertSame( 'production' === $type, SiteEnvironment::is_production() );
		}
	}

	/**
	 * Every kind of site other than production withholds the container by default.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::suppresses_container
	 */
	public function test_non_production_withholds_the_container_by_default(): void {
		foreach ( SiteEnvironment::NON_PRODUCTION_TYPES as $type ) {
			$this->environment = $type;

			$this->assertTrue(
				SiteEnvironment::suppresses_container( $this->options() ),
				sprintf( 'A site reporting itself as %s must withhold the container.', $type )
			);
		}
	}

	/**
	 * The override loads the container on a site that is not production.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::suppresses_container
	 */
	public function test_override_loads_the_container_on_a_non_production_site(): void {
		foreach ( SiteEnvironment::NON_PRODUCTION_TYPES as $type ) {
			$this->environment = $type;

			$this->assertFalse(
				SiteEnvironment::suppresses_container( $this->options( [ 'load_on_non_production' => true ] ) ),
				sprintf( 'The override must load the container on a site reporting itself as %s.', $type )
			);
		}
	}

	/**
	 * A production site is never withheld, with or without the override.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::suppresses_container
	 */
	public function test_production_is_never_withheld(): void {
		$this->environment = 'production';

		$this->assertFalse( SiteEnvironment::suppresses_container( $this->options() ) );
		$this->assertFalse( SiteEnvironment::suppresses_container( $this->options( [ 'load_on_non_production' => true ] ) ) );
	}

	/**
	 * A site that has said nothing has not declared what it is.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::is_declared
	 */
	public function test_a_silent_site_has_not_declared_what_it_is(): void {
		$this->assertFalse( SiteEnvironment::is_declared() );
	}

	/**
	 * The environment variable counts as a declaration, as it does in WordPress.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::is_declared
	 */
	public function test_the_environment_variable_counts_as_a_declaration(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Setting the variable is the only way to reproduce a site that declares itself through the environment rather than a constant; it is unset again below.
		putenv( 'WP_ENVIRONMENT_TYPE=production' );

		try {
			$this->assertTrue( SiteEnvironment::is_declared() );
		} finally {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restores the process to the state the test found it in.
			putenv( 'WP_ENVIRONMENT_TYPE' );
		}
	}

	/**
	 * Addresses that read like a copy are recognised; ordinary ones are not.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::url_looks_like_a_copy
	 */
	public function test_recognises_addresses_that_read_like_a_copy(): void {
		$copies = [
			'https://staging.example.com',
			'https://example-staging.com',
			'https://dev.example.com',
			'https://uat.shop.example',
			'https://example.local',
			'https://example.test',
		];

		foreach ( $copies as $url ) {
			$this->assertTrue(
				SiteEnvironment::url_looks_like_a_copy( $url ),
				sprintf( '%s should read like a copy.', $url )
			);
		}

		$real = [
			'https://example.com',
			// A whole-label match keeps ordinary words that merely contain a
			// listed fragment from being mistaken for a copy.
			'https://devon-bakery.com',
			'https://testkitchen.com',
			'https://stagecoach.co.uk',
		];

		foreach ( $real as $url ) {
			$this->assertFalse(
				SiteEnvironment::url_looks_like_a_copy( $url ),
				sprintf( '%s should not read like a copy.', $url )
			);
		}
	}

	/**
	 * The address falls back to the site's own when none is given.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::url_looks_like_a_copy
	 */
	public function test_falls_back_to_the_sites_own_address(): void {
		$this->home_url = 'https://staging.example.com';
		$this->assertTrue( SiteEnvironment::url_looks_like_a_copy() );

		$this->home_url = 'https://example.com';
		$this->assertFalse( SiteEnvironment::url_looks_like_a_copy() );
	}

	/**
	 * The list of address fragments can be filtered.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\SiteEnvironment::url_looks_like_a_copy
	 */
	public function test_the_address_fragments_can_be_filtered(): void {
		Filters\expectApplied( 'gtmkit_copy_site_host_labels' )
			->andReturn( [ 'kopi' ] );

		$this->assertTrue( SiteEnvironment::url_looks_like_a_copy( 'https://kopi.example.com' ) );
	}
}
