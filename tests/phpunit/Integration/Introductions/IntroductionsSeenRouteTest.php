<?php
/**
 * Integration tests for the introductions seen-state REST route.
 *
 * Covers:
 *
 *  - POST with a registered id returns 200 and marks the intro seen.
 *  - POST with an unknown id returns 400.
 *  - Requests without the `edit_posts` capability are rejected.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_Seen_Route}
 *  - {@see \TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_REST_Controller}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Introductions;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector;
use TLA_Media\GTM_Kit\Admin\Introductions\Application\Welcome_Introduction;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;
use TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_REST_Controller;
use TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_Seen_Route;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the `/gtmkit/v1/introductions/{id}/seen` route.
 */
final class IntroductionsSeenRouteTest extends WP_UnitTestCase {

	/**
	 * Editor user id used for authenticated requests.
	 *
	 * @var int
	 */
	private int $editor_id = 0;

	/**
	 * Subscriber user id used for the capability-rejection test.
	 *
	 * @var int
	 */
	private int $subscriber_id = 0;

	/**
	 * The repository under test.
	 *
	 * @var Introductions_Seen_Repository
	 */
	private Introductions_Seen_Repository $seen;

	/**
	 * Boot the REST server and register the route once per test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'gtmkit_introductions' );

		$this->editor_id     = self::factory()->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->seen = new Introductions_Seen_Repository();
		$collector  = new Introductions_Collector( $this->seen, [ new Welcome_Introduction() ] );
		$route      = new Introductions_Seen_Route( $this->seen, $collector );
		$controller = new Introductions_REST_Controller( new RestAPIServer(), $route );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global, not plugin-defined.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global, not plugin-defined.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing the WordPress core hook so the standard route registration path runs in tests.
		do_action( 'rest_api_init', $wp_rest_server );
		$controller->register_routes();
	}

	/**
	 * Tear down the REST server so each test starts clean.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global, not plugin-defined.
		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WordPress core global, not plugin-defined.
		parent::tear_down();
	}

	/**
	 * POST with a registered intro id returns 200 and marks the user as having seen the intro.
	 *
	 * @return void
	 */
	public function test_post_with_valid_id_returns_200_and_updates_meta(): void {
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'POST', '/gtmkit/v1/introductions/' . Welcome_Introduction::ID . '/seen' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( (bool) $data['success'] );

		$this->assertTrue( $this->seen->is_seen( $this->editor_id, Welcome_Introduction::ID ) );
	}

	/**
	 * POST with an unknown id returns 400 and does not write meta.
	 *
	 * @return void
	 */
	public function test_post_with_unknown_id_returns_400(): void {
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'POST', '/gtmkit/v1/introductions/not-a-real-id/seen' );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $this->seen->is_seen( $this->editor_id, 'not-a-real-id' ) );
	}

	/**
	 * Users without the `edit_posts` capability cannot mark intros seen.
	 *
	 * @return void
	 */
	public function test_request_without_edit_posts_is_rejected(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/gtmkit/v1/introductions/' . Welcome_Introduction::ID . '/seen' );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( $this->seen->is_seen( $this->subscriber_id, Welcome_Introduction::ID ) );
	}
}
