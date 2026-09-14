<?php
/**
 * Integration tests for the consent management platform plugin
 * detection helper.
 *
 * Mocks the WP active-plugins option so tests do not depend on real
 * CMP plugin packages being present in the test environment. Each
 * test asserts the helper resolves the expected slug for a given
 * active-plugins list, including the multi-active edge case where the
 * iteration order yields a deterministic first match.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\CMPDetection}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use WP_UnitTestCase;

/**
 * Covers plugin-list-driven CMP detection.
 */
final class CMPDetectionTest extends WP_UnitTestCase {

	/**
	 * Original active_plugins value, restored in tear_down.
	 *
	 * @var array<int, string>|null
	 */
	private $original_active_plugins;

	/**
	 * Stash the real active-plugins list and start each test from a
	 * known-empty state.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_active_plugins = get_option( 'active_plugins', [] );
		update_option( 'active_plugins', [] );
	}

	/**
	 * Restore the active-plugins list so other tests are not affected.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		update_option( 'active_plugins', $this->original_active_plugins ?? [] );
		parent::tear_down();
	}

	/**
	 * Returns null when no known CMP plugin is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_null_when_no_known_cmp_is_active(): void {
		update_option( 'active_plugins', [ 'some-other-plugin/some-other-plugin.php' ] );

		$this->assertNull( CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookiebot' when the canonical Cookiebot plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookiebot_for_canonical_slug(): void {
		update_option( 'active_plugins', [ 'cookiebot/cookiebot.php' ] );

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookiebot' for the alternate `cookiebot-by-cybot` slug.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookiebot_for_alternate_slug(): void {
		update_option(
			'active_plugins',
			[ 'cookiebot-by-cybot/cookiebot.php' ]
		);

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'iubenda' when the Iubenda plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_iubenda_when_plugin_active(): void {
		update_option(
			'active_plugins',
			[ 'iubenda-cookie-law-solution/iubenda_cookie_solution.php' ]
		);

		$this->assertSame( 'iubenda', CMPDetection::detect_active_cmp() );
	}

	/**
	 * Returns 'cookieyes' when the CookieYes plugin slug is active.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_cookieyes_when_plugin_active(): void {
		update_option(
			'active_plugins',
			[ 'cookie-law-info/cookie-law-info.php' ]
		);

		$this->assertSame( 'cookieyes', CMPDetection::detect_active_cmp() );
	}

	/**
	 * When more than one CMP plugin is active simultaneously (rare and
	 * misconfigured), the helper returns the first match in canonical
	 * order so callers get deterministic behavior.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_returns_first_match_when_multiple_cmps_active(): void {
		update_option(
			'active_plugins',
			[
				'cookie-law-info/cookie-law-info.php',
				'cookiebot/cookiebot.php',
				'iubenda-cookie-law-solution/iubenda_cookie_solution.php',
			]
		);

		$this->assertSame( 'cookiebot', CMPDetection::detect_active_cmp() );
	}

	/**
	 * A site can declare a consent platform that plugin detection cannot see.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_filter_declares_an_undetected_cmp(): void {
		add_filter( 'gtmkit_active_cmp', static fn() => 'tarteaucitron' );

		$this->assertSame( 'tarteaucitron', CMPDetection::detect_active_cmp() );
	}

	/**
	 * The filter receives the detected slug and can override it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 */
	public function test_filter_receives_and_overrides_the_detected_cmp(): void {
		update_option( 'active_plugins', [ 'cookiebot/cookiebot.php' ] );

		$received = 'not called';
		add_filter(
			'gtmkit_active_cmp',
			static function ( $detected ) use ( &$received ) {
				$received = $detected;

				return null;
			}
		);

		$this->assertNull( CMPDetection::detect_active_cmp() );
		$this->assertSame( 'cookiebot', $received );
	}

	/**
	 * An empty string from the filter means no consent platform, so no caller
	 * can mistake it for a detected one with a blank name.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::detect_active_cmp
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::get_display_name
	 */
	public function test_filter_returning_an_empty_string_is_no_cmp(): void {
		update_option( 'active_plugins', [ 'cookiebot/cookiebot.php' ] );
		add_filter( 'gtmkit_active_cmp', '__return_empty_string' );

		$detected = CMPDetection::detect_active_cmp();

		$this->assertNull( $detected );
		$this->assertSame( '', CMPDetection::get_display_name( $detected ) );
	}

	/**
	 * The display-name filter names a declared platform instead of its slug.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::get_display_name
	 */
	public function test_display_name_filter_names_a_declared_cmp(): void {
		$this->assertSame( 'tarteaucitron', CMPDetection::get_display_name( 'tarteaucitron' ) );

		add_filter(
			'gtmkit_cmp_display_name',
			static fn( $name, $slug ) => ( 'tarteaucitron' === $slug ) ? 'tarteaucitron.js' : $name,
			10,
			2
		);

		$this->assertSame( 'tarteaucitron.js', CMPDetection::get_display_name( 'tarteaucitron' ) );
		$this->assertSame( 'Cookiebot', CMPDetection::get_display_name( 'cookiebot' ) );
	}

	/**
	 * An empty display name from the filter keeps the default name.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::get_display_name
	 */
	public function test_display_name_filter_cannot_blank_a_detected_cmp(): void {
		add_filter( 'gtmkit_cmp_display_name', '__return_empty_string' );

		$this->assertSame( 'Iubenda', CMPDetection::get_display_name( 'iubenda' ) );
	}

	/**
	 * With no filters, the display names are unchanged.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\CMPDetection::get_display_name
	 */
	public function test_display_names_are_unchanged_without_filters(): void {
		$this->assertSame( 'Cookiebot', CMPDetection::get_display_name( 'cookiebot' ) );
		$this->assertSame( 'CookieYes', CMPDetection::get_display_name( 'cookieyes' ) );
		$this->assertSame( '', CMPDetection::get_display_name( null ) );
	}
}
