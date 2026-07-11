<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Options\Options;

/**
 * Live support system-data sync.
 *
 * While a customer has shared system data with the support team, this class
 * keeps that data current: every completed settings save schedules a single
 * coalesced push of the same payload the manual share sends. The sync is
 * strictly session-scoped and fail-closed: it only ever runs against a
 * ticket the customer shared, it re-confirms the ticket is still open
 * before transmitting, it stops on any error, and it stops unconditionally
 * a fixed number of days after the first share. The customer can see the
 * active sync on the Support page and stop it with one click.
 */
final class SupportSync {

	/**
	 * The standalone option holding the sync session marker.
	 *
	 * Deliberately stored outside the `gtmkit` options array so the marker
	 * never appears in shared snapshots and never re-triggers the option
	 * update hook that schedules pushes.
	 *
	 * @var string
	 */
	public const OPTION = 'gtmkit_support_sync';

	/**
	 * The single-event cron hook that performs a coalesced push.
	 *
	 * @var string
	 */
	public const PUSH_HOOK = 'gtmkit_support_sync_push';

	/**
	 * Source label for pushes triggered by the customer on the Support page.
	 *
	 * @var string
	 */
	public const SOURCE_MANUAL = 'manual';

	/**
	 * Source label for automatic pushes triggered by settings saves.
	 *
	 * @var string
	 */
	public const SOURCE_AUTO = 'auto';

	/**
	 * Seconds to wait before pushing, so bursts of saves coalesce into one push.
	 *
	 * @var int
	 */
	private const COALESCE_DELAY = 60;

	/**
	 * Hard stop: seconds after the first share when the sync ends unconditionally (7 days).
	 *
	 * @var int
	 */
	private const SESSION_CAP = 604800;

	/**
	 * Seconds a confirmed-open status check stays valid before the next push re-checks (15 minutes).
	 *
	 * @var int
	 */
	private const STATUS_CHECK_INTERVAL = 900;

	/**
	 * Base URL of the support endpoint that receives system data.
	 *
	 * @var string
	 */
	private const ENDPOINT_BASE = 'https://support.gtmkit.com/api/wporg/support/';

	/**
	 * Timeout in seconds for the status check and push requests.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 10;

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
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Register the push-engine hooks.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 *
	 * @return void
	 */
	public static function register( Options $options, Util $util ): void {
		$instance = new self( $options, $util );

		add_action( 'update_option_gtmkit', [ $instance, 'schedule_push' ], 10, 0 );
		add_action( self::PUSH_HOOK, [ $instance, 'run_push' ] );
	}

	/**
	 * Get the support endpoint URL for a ticket.
	 *
	 * @param string $ticket The support ticket reference.
	 * @param string $suffix Optional path suffix, e.g. '/status'.
	 *
	 * @return string
	 */
	public static function get_endpoint( string $ticket, string $suffix = '' ): string {
		return self::ENDPOINT_BASE . rawurlencode( $ticket ) . $suffix;
	}

	/**
	 * Get the sync configuration.
	 *
	 * @return array<string, int> Keys: coalesce_delay, session_cap, status_check_interval (all seconds).
	 */
	public function get_config(): array {
		$defaults = [
			'coalesce_delay'        => self::COALESCE_DELAY,
			'session_cap'           => self::SESSION_CAP,
			'status_check_interval' => self::STATUS_CHECK_INTERVAL,
		];

		/**
		 * Filters the support sync timing configuration.
		 *
		 * @param array<string, int> $defaults The configuration: `coalesce_delay` (seconds
		 *                                     a scheduled push waits so bursts of saves
		 *                                     coalesce), `session_cap` (seconds after the
		 *                                     first share when the sync stops
		 *                                     unconditionally), and `status_check_interval`
		 *                                     (seconds a confirmed-open status check stays
		 *                                     valid before the next push re-checks).
		 */
		$config = apply_filters( 'gtmkit_support_sync_config', $defaults );

		if ( ! is_array( $config ) ) {
			return $defaults;
		}

		$sanitized = [];
		foreach ( $defaults as $key => $default_value ) {
			$sanitized[ $key ] = ( isset( $config[ $key ] ) && is_numeric( $config[ $key ] ) ) ? max( 0, (int) $config[ $key ] ) : $default_value;
		}

		return $sanitized;
	}

	/**
	 * Get the active sync session marker.
	 *
	 * @return array{ticket: string, first_shared_at: int, last_push_at: int, last_status_check_at: int}|null The marker, or null when no valid marker is stored.
	 */
	public function get_marker(): ?array {
		$marker = get_option( self::OPTION );

		if ( ! is_array( $marker ) || empty( $marker['ticket'] ) || ! is_string( $marker['ticket'] ) ) {
			return null;
		}

		foreach ( [ 'first_shared_at', 'last_push_at', 'last_status_check_at' ] as $key ) {
			if ( ! isset( $marker[ $key ] ) || ! is_numeric( $marker[ $key ] ) ) {
				return null;
			}
		}

		return [
			'ticket'               => $marker['ticket'],
			'first_shared_at'      => (int) $marker['first_shared_at'],
			'last_push_at'         => (int) $marker['last_push_at'],
			'last_status_check_at' => (int) $marker['last_status_check_at'],
		];
	}

