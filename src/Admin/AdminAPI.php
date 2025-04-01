<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use TLA_Media\GTM_Kit\Options;
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
			'/set-notification-status',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'set_notification_status' ],
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
	 * @return void
	 */
	public function send_support_data(): void {
		$support_ticket = strtoupper( json_decode( file_get_contents( 'php://input' ), true ) );

		$match = preg_match( '/FS(\d+)-([A-Z0-9]+)/', $support_ticket, $matches );

		if ( $match === 1 ) {

			$url = 'https://support.gtmkit.com/api/wporg/support/' . $support_ticket;

			$body = [
				'system_data' => wp_json_encode( $this->util->get_site_data( $this->options->get_all_raw(), false ) ),
			];
			$args = [
				'method'  => 'PUT',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			];

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
			} else {
				wp_send_json_success( __( 'Thank you! We have received the data.', 'gtm-kit' ) );
			}
		} else {
			wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
		}
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
		return $input_raw ? json_decode( $input_raw, true ) : null;
	}
}
