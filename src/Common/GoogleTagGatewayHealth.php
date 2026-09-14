<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;

/**
 * Runs and stores the two checks the Google tag gateway depends on.
 *
 * Serving the container from this site's own origin needs two things that are
 * true on most hosts and absent on some, and neither of them fails loudly:
 *
 * - the server can open an outbound HTTPS connection to the gateway service,
 *   which locked-down hosts block;
 * - the proxy serves the tag over the web, which hosts that forbid direct PHP
 *   execution inside the plugin directory, and firewalls that filter it, block.
 *
 * When either is missing the gateway cannot serve the tag, and a site owner
 * who was not told would see tracking simply stop. So the checks run before
 * the setting can be switched on and once a day afterwards, and their stored
 * result is what decides whether the gateway serves a given request.
 *
 * The requests go through the proxy's own transport rather than the WordPress
 * HTTP API on purpose: the question is not whether some HTTP client can reach
 * the endpoint, it is whether the client the proxy itself will use can.
 *
 * Nothing here runs on a site that has not switched the gateway on. The daily
 * event is scheduled when the setting is enabled and cleared when it is not,
 * so the feature costs a site that ignores it no requests at all.
 */
final class GoogleTagGatewayHealth {

	/**
	 * The option holding the last result.
	 *
	 * @var string
	 */
	public const OPTION = 'gtmkit_gtg_health';

	/**
	 * The scheduled event that re-runs the checks.
	 *
	 * @var string
	 */
	public const CHECK_HOOK = 'gtmkit_gtg_health_check';

	/**
	 * The Action Scheduler group scheduled checks belong to.
	 *
	 * @var string
	 */
	private const SCHEDULER_GROUP = 'gtmkit';

	/**
	 * The stored result's schema version.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * The gateway service endpoint that answers a reachability probe.
	 *
	 * A fixed sentinel published by the service for this purpose. It carries
	 * no identifier of this site, so the probe reports only whether outbound
	 * HTTPS works, not that this site exists.
	 *
	 * @var string
	 */
	private const SERVICE_ENDPOINT = 'https://g-1234.fps.goog/mpath/healthy';

	/**
	 * The body a healthy endpoint returns.
	 *
	 * @var string
	 */
	private const HEALTHY_BODY = 'ok';

	/**
	 * How long a stored result stands before the enable gate re-runs it.
	 *
	 * @var int
	 */
	private const MAX_AGE = 3600;

	/**
	 * The option remembering that the fallback notice was dismissed.
	 *
	 * @var string
	 */
	public const NOTICE_DISMISSED_OPTION = 'gtmkit_gtg_notice_dismissed';

