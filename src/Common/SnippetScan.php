<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Admin\PluginAvailability;
use TLA_Media\GTM_Kit\Frontend\UrlExclusion;
use TLA_Media\GTM_Kit\Options\Options;
use WP_Error;

/**
 * Server-side scan of a sample page for tracking implementations.
 *
 * One scheduled request per day fetches a sample page the way an anonymous
 * visitor would receive it, identifies every tracking implementation in the
 * response, and stores the finding. Every consumer, the notices and the Site
 * Health test alike, reads that stored finding: nothing performs the fetch
 * inline, and nothing runs on a visitor request.
 *
 * A server-side fetch sees the HTML the server sends. It cannot see a
 * container that a consent platform or another loader injects later in the
 * browser, so an absent container is reported as "not found in the response",
 * never as "not loading". That distinction is what the third result state,
 * `inconclusive`, and the deliberately narrow wording exist for.
 */
final class SnippetScan {

	/**
	 * The standalone option holding the last scan result.
	 *
	 * Stored outside the `gtmkit` options array, and never autoloaded: the
	 * result is evidence about the site rather than configuration of it, and
	 * only the admin surfaces that report it ever read it.
	 *
	 * @var string
	 */
	public const OPTION = 'gtmkit_snippet_scan';

	/**
	 * The scheduled event that performs the scan.
	 *
	 * @var string
	 */
	public const SCAN_HOOK = 'gtmkit_snippet_scan';

	/**
	 * The Action Scheduler group scheduled scans belong to.
	 *
	 * @var string
	 */
	private const SCHEDULER_GROUP = 'gtmkit';

	/**
	 * The shape of the stored result.
	 *
	 * A stored result carrying any other version is ignored, so a future
	 * change to the shape never has to migrate old data.
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * At least one tracking implementation was identified in the response.
	 *
	 * @var string
	 */
	public const STATE_FOUND = 'found';

	/**
	 * The fetch succeeded, the response looked like a complete page, and no
	 * implementation was identified.
	 *
	 * @var string
	 */
	public const STATE_NOT_FOUND = 'not_found';

	/**
	 * Nothing can be concluded from this scan. See the `reason` field.
	 *
	 * @var string
	 */
	public const STATE_INCONCLUSIVE = 'inconclusive';

	/**
	 * The request to the site's own URL did not complete.
	 *
	 * @var string
	 */
	public const REASON_LOOPBACK_BLOCKED = 'loopback_blocked';

	/**
	 * The request failed for a reason other than a blocked loopback.
	 *
	 * @var string
	 */
	public const REASON_REQUEST_FAILED = 'request_failed';

	/**
	 * The response carried a status outside the 2xx range.
	 *
	 * @var string
	 */
	public const REASON_HTTP_STATUS = 'http_status';

	/**
	 * The response body was empty.
	 *
	 * @var string
	 */
	public const REASON_EMPTY_RESPONSE = 'empty_response';

	/**
	 * The response was not HTML.
	 *
	 * @var string
	 */
	public const REASON_NOT_HTML = 'not_html';

	/**
	 * The response looked like a truncated document.
	 *
	 * @var string
	 */
	public const REASON_INCOMPLETE_RESPONSE = 'incomplete_response';

	/**
	 * The scan URL matches an active exclusion pattern, so GTM Kit outputs
	 * nothing there by design.
	 *
	 * @var string
	 */
	public const REASON_URL_EXCLUDED = 'url_excluded';

	/**
	 * More than one distinct container is present.
	 *
	 * @var string
	 */
	public const DUPLICATE_CONTAINERS = 'multiple_containers';

	/**
	 * The same container is loaded by more than one script.
	 *
	 * @var string
	 */
	public const DUPLICATE_REPEATED = 'repeated_container';

	/**
	 * A Google tag is loaded directly alongside the container.
	 *
	 * @var string
	 */
	public const DUPLICATE_GTAG = 'gtag_alongside_gtm';

