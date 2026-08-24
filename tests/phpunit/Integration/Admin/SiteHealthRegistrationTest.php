<?php
/**
 * Integration test for the Site Health wiring.
 *
 * The result matrices are covered by the unit suite. What needs a booted
 * WordPress is the wiring itself: that the admin bootstrap registers the
 * integration, and that WordPress's own filters then hand back GTM Kit's
 * tests under the ids the Status screen renders.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\SiteHealth} as wired from
 * `gtmkit_admin_init()`.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use WP_UnitTestCase;

/**
 * Covers the registration of the Site Health tests and debug section.
 */
final class SiteHealthRegistrationTest extends WP_UnitTestCase {

	/**
	 * Run the admin bootstrap the way an admin request would.
	 *
	 * The stored version matches the running one so the bootstrap's upgrade
	 * branch stays out of the way.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'gtmkit_version', GTMKIT_VERSION );

		remove_all_filters( 'site_status_tests' );
		remove_all_filters( 'debug_information' );

		\TLA_Media\GTM_Kit\gtmkit_admin_init();
	}

	/**
	 * Both tests surface as direct tests under their expected ids.
	 */
	public function test_both_tests_are_registered_as_direct_tests(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$tests = apply_filters( 'site_status_tests', [] );

		$this->assertArrayHasKey( 'direct', $tests );
		$this->assertArrayHasKey( 'gtmkit_container', $tests['direct'] );
		$this->assertArrayHasKey( 'gtmkit_consent', $tests['direct'] );
		$this->assertIsCallable( $tests['direct']['gtmkit_container']['test'] );
		$this->assertIsCallable( $tests['direct']['gtmkit_consent']['test'] );
	}

	/**
	 * Running the registered callbacks yields results Site Health can render.
	 */
	public function test_registered_tests_return_renderable_results(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$tests = apply_filters( 'site_status_tests', [] );

		foreach ( [ 'gtmkit_container', 'gtmkit_consent' ] as $test_id ) {
			$result = call_user_func( $tests['direct'][ $test_id ]['test'] );

			$this->assertSame( $test_id, $result['test'] );
			$this->assertContains( $result['status'], [ 'good', 'recommended', 'critical' ] );
			$this->assertSame( 'GTM Kit', $result['badge']['label'] );
			$this->assertNotEmpty( $result['description'] );
		}
	}

	/**
	 * The debug section is registered with fields Site Health can render.
	 */
	public function test_debug_section_is_registered(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, applied here to read back what the plugin registered on it.
		$info = apply_filters( 'debug_information', [] );

		$this->assertArrayHasKey( 'gtmkit', $info );
		$this->assertSame( 'GTM Kit', $info['gtmkit']['label'] );
		$this->assertNotEmpty( $info['gtmkit']['fields'] );

		foreach ( $info['gtmkit']['fields'] as $field ) {
			$this->assertArrayHasKey( 'label', $field );
			$this->assertArrayHasKey( 'value', $field );
		}
	}
}
