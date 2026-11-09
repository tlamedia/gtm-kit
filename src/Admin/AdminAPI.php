<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Common\StapeLoaderClient;
use TLA_Media\GTM_Kit\Common\SupportSync;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\SettingsWriter;
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

		$this->util->rest_api_server->register_rest_route(
			'/health',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'health' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/sgtm-loader-refresh',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'refresh_sgtm_loader' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/sgtm-loader-paste',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'paste_sgtm_loader' ],
			]
		);
	}

	/**
	 * Ask Stape for the loader it issues, on the site owner's request.
	 *
	 * @return \WP_REST_Response
	 */
	public function refresh_sgtm_loader(): \WP_REST_Response {
		$sgtm_loader = new StapeLoader( $this->options );
		$outcome     = $sgtm_loader->refresh( new StapeLoaderClient( $this->options ) );

		return self::envelope( true, array_merge( $outcome, $sgtm_loader->get_client_state() ) );
	}

	/**
	 * Store a loader from the code the site owner copied out of Stape.
	 *
	 * The code goes through the same parser as an API response and is never
	 * stored or printed as it is.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function paste_sgtm_loader( \WP_REST_Request $request ): \WP_REST_Response {
		$submitted   = json_decode( $request->get_body(), true );
		$code        = ( is_array( $submitted ) && isset( $submitted['code'] ) && is_string( $submitted['code'] ) ) ? $submitted['code'] : '';
		$sgtm_loader = new StapeLoader( $this->options );
		$outcome     = $sgtm_loader->paste( $code );

		return self::envelope( true, array_merge( $outcome, $sgtm_loader->get_client_state() ) );
	}

	/**
	 * Answer the Site Health reachability check.
	 *
	 * Deliberately does nothing: the check only needs proof that a request
	 * through the same method, namespace and permission check as a settings
	 * save reached this code.
	 *
	 * @return array{reachable: bool}
	 */
	public function health(): array {
		return [ 'reachable' => true ];
	}

	/**
	 * Explain why the Google tag gateway could not be switched on.
	 *
	 * @param string $reason One of the gateway processor's refusal reasons.
	 *
	 * @return string
	 */
	public static function get_gateway_refusal_message( string $reason ): string {
		return SettingsWriter::get_gateway_refusal_message( $reason );
	}

	/**
	 * Set options
	 *
	 * The settings app adopts the response as the new saved state. If the
	 * save was rejected by validation, or the values read back after the
	 * write are not the values written, adopting them would quietly undo the
	 * change on screen, so both cases are answered with an error instead. A
	 * save that switches on a Google tag gateway that cannot serve is refused
	 * the same way, for the same reason.
	 *
	 * The save itself is {@see SettingsWriter::apply()}, which every other
	 * route that changes settings shares.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function set_options( \WP_REST_Request $request ) {
		$saved = ( new SettingsWriter( $this->options ) )->apply( json_decode( $request->get_body(), true ) );

		if ( $saved instanceof WP_Error ) {
			return $saved;
		}

		return new \WP_REST_Response(
			[
				'success'     => true,
				'data'        => $saved['data'],
				'sgtm_loader' => $saved['sgtm_loader'],
			]
		);
	}

	/**
	 * Find the submitted options whose written value did not survive the read-back.
	 *
	 * @param array<string, mixed> $submitted The options the request carried.
	 * @param array<string, mixed> $written The options WordPress stored.
	 * @param array<string, mixed> $persisted The options as read back.
	 *
	 * @return array<int, string> The `group.key` of each option not kept.
	 */
	public static function find_options_not_kept( array $submitted, array $written, array $persisted ): array {
		return SettingsWriter::find_options_not_kept( $submitted, $written, $persisted );
	}

	/**
	 * Send Support Data
	 *
	 * A successful share also starts the live sync session, so later
	 * settings saves keep the shared data current while the ticket stays
	 * open.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function send_support_data( \WP_REST_Request $request ): \WP_REST_Response {
		$input          = json_decode( $request->get_body(), true );
		$support_ticket = is_string( $input ) ? strtoupper( $input ) : '';
		$not_found      = __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' );

		if ( preg_match( '/FS(\d+)-([A-Z0-9]+)/', $support_ticket ) !== 1 ) {
			return self::envelope( false, $not_found );
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

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		// No answer, or a server error: nothing was learned about the ticket,
		// so this is reported as the sending itself not working. The settings
		// app uses the reason to offer the system data another way.
		if ( 0 === $code || $code >= 500 ) {
			return self::envelope(
				false,
				[
					'message' => __( 'Your site could not reach the GTM Kit support server, so your system data was not sent.', 'gtm-kit' ),
					'reason'  => 'unreachable',
				]
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['success'] ) ) {
			$support_sync->activate( $support_ticket );

			return self::envelope(
				true,
				[
					'message'     => __( 'Thank you! We have received the data.', 'gtm-kit' ),
					'supportSync' => $support_sync->get_client_state(),
				]
			);
		}

		if ( $code === 410 ) {
			$support_sync->clear_marker_for_ticket( $support_ticket );
			return self::envelope( false, __( 'This support ticket is closed. If you still need to share your system data, ask the support team for a new ticket.', 'gtm-kit' ) );
		}

		return self::envelope( false, $not_found );
	}

	/**
	 * Wrap a result in the `{ success, data }` body the settings app reads.
	 *
	 * @param bool  $success Whether the action succeeded.
	 * @param mixed $data The result, or the error message or details.
	 *
	 * @return \WP_REST_Response
	 */
	private static function envelope( bool $success, $data ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'success' => $success,
				'data'    => $data,
			]
		);
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

						// A notice that only exists while the site is in a
						// particular situation cannot leave its dismissal to
						// the notifications system: that record covers only
						// notifications currently in the set, and a notice
						// held back by its own dismissal is not one of them.
						// It keeps the timestamp itself instead.
						if ( $notification_action && Suggestions::is_upgrade_notice( $notification_id ) ) {
							PremiumTriggerCooldown::record( $notification_id );
						}

						// The gateway fallback notice is conditional in the
						// same way, and keeps its own record for the same
						// reason. Its dismissal is cleared once the gateway
						// serves again, so it answers this outage only.
						if ( $notification_action && $notification_id === GoogleTagGatewayNotice::NOTIFICATION_ID ) {
							GoogleTagGatewayHealth::dismiss_notice( GoogleTagGatewayHealth::NOTICE_FALLBACK );
						}

						// The notice about a custom sGTM domain keeps a record
						// of its own, so dismissing one gateway notice never
						// silences the other.
						if ( $notification_action && $notification_id === GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) {
							GoogleTagGatewayHealth::dismiss_notice( GoogleTagGatewayHealth::NOTICE_BLOCKED );
						}

						// The Stape loader notice is conditional in the same
						// way and keeps its own record until the site has a
						// loader again.
						if ( $notification_action && $notification_id === StapeLoaderNotice::NOTIFICATION_ID ) {
							StapeLoaderNotice::dismiss();
						}
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
