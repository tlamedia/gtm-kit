<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use WP_Error;

/**
 * Class for REST API
 */
final class RestAPIServer {

	/**
	 * Route namespace.
	 *
	 * @var string
	 */
	private string $route_namespace = 'gtmkit/v1';

	/**
	 * Permission callback
	 *
	 * @return true|WP_Error
	 */
	public function permission_callback() {
		$capability = \apply_filters( 'gtmkit_admin_capability', \is_multisite() ? 'manage_network_options' : 'manage_options' );

		if ( ! \current_user_can( $capability ) ) {
			return new WP_Error( 'rest_forbidden', \esc_html__( 'Only authenticated users can access endpoint.', 'gtm-kit' ), [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Register REST route
	 *
	 * @param string               $route The route.
	 * @param array<string, mixed> $args The arguments.
	 *
	 * @return void
	 */
	public function register_rest_route( string $route, array $args ): void {
		if ( ! isset( $args['permissions_callback'] ) ) {
			$args['permission_callback'] = [ $this, 'permission_callback' ];
		}

		\register_rest_route( $this->route_namespace, $route, $args );
	}
}
