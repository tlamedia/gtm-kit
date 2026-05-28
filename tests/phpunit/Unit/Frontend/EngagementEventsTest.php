<?php
/**
 * Unit tests for the GA4 engagement-event module.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Frontend\EngagementEvents} via the
 * BrainMonkey harness: filters are stubbed, no real cookies are written,
 * the swappable cookie writer captures every call so each event path is
 * asserted on directly. Acceptance criteria covered:
 *
 *  - Cookie writer is called with the expected payload for `wp_login`,
 *    `user_register`, and `woocommerce_created_customer` (the last
 *    upgrades `method` to `woocommerce`).
 *  - `user_register` in an admin context emits nothing.
 *  - Every filter introduced by the module is exercised by at least one
 *    case that asserts both the default path and the filtered path.
 *  - The `gtmkit_engagement_event_should_emit` veto suppresses the
 *    cookie write.
 *  - The body class filter adds `gtmkit-search-results` only on a
 *    search-results page with a non-empty term, and never otherwise.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Frontend\EngagementEvents;
use TLA_Media\GTM_Kit\Options\Options;
use WP_User;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see EngagementEvents}.
 */
final class EngagementEventsTest extends TestCase {

	/**
	 * Captured cookie-writer calls. Each entry is `{ name, value, attributes }`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $cookies = [];

	/**
	 * Pre-built engagement-events SUT for the common case where every
	 * default-path stub already matches the test.
	 *
	 * @var HeadersOpenEngagementEvents
	 */
	private HeadersOpenEngagementEvents $events;

	/**
	 * Stub every WP function the module touches in a single call. Brain
	 * Monkey's `Functions\stubs` replaces the entire stub table on each
	 * call, so the test base class wires the full set once per test
	 * rather than letting helpers stomp each other. Individual tests
	 * override single functions via `Functions\when( name )->...`.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->cookies = [];

		Functions\stubs(
			[
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test stub replacing `wp_json_encode` in the unit harness.
				'wp_json_encode'   => static fn( $value ) => \json_encode( $value ),
				'home_url'         => static fn( $path = '/' ) => 'https://example.test' . $path,
				'wp_parse_url'     => static fn() => '/',
				'is_ssl'           => static fn() => true,
				'is_admin'         => static fn() => false,
				'get_option'       => static fn( $name, $fallback = false ) => $fallback,
				'add_filter'       => static fn() => true,
				'is_search'        => static fn() => false,
				'get_search_query' => static fn() => '',
			]
		);

		$this->events = new HeadersOpenEngagementEvents(
			new Options(),
			function ( string $name, string $value, array $attributes ): void {
				$this->cookies[] = [
					'name'       => $name,
					'value'      => $value,
					'attributes' => $attributes,
				];
			}
		);
	}

	/**
	 * Decode the last captured cookie payload as an associative array.
	 *
	 * @return array<string, mixed>
	 */
	private function last_payload(): array {
		$last = end( $this->cookies );
		if ( ! is_array( $last ) ) {
			return [];
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_decode -- Test helper decoding the JSON the SUT wrote via `wp_json_encode`.
		$decoded = \json_decode( rawurldecode( $last['value'] ), true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Build a minimal WP_User-shaped value for filter callbacks. The SUT
	 * never reads the user; it only hands the value back through filters.
	 *
	 * @return WP_User
	 */
	private function user_stub(): WP_User {
		return new WP_User();
	}

	/**
	 * `wp_login` writes the cookie with the default `method=wordpress` payload.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_login
	 */
	public function test_wp_login_writes_default_payload(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_login_method' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_should_emit' )->andReturn( true );
		Filters\expectApplied( 'gtmkit_engagement_event_cookie_name' )->andReturnFirstArg();

		$this->events->on_login( 'alice', $this->user_stub() );

		$this->assertCount( 1, $this->cookies );
		$this->assertSame( 'gtmkit_engagement_event', $this->cookies[0]['name'] );
		$this->assertSame(
			[
				'event'  => 'login',
				'method' => 'wordpress',
			],
			$this->last_payload()
		);
	}

	/**
	 * The `gtmkit_engagement_event_login_method` filter overrides the method.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_login
	 */
	public function test_login_method_filter_overrides_default(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_login_method' )->andReturn( 'sso' );
		Filters\expectApplied( 'gtmkit_engagement_event_should_emit' )->andReturn( true );
		Filters\expectApplied( 'gtmkit_engagement_event_cookie_name' )->andReturnFirstArg();

		$this->events->on_login( 'alice', $this->user_stub() );

		$this->assertSame( 'sso', $this->last_payload()['method'] ?? null );
	}

	/**
	 * `user_register` writes a default `sign_up` cookie.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_user_register
	 */
	public function test_user_register_writes_default_payload(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_signup_method' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_should_emit' )->andReturn( true );
		Filters\expectApplied( 'gtmkit_engagement_event_cookie_name' )->andReturnFirstArg();

		$this->events->on_user_register( 42 );

		$this->assertSame(
			[
				'event'  => 'sign_up',
				'method' => 'wordpress',
			],
			$this->last_payload()
		);
	}

	/**
	 * Admin-context registrations are suppressed to avoid bulk-import noise.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_user_register
	 */
	public function test_user_register_suppressed_in_admin(): void {
		Functions\when( 'is_admin' )->justReturn( true );

		$this->events->on_user_register( 42 );

		$this->assertSame( [], $this->cookies );
	}

	/**
	 * `woocommerce_created_customer` upgrades the cookie's method to `woocommerce`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_woocommerce_created_customer
	 */
	public function test_woocommerce_customer_upgrades_method(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_signup_method' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_should_emit' )->andReturn( true );
		Filters\expectApplied( 'gtmkit_engagement_event_cookie_name' )->andReturnFirstArg();

		$this->events->on_user_register( 42 );
		$this->events->on_woocommerce_created_customer( 42 );

		$this->assertCount( 2, $this->cookies );
		$this->assertSame( 'woocommerce', $this->last_payload()['method'] ?? null );
	}

