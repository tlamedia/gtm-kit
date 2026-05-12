<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\UI;

use TLA_Media\GTM_Kit\Common\RestAPIServer;

/**
 * Registers the introductions REST routes inside the existing `gtmkit/v1` namespace. Capability
 * matches the surface Yoast uses for the same kind of route so editors can dismiss their own
 * intros without elevated permissions.
 */
final class Introductions_REST_Controller {

	/**
	 * Shared REST server wrapper. Registers routes under `gtmkit/v1`.
	 *
	 * @var RestAPIServer
	 */
	private RestAPIServer $rest_api_server;

	/**
	 * Seen-route handler.
	 *
	 * @var Introductions_Seen_Route
	 */
	private Introductions_Seen_Route $seen_route;

	/**
	 * Constructor.
	 *
	 * @param RestAPIServer            $rest_api_server The REST server wrapper.
	 * @param Introductions_Seen_Route $seen_route The seen-route handler.
	 */
	public function __construct( RestAPIServer $rest_api_server, Introductions_Seen_Route $seen_route ) {
		$this->rest_api_server = $rest_api_server;
		$this->seen_route      = $seen_route;
	}

	/**
	 * Hook the route registration onto `rest_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		\add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->rest_api_server->register_rest_route(
			'/introductions/(?P<id>[a-z0-9-]+)/seen',
			[
				'methods'             => 'POST',
				'callback'            => [ $this->seen_route, 'handle' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'id' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Permission callback. Matches the surface used by Yoast: editors and up can dismiss their
	 * own intros without needing the elevated `manage_options` capability used by the default
	 * REST surface.
	 *
	 * @return bool
	 */
	public function permission_callback(): bool {
		return \current_user_can( 'edit_posts' );
	}
}
