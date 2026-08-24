<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\SupportSync;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use WP_Error;

/**
 * Class for the admin REST API.
 */
final class AdminAPI {

	/**
	 * An instance of Options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * An instance of Util.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
	 * Constructor
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Initialize REST
	 *
	 * @return void
	 */
	public function rest_init() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}


	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$this->util->rest_api_server->register_rest_route(
			'/set-options',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'set_options' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/send-support-data',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'send_support_data' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/stop-support-sync',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'stop_support_sync' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/set-notification-status',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'set_notification_status' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/generate_template',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'generate_template' ],
			]
		);
	}

	/**
	 * Set options
	 *
	 * @return void
	 */
	public function set_options(): void {
		$new_options = json_decode( file_get_contents( 'php://input' ), true );
		$this->options->set( $new_options );

		wp_send_json_success( $this->options->get_all_raw() );
	}

	/**
	 * Send Support Data
	 *
	 * A successful share also starts the live sync session, so later
	 * settings saves keep the shared data current while the ticket stays
	 * open.
	 *
	 * @return void
	 */
	public function send_support_data(): void {
		$input          = json_decode( (string) file_get_contents( 'php://input' ), true );
		$support_ticket = is_string( $input ) ? strtoupper( $input ) : '';

		if ( preg_match( '/FS(\d+)-([A-Z0-9]+)/', $support_ticket ) !== 1 ) {
			wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
		}

		$support_sync = new SupportSync( $this->options, $this->util );

		$response = wp_remote_request(
			SupportSync::get_endpoint( $support_ticket ),
			[
				'method'  => 'PUT',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $support_sync->build_request_body( SupportSync::SOURCE_MANUAL ) ),
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['success'] ) ) {
			$support_sync->activate( $support_ticket );

			wp_send_json_success(
				[
					'message'     => __( 'Thank you! We have received the data.', 'gtm-kit' ),
					'supportSync' => $support_sync->get_client_state(),
				]
			);
		}

		if ( $code === 410 ) {
			$support_sync->clear_marker_for_ticket( $support_ticket );
			wp_send_json_error( __( 'This support ticket is closed. If you still need to share your system data, ask the support team for a new ticket.', 'gtm-kit' ) );
		}

		wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
	}

	/**
	 * Stop the live support sync session.
	 *
	 * @return void
	 */
	public function stop_support_sync(): void {
		$support_sync = new SupportSync( $this->options, $this->util );
		$support_sync->clear_marker();

		wp_send_json_success(
			[
				'supportSync' => $support_sync->get_client_state(),
			]
		);
	}

	/**
	 * Set notification status
	 *
	 * @return void
	 */
	public function set_notification_status(): void {
		$input = $this->get_json_input();

		if ( $this->validate_notification_input( $input ) ) {
			$notification_id = sanitize_text_field( $input['notification-id'] );
			$action          = sanitize_text_field( $input['action'] );

			$notifications_handler = NotificationsHandler::get();
			$notifications_handler->setup_current_notifications();
			$notification = $notifications_handler->get_notification_by_id( $notification_id );

			if ( $action === 'remove' ) {
				$notifications_handler->remove_notification_by_id( $notification_id );
				wp_send_json_success( (object) $notifications_handler->get_notifications_array() );
			} elseif ( $notification instanceof Notification ) {
				switch ( $action ) {
					case 'dismiss':
						$notification_action = $notifications_handler->maybe_dismiss_notification( $notification );
						break;
					case 'restore':
						$notification_action = $notifications_handler->restore_notification( $notification );
						break;
					default:
						$notification_action = false;
				}

				if ( $notification_action ) {
					wp_send_json_success( (object) $notifications_handler->get_notifications_array() );
				} else {
					wp_send_json_error( (object) $notifications_handler->get_notifications_array() );
				}
			} else {
				wp_send_json_error( (object) $notifications_handler->get_notifications_array() ); // The notification was not found.
			}
		} else {
			wp_send_json_error( 'Invalid input.' );
		}
	}

	/**
	 * Generate GTM template based on user selections
	 *
	 * @return \WP_REST_Response
	 */
	public function generate_template(): \WP_REST_Response {
		$data = $this->get_json_input();

		// Validate input exists.
		if ( ! $data ) {
			return new \WP_REST_Response(
				[ 'error' => __( 'Invalid input data.', 'gtm-kit' ) ],
				400
			);
		}

		// Sanitize and validate selectedServices (array of strings).
		$selected_services = [];
		if ( isset( $data['selectedServices'] ) && is_array( $data['selectedServices'] ) ) {
			foreach ( $data['selectedServices'] as $service ) {
				$sanitized_service = sanitize_text_field( $service );
				if ( ! empty( $sanitized_service ) ) {
					$selected_services[] = $sanitized_service;
				}
			}
		}

		// Sanitize and validate serviceConfigs (nested array).
		$configurations = [];
		if ( isset( $data['serviceConfigs'] ) && is_array( $data['serviceConfigs'] ) ) {
			foreach ( $data['serviceConfigs'] as $service_id => $config ) {
				$sanitized_service_id = sanitize_text_field( $service_id );
				if ( is_array( $config ) ) {
					$configurations[ $sanitized_service_id ] = [];
					foreach ( $config as $key => $value ) {
						$sanitized_key   = sanitize_text_field( $key );
						$sanitized_value = sanitize_text_field( $value );
						$configurations[ $sanitized_service_id ][ $sanitized_key ] = $sanitized_value;
					}
				}
			}
		}

		// Sanitize and validate gtmType (only allow specific values).
		$gtm_type = isset( $data['gtmType'] ) ? sanitize_text_field( $data['gtmType'] ) : 'web';
		if ( ! in_array( $gtm_type, [ 'web', 'standard', 'server-side' ], true ) ) {
			$gtm_type = 'web';
		}

		$ecommerce = isset( $data['ecommerce'] ) ? (bool) $data['ecommerce'] : false;

		$template = [
			'selectedServices' => $selected_services,
			'configurations'   => $configurations,
			'gtmType'          => $gtm_type,
			'ecommerce'        => $ecommerce,
		];

		// Return as JSON download.
		$response = new \WP_REST_Response( $template );
		$response->header( 'Content-Type', 'application/json' );
		$response->header( 'Content-Disposition', 'attachment; filename="gtm-template.json"' );

		return $response;
	}

	/**
	 * Validate notification input
	 *
	 * @param array<string, string>|null $input The input.
	 * @return bool
	 */
	private function validate_notification_input( ?array $input ): bool {
		return isset( $input['notification-id'], $input['action'] )
				&& in_array( $input['action'], [ 'dismiss', 'restore', 'remove' ], true );
	}

	/**
	 * Get JSON input
	 *
	 * @return array<string, string>|null
	 */
	private function get_json_input(): ?array {
		$input_raw = file_get_contents( 'php://input' );
		if ( ! $input_raw ) {
			return null;
		}
		$decoded = json_decode( $input_raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}
}