	/**
	 * Markers left in the page by tools that add tracking code but are not
	 * plugins GTM Kit already checks for by name.
	 *
	 * Used only to name a culprit once a duplicate has been established, and
	 * only after the active-plugin check has come up empty: a plugin that is
	 * demonstrably active beats a string in the HTML every time.
	 *
	 * @var array<int, array<string, string>>
	 */
	private const SIGNATURES = [
		[
			'pattern' => '~Google Tag Manager for WordPress by gtm4wp~i',
			'name'    => 'Google Tag Manager for WordPress',
		],
		[
			'pattern' => '~(?:added|snippet) by Site Kit|google-site-kit~i',
			'name'    => 'Site Kit by Google',
		],
		[
			'pattern' => '~MonsterInsights~i',
			'name'    => 'MonsterInsights',
		],
		[
			'pattern' => '~ExactMetrics~i',
			'name'    => 'ExactMetrics',
		],
		[
			'pattern' => '~snippet added by WPCode|wpcode-~i',
			'name'    => 'WPCode',
		],
		[
			'pattern' => '~Header Footer Code Manager|\bhfcm[-_]~i',
			'name'    => 'Header Footer Code Manager',
		],
	];

	/**
	 * Seconds to wait for the sample page.
	 *
	 * @var int
	 */
	private const REQUEST_TIMEOUT = 10;

	/**
	 * The query argument carrying a manual rescan request.
	 *
	 * @var string
	 */
	private const RESCAN_ARG = 'gtmkit-snippet-rescan';

	/**
	 * The nonce action guarding a manual rescan.
	 *
	 * @var string
	 */
	private const RESCAN_NONCE = 'gtmkit-snippet-rescan';

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
	 * Register the scan engine.
	 *
	 * The scan performs a blocking HTTP request, so it is attached in the
	 * admin and on cron requests only. A visitor request never reaches it:
	 * there is no hook here that a page render fires.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {

		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		$instance = new self( $options );

		add_action( self::SCAN_HOOK, [ $instance, 'run_scheduled_scan' ] );

		if ( is_admin() ) {
			add_action( 'admin_init', [ $instance, 'schedule_daily_event' ] );
			add_action( 'admin_init', [ $instance, 'handle_rescan_request' ] );
		}
	}

	/**
	 * Schedule the daily scan.
	 *
	 * Action Scheduler when the site has it, WP-Cron otherwise, matching how
	 * the plugin schedules its other daily work.
	 *
	 * @return void
	 */
	public function schedule_daily_event(): void {

		if ( class_exists( 'ActionScheduler' ) ) {
			if ( ! as_next_scheduled_action( self::SCAN_HOOK ) ) {
				as_schedule_single_action( strtotime( 'midnight +25 hours' ), self::SCAN_HOOK, [], self::SCHEDULER_GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::SCAN_HOOK ) ) {
			wp_schedule_event( strtotime( 'midnight' ), 'daily', self::SCAN_HOOK );
		}
	}

	/**
	 * Cancel every scheduled scan.
	 *
	 * Called on deactivation, so a deactivated plugin leaves no scheduled
	 * request behind on either scheduler.
	 *
	 * @return void
	 */
	public static function clear_scheduled_event(): void {

		wp_clear_scheduled_hook( self::SCAN_HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::SCAN_HOOK, [], self::SCHEDULER_GROUP );
		}
	}

	/**
	 * Perform the scheduled scan.
	 *
	 * @return void
	 */
	public function run_scheduled_scan(): void {
		$this->run_scan();
	}

	/**
	 * Perform a scan and store its result.
	 *
	 * @return array<string, mixed> The stored result.
	 */
	public function run_scan(): array {

		$url = $this->get_scan_url();

		$result = $this->scan( $url );

		update_option( self::OPTION, $result, false );

		return $result;
	}

	/**
	 * Resolve the URL the scan requests.
	 *
	 * @return string
	 */
	public function get_scan_url(): string {

		/**
		 * Filters the sample page the tracking scan requests.
		 *
		 * One request runs per scan, against the site's home page by
		 * default. Point this at another URL when the home page is not
		 * representative of the site's pages, for example when it is a
		 * redirect or a landing page that carries no tracking.
		 *
		 * @param string $url The URL to scan.
		 */
		$url = (string) apply_filters( 'gtmkit_snippet_scan_url', home_url( '/' ) );

		return $url;
	}

