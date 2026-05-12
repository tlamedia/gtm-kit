<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\UI;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Request handler for the seen-state route. The controller class wires this into the existing
 * `gtmkit/v1` REST namespace.
 */
final class Introductions_Seen_Route {

	/**
	 * Seen-state repository.
	 *
	 * @var Introductions_Seen_Repository
	 */
	private Introductions_Seen_Repository $seen;

	/**
	 * Collector used to look up known intro ids for validation.
	 *
	 * @var Introductions_Collector
	 */
	private Introductions_Collector $collector;

	/**
	 * Constructor.
	 *
	 * @param Introductions_Seen_Repository $seen The repository.
	 * @param Introductions_Collector       $collector The collector.
	 */
	public function __construct( Introductions_Seen_Repository $seen, Introductions_Collector $collector ) {
		$this->seen      = $seen;
		$this->collector = $collector;
	}

	/**
	 * Handle a seen-state POST. Returns 400 for unknown intro ids and 200 with `{ success: bool }`
	 * otherwise.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$intro_id = isset( $request['id'] ) ? (string) $request['id'] : '';

		if ( $intro_id === '' || ! in_array( $intro_id, $this->collector->get_registered_ids(), true ) ) {
			return new WP_Error(
				'gtmkit_introductions_unknown_id',
				\esc_html__( 'Unknown introduction id.', 'gtm-kit' ),
				[ 'status' => 400 ]
			);
		}

		$user_id = \get_current_user_id();
		$success = $this->seen->mark_seen( $user_id, $intro_id );

		return \rest_ensure_response( [ 'success' => $success ] );
	}
}