	/**
	 * Start a sync session for a ticket.
	 *
	 * Called after a successful manual share, which doubles as the open
	 * confirmation: the endpoint only accepts data for open tickets. A new
	 * share overwrites any previous session (latest ticket wins).
	 *
	 * @param string $ticket The support ticket reference.
	 *
	 * @return void
	 */
	public function activate( string $ticket ): void {
		$now = time();

		$this->save_marker(
			[
				'ticket'               => $ticket,
				'first_shared_at'      => $now,
				'last_push_at'         => $now,
				'last_status_check_at' => $now,
			]
		);
	}

	/**
	 * End the sync session and unschedule any pending push.
	 *
	 * @return void
	 */
	public function clear_marker(): void {
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::PUSH_HOOK );
	}

	/**
	 * End the sync session only when it belongs to the given ticket.
	 *
	 * Used when a share attempt learns that a specific ticket is closed; a
	 * session for a different ticket is left untouched.
	 *
	 * @param string $ticket The support ticket reference.
	 *
	 * @return void
	 */
	public function clear_marker_for_ticket( string $ticket ): void {
		$marker = $this->get_marker();

		if ( $marker !== null && $marker['ticket'] === $ticket ) {
			$this->clear_marker();
		}
	}

	/**
	 * Schedule a coalesced push after a completed settings save.
	 *
	 * No-op without an active session or when a push is already scheduled,
	 * so any burst of saves results in exactly one push.
	 *
	 * @return void
	 */
	public function schedule_push(): void {
		$marker = $this->get_marker();

		if ( $marker === null ) {
			return;
		}

		if ( $this->is_expired( $marker ) ) {
			$this->clear_marker();
			return;
		}

		if ( wp_next_scheduled( self::PUSH_HOOK ) !== false ) {
			return;
		}

		wp_schedule_single_event( time() + $this->get_config()['coalesce_delay'], self::PUSH_HOOK );
	}

	/**
	 * Perform the scheduled push, fail-closed at every step.
	 *
	 * The session ends (and nothing is transmitted) unless the marker is
	 * valid and within the session cap, and the ticket is confirmed open.
	 * Any error response ends the session; the customer can always start a
	 * new one by sharing again.
	 *
	 * @return void
	 */
	public function run_push(): void {
		$marker = $this->get_marker();

		if ( $marker === null ) {
			return;
		}

		if ( $this->is_expired( $marker ) ) {
			$this->clear_marker();
			return;
		}

		$config = $this->get_config();

		if ( ( time() - $marker['last_status_check_at'] ) > $config['status_check_interval'] ) {
			if ( ! $this->ticket_is_open( $marker['ticket'] ) ) {
				$this->clear_marker();
				return;
			}

			$marker['last_status_check_at'] = time();
			$this->save_marker( $marker );
		}

		$response = wp_remote_request(
			self::get_endpoint( $marker['ticket'] ),
			[
				'method'  => 'PUT',
				'timeout' => self::REQUEST_TIMEOUT,
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $this->build_request_body( self::SOURCE_AUTO ) ),
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->clear_marker();
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) || empty( $body['success'] ) ) {
			$this->clear_marker();
			return;
		}

		$marker['last_push_at'] = time();
		$this->save_marker( $marker );
	}

	/**
	 * Build the request body shared by the manual share and the automatic push.
	 *
	 * Both paths transmit the identical system-data payload; only the source
	 * label differs.
	 *
	 * @param string $source One of the SOURCE_* constants.
	 *
	 * @return array<string, mixed>
	 */
	public function build_request_body( string $source ): array {
		return [
			'system_data' => wp_json_encode( $this->util->get_site_data( $this->options->get_all_raw(), false ) ),
			'source'      => $source,
		];
	}

	/**
	 * Get the sync state exposed to the settings app.
	 *
	 * @return array<string, mixed> `active` (bool), plus `ticket` and the
	 *                              localized `until` end date while a session
	 *                              is running.
	 */
	public function get_client_state(): array {
		$marker = $this->get_marker();

		if ( $marker === null || $this->is_expired( $marker ) ) {
			return [ 'active' => false ];
		}

		$date_format = get_option( 'date_format' );
		$date_format = is_string( $date_format ) && $date_format !== '' ? $date_format : 'Y-m-d';

		return [
			'active' => true,
			'ticket' => $marker['ticket'],
			'until'  => (string) wp_date( $date_format, $marker['first_shared_at'] + $this->get_config()['session_cap'] ),
		];
	}

	/**
	 * Whether a marker is past the unconditional session cap.
	 *
	 * @param array{ticket: string, first_shared_at: int, last_push_at: int, last_status_check_at: int} $marker The marker.
	 *
	 * @return bool
	 */
	private function is_expired( array $marker ): bool {
		return ( time() - $marker['first_shared_at'] ) > $this->get_config()['session_cap'];
	}

	/**
	 * Confirm with the support endpoint that a ticket is still open.
	 *
	 * Anything other than an explicit `open` confirmation (closed, an
	 * unexpected status code, a transport error) reports the ticket as not
	 * open, so data is never transmitted on uncertainty.
	 *
	 * @param string $ticket The support ticket reference.
	 *
	 * @return bool
	 */
	private function ticket_is_open( string $ticket ): bool {
		$response = wp_remote_get(
			self::get_endpoint( $ticket, '/status' ),
			[
				'timeout' => self::REQUEST_TIMEOUT,
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && isset( $body['status'] ) && $body['status'] === 'open';
	}

	/**
	 * Persist the session marker without autoloading it on every request.
	 *
	 * @param array{ticket: string, first_shared_at: int, last_push_at: int, last_status_check_at: int} $marker The marker.
	 *
	 * @return void
	 */
	private function save_marker( array $marker ): void {
		update_option( self::OPTION, $marker, false );
	}
}