	/**
	 * An instance of Options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register the scheduling.
	 *
	 * The checks perform blocking HTTP requests, but only when their event
	 * fires. No hook here performs a request on a page render.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {

		$instance = new self( $options );

		// Attached on every request, because a cron run is not always
		// recognisable as one while plugins load: a run started from WP-CLI or
		// by an Action Scheduler runner can fire the event in a request that
		// was not marked as cron in time. An event that fires with nothing
		// attached is quietly lost, and the gateway then serves on a result
		// nobody refreshes.
		add_action( self::CHECK_HOOK, [ $instance, 'run_scheduled_check' ] );

		if ( is_admin() ) {
			add_action( 'admin_init', [ $instance, 'schedule_daily_event' ] );
		}
	}

	/**
	 * Keep the daily check scheduled while the gateway is switched on.
	 *
	 * Runs on every admin page load, so it restores a schedule that has gone
	 * missing. It does not tear one down when the gateway is off: switching
	 * off clears the schedule, and a check that fires while the gateway is off
	 * clears it too, so an admin page load never has to ask the scheduler.
	 *
	 * @return void
	 */
	public function schedule_daily_event(): void {

		if ( (bool) $this->options->get( 'general', 'google_tag_gateway' ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedule the daily check unless it already is.
	 *
	 * @return void
	 */
	public static function schedule(): void {

		$first_run = strtotime( 'midnight' );

		if ( class_exists( 'ActionScheduler' ) ) {
			if ( ! as_next_scheduled_action( self::CHECK_HOOK ) ) {
				as_schedule_recurring_action( $first_run, DAY_IN_SECONDS, self::CHECK_HOOK, [], self::SCHEDULER_GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( $first_run, 'daily', self::CHECK_HOOK );
		}
	}

	/**
	 * Cancel every scheduled check.
	 *
	 * Called on deactivation, so a deactivated plugin leaves no scheduled
	 * request behind on either scheduler.
	 *
	 * @return void
	 */
	public static function clear_scheduled_event(): void {

		wp_clear_scheduled_hook( self::CHECK_HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CHECK_HOOK, [], self::SCHEDULER_GROUP );
		}
	}

	/**
	 * Perform the scheduled check.
	 *
	 * A check that fires after the gateway was switched off, from a schedule
	 * nothing cleared, clears the schedule instead of making requests for a
	 * feature nobody is using.
	 *
	 * @return void
	 */
	public function run_scheduled_check(): void {

		if ( ! (bool) $this->options->get( 'general', 'google_tag_gateway' ) ) {
			self::clear_scheduled_event();

			return;
		}

		self::run_checks();
	}

	/**
	 * Run both checks and store the result.
	 *
	 * @param string|null $gtm_id The container to request, when the caller knows it better than the stored
	 *                            settings do. A save that sets the ID and switches the gateway on together is
	 *                            judged on the ID it sets; null reads the stored one.
	 *
	 * @return array<string, mixed> The stored result.
	 */
	public static function run_checks( ?string $gtm_id = null ): array {

		$result = [
			'schema'  => self::SCHEMA_VERSION,
			'service' => self::answers_ok( self::SERVICE_ENDPOINT ),
			'script'  => self::is_tag_served( $gtm_id ),
			'checked' => time(),
		];

		update_option( self::OPTION, $result, false );

		// A dismissal answers one episode of the gateway being down, not the
		// feature as a whole. Once it is serving again the slate is clean, so
		// the next time it breaks the site owner is told rather than left with
		// a silent fallback they dismissed months earlier.
		if ( ! empty( $result['service'] ) && ! empty( $result['script'] ) ) {
			delete_option( self::NOTICE_DISMISSED_OPTION );
		}

		return $result;
	}

	/**
	 * Remember that the fallback notice was dismissed.
	 *
	 * @return void
	 */
	public static function dismiss_notice(): void {
		update_option( self::NOTICE_DISMISSED_OPTION, true, false );
	}

	/**
	 * Whether the fallback notice has been dismissed for the current outage.
	 *
	 * @return bool
	 */
	public static function is_notice_dismissed(): bool {
		return (bool) get_option( self::NOTICE_DISMISSED_OPTION, false );
	}

	/**
	 * The last stored result, if the checks have ever run.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_result(): ?array {

		$result = get_option( self::OPTION );

		if ( ! is_array( $result ) || ( $result['schema'] ?? 0 ) !== self::SCHEMA_VERSION ) {
			return null;
		}

		return $result;
	}

	/**
	 * Re-run the checks when no result stands or the last one has aged out.
	 *
	 * @param string|null $gtm_id The container to request if the checks run; null reads the stored one.
	 *
	 * @return array<string, mixed> The result now standing.
	 */
	public static function ensure_fresh( ?string $gtm_id = null ): array {

		$result = self::get_result();

		if ( $result !== null && ( time() - (int) $result['checked'] ) < self::MAX_AGE ) {
			return $result;
		}

		return self::run_checks( $gtm_id );
	}

	/**
	 * Whether both checks passed the last time they ran.
	 *
	 * A site whose checks have never run is not passing. The gateway is not
	 * served on a maybe: until something has confirmed the proxy answers,
	 * the standard loader is the safe answer.
	 *
	 * @return bool
	 */
	public static function is_passing(): bool {

		$result = self::get_result();

		if ( $result === null ) {
			return false;
		}

		return ! empty( $result['service'] ) && ! empty( $result['script'] );
	}

	/**
	 * The address the page's loader requests the container from.
	 *
	 * @param string $gtm_id The container ID.
	 *
	 * @return string
	 */
	public static function tag_request_url( string $gtm_id ): string {
		return GoogleTagGateway::proxy_url() . '?id=' . rawurlencode( $gtm_id );
	}

	/**
	 * Whether a proxy response is the tag being served.
	 *
	 * A reply the proxy writes on its own, such as the plain `ok` of its
	 * health endpoint, is not the tag and does not count.
	 *
	 * @param array<string, mixed> $response The response, as the proxy's transport returns it.
	 *
	 * @return bool
	 */
	public static function response_serves_tag( array $response ): bool {

		if ( (int) ( $response['statusCode'] ?? 0 ) !== 200 || '' === trim( (string) ( $response['body'] ?? '' ) ) ) {
			return false;
		}

		foreach ( (array) ( $response['headers'] ?? [] ) as $header ) {
			if ( preg_match( '/^\s*content-type\s*:.*javascript/i', (string) $header ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the proxy serves the container to a request made the way the page makes it.
	 *
	 * The request is the one the page's loader makes, so it passes through
	 * everything that stands between a visitor and the tag: the web server,
	 * any firewall or security rule in front of the plugin directory, the
	 * proxy itself and the gateway service behind it. A probe that stopped
	 * short of that would pass on a site where the tag never loads.
	 *
	 * Without a container ID there is nothing to request, so only the proxy's
	 * own reply is checked. The gateway serves nothing on such a site anyway.
	 *
	 * @param string|null $gtm_id The container to request; null reads the stored one.
	 *
	 * @return bool
	 */
	private static function is_tag_served( ?string $gtm_id ): bool {

		$gtm_id = $gtm_id ?? (string) OptionsFactory::get_instance()->get( 'general', 'gtm_id' );

		if ( '' === $gtm_id ) {
			return self::answers_ok( add_query_arg( 'healthCheck', '1', GoogleTagGateway::proxy_url() ) );
		}

		$response = self::fetch( self::tag_request_url( $gtm_id ) );

		return null !== $response && self::response_serves_tag( $response );
	}

	/**
	 * Whether an endpoint answers the health probe with the healthy body.
	 *
	 * @param string $endpoint The endpoint to check.
	 *
	 * @return bool
	 */
	private static function answers_ok( string $endpoint ): bool {

		$response = self::fetch( $endpoint );

		if ( null === $response || (int) ( $response['statusCode'] ?? 0 ) !== 200 ) {
			return false;
		}

		return trim( (string) ( $response['body'] ?? '' ) ) === self::HEALTHY_BODY;
	}

	/**
	 * Request an endpoint through the proxy's own transport.
	 *
	 * @param string $endpoint The endpoint to request.
	 *
	 * @return array<string, mixed>|null The response, or null when the proxy file cannot be loaded.
	 */
	private static function fetch( string $endpoint ): ?array {

		// The proxy file defines the transport it uses and returns early
		// when this constant is set, so requiring it here yields the client
		// without performing a proxy request of its own.
		if ( ! defined( 'GTMKIT_GTG_ENDPOINT_HEALTH_CHECK' ) ) {
			define( 'GTMKIT_GTG_ENDPOINT_HEALTH_CHECK', true );
		}

		$proxy_file = GoogleTagGateway::proxy_file();

		if ( ! is_readable( $proxy_file ) ) {
			return null;
		}

		require_once $proxy_file;

		$helper = new \Google\GoogleTagGatewayLibrary\Http\RequestHelper();

		return $helper->sendRequest( 'GET', $endpoint );
	}
}