	/**
	 * Fetch the sample page and interpret the response.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return array<string, mixed> The result.
	 */
	private function scan( string $url ): array {

		if ( $this->is_excluded( $url ) ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, self::REASON_URL_EXCLUDED );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => self::REQUEST_TIMEOUT,
				'redirection' => 5,
				// No cookies and no cache-busting argument: the scan must see
				// what an anonymous visitor receives, full-page cache and all.
				'cookies'     => [],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, $this->reason_for_error( $response ) );
		}

		$status    = (int) wp_remote_retrieve_response_code( $response );
		$final_url = $this->get_final_url( $response, $url );

		if ( $status < 200 || $status >= 300 ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, self::REASON_HTTP_STATUS, $status, $final_url );
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( trim( $body ) === '' ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, self::REASON_EMPTY_RESPONSE, $status, $final_url );
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$content_type = is_array( $content_type ) ? (string) reset( $content_type ) : (string) $content_type;

		if ( ! $this->looks_like_html( $body, $content_type ) ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, self::REASON_NOT_HTML, $status, $final_url );
		}

		$implementations = SnippetScanDetector::detect( $body, $this->get_own_output_fingerprint() );

		if ( $implementations !== [] ) {
			// Evidence of tracking stands on its own. Only the absence of
			// evidence needs a complete document to mean anything, so
			// completeness is checked below rather than here.
			return $this->build_result(
				$url,
				self::STATE_FOUND,
				'',
				$status,
				$final_url,
				$implementations,
				$this->analyse_duplicates( $implementations, $body )
			);
		}

		if ( ! $this->looks_complete( $body ) ) {
			return $this->build_result( $url, self::STATE_INCONCLUSIVE, self::REASON_INCOMPLETE_RESPONSE, $status, $final_url );
		}

		return $this->build_result( $url, self::STATE_NOT_FOUND, '', $status, $final_url );
	}

	/**
	 * Whether the scan URL is one GTM Kit deliberately outputs nothing on.
	 *
	 * Scanning an excluded URL would find no container and prove nothing, so
	 * the result is inconclusive rather than a finding.
	 *
	 * @param string $url The URL to scan.
	 *
	 * @return bool
	 */
	private function is_excluded( string $url ): bool {

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || $path === '' ) {
			$path = '/';
		}

		return UrlExclusion::is_excluded( $path, $this->options->get( 'general', 'excluded_url_patterns' ) );
	}

	/**
	 * Map a transport error to a result reason.
	 *
	 * A request to the site's own URL that never completes is, in practice,
	 * a site whose server cannot reach itself: loopback requests are blocked
	 * by a firewall, a hosting rule or a DNS split. Anything else is reported
	 * as a plain request failure so the wording never overstates the cause.
	 *
	 * @param WP_Error $error The transport error.
	 *
	 * @return string
	 */
	private function reason_for_error( WP_Error $error ): string {

		$blocked = [
			'http_request_failed',
			'http_request_not_executed',
		];

		return in_array( $error->get_error_code(), $blocked, true )
			? self::REASON_LOOPBACK_BLOCKED
			: self::REASON_REQUEST_FAILED;
	}

	/**
	 * Resolve the URL the response was finally served from.
	 *
	 * @param array<string, mixed> $response The response.
	 * @param string               $fallback The requested URL.
	 *
	 * @return string
	 */
	private function get_final_url( array $response, string $fallback ): string {

		if ( ! isset( $response['http_response'] ) ) {
			return $fallback;
		}

		$http_response = $response['http_response'];

		if ( ! $http_response instanceof \WP_HTTP_Requests_Response ) {
			return $fallback;
		}

		$final = (string) $http_response->get_response_object()->url;

		return ( $final !== '' ) ? $final : $fallback;
	}

	/**
	 * Whether the response is HTML.
	 *
	 * @param string $body The response body.
	 * @param string $content_type The response content type header.
	 *
	 * @return bool
	 */
	private function looks_like_html( string $body, string $content_type ): bool {

		if ( $content_type !== '' && stripos( $content_type, 'html' ) === false ) {
			return false;
		}

		return stripos( $body, '<html' ) !== false || stripos( $body, '<body' ) !== false;
	}

	/**
	 * Whether the response looks like a whole document rather than a truncated one.
	 *
	 * @param string $body The response body.
	 *
	 * @return bool
	 */
	private function looks_complete( string $body ): bool {
		return stripos( $body, '</html>' ) !== false || stripos( $body, '</body>' ) !== false;
	}

	/**
	 * Describe GTM Kit's own output, so implementations can be told apart.
	 *
	 * @return array<string, string> Keys: `container`, `domain`, `loader`.
	 */
	public function get_own_output_fingerprint(): array {

		$domain = (string) $this->options->get( 'general', 'sgtm_domain' );
		$loader = (string) $this->options->get( 'general', 'sgtm_container_identifier' );

		return [
			'container' => (string) $this->options->get( 'general', 'gtm_id' ),
			'domain'    => ( $domain !== '' ) ? $domain : 'www.googletagmanager.com',
			'loader'    => ( $loader !== '' ) ? $loader : 'gtm',
		];
	}

	/**
	 * Assemble a result.
	 *
	 * @param string                           $url The URL that was scanned.
	 * @param string                           $state One of the STATE_* constants.
	 * @param string                           $reason One of the REASON_* constants, or an empty string.
	 * @param int                              $status The HTTP status code, or 0.
	 * @param string                           $final_url The URL after redirects.
	 * @param array<int, array<string, mixed>> $implementations The identified implementations.
	 * @param array<string, mixed>|null        $duplicate The duplicate finding, or null for none.
	 *
	 * @return array<string, mixed>
	 */
	private function build_result( string $url, string $state, string $reason, int $status = 0, string $final_url = '', array $implementations = [], ?array $duplicate = null ): array {

		return [
			'schema'           => self::SCHEMA_VERSION,
			'url'              => $url,
			'final_url'        => ( $final_url !== '' ) ? $final_url : $url,
			'scanned_at'       => time(),
			'state'            => $state,
			'reason'           => $reason,
			'status'           => $status,
			'implementations'  => $implementations,
			'container_output' => $this->container_output_is_on(),
			'cmp'              => CMPDetection::detect_active_cmp(),
			'duplicate'        => $duplicate ?? self::no_duplicate(),
		];
	}

	/**
	 * The duplicate finding of a page that loads tracking at most once.
	 *
	 * @return array<string, mixed>
	 */
	private static function no_duplicate(): array {

		return [
			'type'       => '',
			'containers' => [],
			'culprit'    => '',
		];
	}

	/**
	 * Decide whether the page loads tracking more than once, and how.
	 *
	 * The three cases are kept apart because the fix differs for each: a
	 * second container is somebody else's measurement running alongside
	 * yours, the same container twice is one measurement counted twice, and a
	 * Google tag beside a container is a tag that may also be firing from
	 * inside it.
	 *
	 * Only script elements and inline bootstraps count as loads. The noscript
	 * iframe is the fallback of an implementation that is already counted,
	 * and a bare container ID is not loaded by anything.
	 *
	 * @param array<int, array<string, mixed>> $implementations The identified implementations.
	 * @param string                           $body The response body, for naming a culprit.
	 *
	 * @return array<string, mixed>
	 */
	private function analyse_duplicates( array $implementations, string $body ): array {

		$gtm_loads  = 0;
		$gtag_loads = 0;
		$containers = [];

		foreach ( $implementations as $implementation ) {
			if ( ! SnippetScanDetector::counts_as_load( $implementation ) ) {
				continue;
			}

			if ( (string) $implementation['type'] === SnippetScanDetector::TYPE_GTAG ) {
				++$gtag_loads;
				continue;
			}

			++$gtm_loads;

			$container = (string) $implementation['container'];
			if ( $container !== '' ) {
				$containers[ strtoupper( $container ) ] = strtoupper( $container );
			}
		}

		$type = '';

		if ( count( $containers ) > 1 ) {
			$type = self::DUPLICATE_CONTAINERS;
		} elseif ( $gtm_loads > 1 ) {
			$type = self::DUPLICATE_REPEATED;
		} elseif ( $gtm_loads > 0 && $gtag_loads > 0 ) {
			$type = self::DUPLICATE_GTAG;
		}

		if ( $type === '' ) {
			return self::no_duplicate();
		}

		return [
			'type'       => $type,
			'containers' => array_values( $containers ),
			'culprit'    => $this->identify_culprit( $body ),
		];
	}

	/**
	 * Name what is adding the second implementation, if it can be named.
	 *
	 * Active plugins come first: a plugin that is demonstrably running is a
	 * stronger answer than a string that happens to appear in the HTML. The
	 * shipped signature list then covers the tools that add tracking code
	 * without being plugins GTM Kit tracks by name, above all theme hardcodes
	 * and code-snippet plugins. When neither answers, the finding is reported
	 * without a name rather than with a guess.
	 *
	 * @param string $body The response body.
	 *
	 * @return string The name, or an empty string.
	 */
	private function identify_culprit( string $body ): string {

		Util::load_plugin_api();

		$availability = new PluginAvailability();
		$availability->register();

		foreach ( $availability->get_plugins( 'conflicting' ) as $plugin ) {
			if ( $availability->is_active( $plugin ) ) {
				return (string) $plugin['name'];
			}
		}

		foreach ( self::SIGNATURES as $signature ) {
			if ( preg_match( $signature['pattern'], $body ) === 1 ) {
				return $signature['name'];
			}
		}

		return '';
	}

	/**
	 * Whether GTM Kit intends to output the container.
	 *
	 * A site that reports itself as somewhere the container does not belong
	 * is answered first: GTM Kit intends no container there, so a page
	 * without one is the intended result rather than evidence that something
	 * stripped it out.
	 *
	 * @return bool
	 */
	private function container_output_is_on(): bool {

		if ( SiteEnvironment::suppresses_container( $this->options ) ) {
			return false;
		}

		return (bool) $this->options->get( 'general', 'container_active' )
			&& (bool) apply_filters( 'gtmkit_container_active', true )
			&& (string) $this->options->get( 'general', 'gtm_id' ) !== '';
	}

	/**
	 * Get the stored result of the last scan.
	 *
	 * @return array<string, mixed>|null The result, or null when no usable result is stored.
	 */
	public function get_result(): ?array {

		$stored = get_option( self::OPTION );

		if ( ! is_array( $stored ) || ( $stored['schema'] ?? 0 ) !== self::SCHEMA_VERSION ) {
			return null;
		}

		if ( ! isset( $stored['state'] ) || ! in_array( $stored['state'], [ self::STATE_FOUND, self::STATE_NOT_FOUND, self::STATE_INCONCLUSIVE ], true ) ) {
			return null;
		}

		return $stored;
	}

	/**
	 * Whether the scanned page carried a tracking implementation.
	 *
	 * Three-valued on purpose. `null` means the question is open: no scan has
	 * run, or the last one could not answer it. A caller that treats `null`
	 * as `false` turns "we do not know" into "your site is not measured",
	 * which is the one mistake this whole engine exists to avoid.
	 *
	 * @return bool|null True or false when the last scan settled it, null otherwise.
	 */
	public function has_container(): ?bool {

		$result = $this->get_result();

		if ( $result === null || $result['state'] === self::STATE_INCONCLUSIVE ) {
			return null;
		}

		return $result['state'] === self::STATE_FOUND;
	}

	/**
	 * Whether the scanned page loaded tracking more than once.
	 *
	 * Three-valued for the same reason as {@see self::has_container()}: a
	 * scan that found nothing, or could not be completed, has not established
	 * that the page is free of duplicates.
	 *
	 * @return bool|null True or false when the last scan settled it, null otherwise.
	 */
	public function has_duplicate_tracking(): ?bool {

		$result = $this->get_result();

		if ( $result === null || $result['state'] !== self::STATE_FOUND ) {
			return null;
		}

		return (string) $result['duplicate']['type'] !== '';
	}

	/**
	 * Handle a manual rescan requested from an admin screen.
	 *
	 * @return void
	 */
	public function handle_rescan_request(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is verified below, once the request is known to be a rescan.
		if ( ! isset( $_GET[ self::RESCAN_ARG ] ) ) {
			return;
		}

		$capability = (string) apply_filters( 'gtmkit_admin_capability', is_multisite() ? 'manage_network_options' : 'manage_options' );

		if ( ! current_user_can( $capability ) || ! check_admin_referer( self::RESCAN_NONCE ) ) {
			return;
		}

		$this->run_scan();

		wp_safe_redirect( remove_query_arg( [ self::RESCAN_ARG, '_wpnonce' ] ) );
		exit;
	}

	/**
	 * Build the URL that triggers a manual rescan of the current screen.
	 *
	 * @return string
	 */
	public function get_rescan_url(): string {
		return wp_nonce_url( add_query_arg( self::RESCAN_ARG, '1' ), self::RESCAN_NONCE );
	}
}