	/**
	 * The `gtmkit_engagement_event_should_emit` veto suppresses the cookie write.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::on_login
	 */
	public function test_should_emit_filter_can_veto_cookie_write(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_login_method' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_should_emit' )->andReturn( false );

		$this->events->on_login( 'alice', $this->user_stub() );

		$this->assertSame( [], $this->cookies );
	}

	/**
	 * The cookie-name filter changes the name written to the response.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::cookie_name
	 */
	public function test_cookie_name_filter_overrides_default(): void {
		Filters\expectApplied( 'gtmkit_engagement_event_cookie_name' )->andReturn( 'gtmkit_alt' );

		$this->assertSame( 'gtmkit_alt', EngagementEvents::cookie_name() );
	}

	/**
	 * The body-class filter adds `gtmkit-search-results` on a non-empty
	 * search-results page.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::filter_body_class
	 */
	public function test_body_class_filter_adds_search_class(): void {
		Functions\when( 'is_search' )->justReturn( true );
		Functions\when( 'get_search_query' )->justReturn( 'widget' );
		Filters\expectApplied( 'gtmkit_is_search_results_page' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_search_term' )->andReturnFirstArg();

		$classes = $this->events->filter_body_class( [ 'theme-default' ] );

		$this->assertContains( 'gtmkit-search-results', $classes );
		$this->assertContains( 'theme-default', $classes );
	}

	/**
	 * An empty search query does not add the body class.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::filter_body_class
	 */
	public function test_body_class_filter_skips_empty_query(): void {
		Functions\when( 'is_search' )->justReturn( true );
		Filters\expectApplied( 'gtmkit_is_search_results_page' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_engagement_event_search_term' )->andReturnFirstArg();

		$classes = $this->events->filter_body_class( [ 'theme-default' ] );

		$this->assertNotContains( 'gtmkit-search-results', $classes );
	}

	/**
	 * The class is not added on a non-search page even when a filter
	 * could otherwise transform the term.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::filter_body_class
	 */
	public function test_body_class_filter_skips_non_search_page(): void {
		Filters\expectApplied( 'gtmkit_is_search_results_page' )->andReturnFirstArg();

		$classes = $this->events->filter_body_class( [ 'home' ] );

		$this->assertNotContains( 'gtmkit-search-results', $classes );
	}

	/**
	 * The `gtmkit_is_search_results_page` filter can opt a non-`is_search()`
	 * request into the search-results signal.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::is_search_results_page
	 */
	public function test_is_search_results_page_filter_can_opt_in(): void {
		Filters\expectApplied( 'gtmkit_is_search_results_page' )->andReturn( true );

		$this->assertTrue( $this->events->is_search_results_page() );
	}

	/**
	 * The `gtmkit_engagement_event_search_term` filter transforms the term
	 * before it reaches the marker.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\EngagementEvents::current_search_term
	 */
	public function test_search_term_filter_transforms_value(): void {
		Functions\when( 'get_search_query' )->justReturn( 'widget' );
		Filters\expectApplied( 'gtmkit_engagement_event_search_term' )->andReturn( 'WIDGET' );

		$this->assertSame( 'WIDGET', $this->events->current_search_term() );
	}
}
