<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\SnippetScanDetector;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Asks once for a review on WordPress.org, after GTM Kit has proven itself.
 *
 * The ask appears on GTM Kit's own settings screen, never as an admin notice
 * across wp-admin, and only once the site has shown two weeks of success in a
 * row: the daily page check finds the container that GTM Kit loads, it finds
 * no duplicate tracking, and the container test in Site Health is good. The
 * two weeks are counted from the first check that found it, and start over
 * whenever a check does not, or when no check has run for a while, since
 * success nobody observed is not success. An open question, such as a check
 * that could not be completed, is not a success either.
 *
 * The ask is raised conditionally, so it keeps its own record in a dedicated
 * option rather than in the notifications system, whose dismissals cover only
 * notifications it currently holds. "Not now" puts it off for a while, once;
 * dismissing it a second time, choosing "Don't ask again" or following the
 * review link ends it for good.
 *
 * Nothing is sent anywhere. Every input is already stored on the site, and the
 * review link is an ordinary link the owner follows in a new tab.
 */
final class ReviewAsk {

	/**
	 * The option holding the ask's state.
	 *
	 * @var string
	 */
	public const OPTION = 'gtmkit_review_ask';

	/**
	 * How long the container must have been found without a break, in days.
	 *
	 * @var int
	 */
	public const SUCCESS_DAYS = 14;

	/**
	 * How old the last check may be and still count, in days.
	 *
	 * The check runs daily, so this allows for one missed run.
	 *
	 * @var int
	 */
	public const FRESH_DAYS = 2;

	/**
	 * How long "Not now" puts the ask off, in days.
	 *
	 * @var int
	 */
	public const SNOOZE_DAYS = 30;

	/**
	 * Where the review link goes.
	 *
	 * @var string
	 */
	public const REVIEW_URL = 'https://wordpress.org/support/plugin/gtm-kit/reviews/#new-post';

	/**
	 * Put the ask off. The second time ends it.
	 *
	 * @var string
	 */
	public const ACTION_SNOOZE = 'snooze';

	/**
	 * End the ask for good.
	 *
	 * @var string
	 */
	public const ACTION_DISMISS = 'dismiss';

	/**
	 * The owner followed the review link, which ends the ask.
	 *
	 * @var string
	 */
	public const ACTION_REVIEWED = 'reviewed';

	/**
	 * An instance of SnippetScan.
	 *
	 * @var SnippetScan
	 */
	private SnippetScan $snippet_scan;

	/**
	 * An instance of SiteHealth.
	 *
	 * @var SiteHealth
	 */
	private SiteHealth $site_health;

	/**
	 * Constructor.
	 *
	 * @param SnippetScan $snippet_scan An instance of SnippetScan.
	 * @param SiteHealth  $site_health An instance of SiteHealth.
	 */
	public function __construct( SnippetScan $snippet_scan, SiteHealth $site_health ) {
		$this->snippet_scan = $snippet_scan;
		$this->site_health  = $site_health;
	}

	/**
	 * Register the ask.
	 *
	 * @param SnippetScan $snippet_scan An instance of SnippetScan.
	 * @param Options     $options An instance of Options.
	 * @param Util        $util An instance of Util.
	 *
	 * @return void
	 */
	public static function register( SnippetScan $snippet_scan, Options $options, Util $util ): void {
		$instance = new self( $snippet_scan, new SiteHealth( $options, $util ) );

		// Every stored check result moves the clock, whoever opens the admin.
		add_action( 'add_option_' . SnippetScan::OPTION, [ $instance, 'observe_scan' ] );
		add_action( 'update_option_' . SnippetScan::OPTION, [ $instance, 'observe_scan' ] );

		add_filter( 'gtmkit_settings', [ $instance, 'add_to_settings' ] );

		add_action(
			'rest_api_init',
			static function () use ( $instance, $util ): void {
				$util->rest_api_server->register_rest_route(
					'/review-ask',
					[
						'methods'  => 'POST',
						'callback' => [ $instance, 'handle_request' ],
						'args'     => [
							'action' => [
								'type'     => 'string',
								'required' => true,
								'enum'     => [ self::ACTION_SNOOZE, self::ACTION_DISMISS, self::ACTION_REVIEWED ],
							],
						],
					]
				);
			}
		);
	}

	/**
	 * Move the success clock when a check result is stored.
	 *
	 * @return void
	 */
	public function observe_scan(): void {
		$this->observe();
	}

	/**
	 * Keep the success clock: start it on the first successful check, and
	 * stop it when a check is not a success or the checks have stopped.
	 *
	 * @param int|null $now The current time; defaults to now.
	 *
	 * @return void
	 */
	public function observe( ?int $now = null ): void {
		$now   = $now ?? time();
		$state = $this->get_state();
		$fresh = self::FRESH_DAYS * DAY_IN_SECONDS;

		if ( $this->is_success( $now ) ) {
			$broken = null !== $state['last_seen'] && ( $now - $state['last_seen'] ) > $fresh;

			if ( null === $state['first_seen'] || $broken ) {
				$state['first_seen'] = $now;
			}
			$state['last_seen'] = $now;
		} elseif ( null === $state['first_seen'] && null === $state['last_seen'] ) {
			return;
		} else {
			$state['first_seen'] = null;
			$state['last_seen']  = null;
		}

		$this->save_state( $state );
	}

	/**
	 * Whether the stored check is a recent one that found the container GTM Kit loads.
	 *
	 * Another plugin's or the theme's tracking does not count: the ask thanks
	 * GTM Kit for loading the container.
	 *
	 * @param int $now The current time.
	 *
	 * @return bool
	 */
	private function is_success( int $now ): bool {
		if ( true !== $this->snippet_scan->has_container() ) {
			return false;
		}

		$result = (array) $this->snippet_scan->get_result();

		if ( ( $now - (int) ( $result['scanned_at'] ?? 0 ) ) > self::FRESH_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		foreach ( (array) ( $result['implementations'] ?? [] ) as $implementation ) {
			if (
				is_array( $implementation )
				&& SnippetScanDetector::OWNER_GTMKIT === ( $implementation['owner'] ?? '' )
				&& SnippetScanDetector::counts_as_load( $implementation )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether to ask the current user now.
	 *
	 * @param int|null $now The current time; defaults to now.
	 *
	 * @return bool
	 */
	public function should_ask( ?int $now = null ): bool {
		$now = $now ?? time();

		if ( ! current_user_can( apply_filters( 'gtmkit_admin_capability', is_multisite() ? 'manage_network_options' : 'manage_options' ) ) ) {
			return false;
		}

		// Catches a result stored before the clock was being kept.
		$this->observe( $now );

		$state = $this->get_state();

		if ( $state['done'] || $state['snoozed_until'] > $now || null === $state['first_seen'] ) {
			return false;
		}

		if ( ( $now - $state['first_seen'] ) < self::SUCCESS_DAYS * DAY_IN_SECONDS ) {
			return false;
		}

		if ( false !== $this->snippet_scan->has_duplicate_tracking() ) {
			return false;
		}

		return 'good' === ( $this->site_health->test_container()['status'] ?? '' );
	}

	/**
	 * Record what the owner chose.
	 *
	 * @param string   $action One of the ACTION_* constants.
	 * @param int|null $now The current time; defaults to now.
	 *
	 * @return void
	 */
	public function record( string $action, ?int $now = null ): void {
		$now   = $now ?? time();
		$state = $this->get_state();

		if ( self::ACTION_SNOOZE === $action && 0 === $state['snoozes'] ) {
			$state['snoozes']       = 1;
			$state['snoozed_until'] = $now + self::SNOOZE_DAYS * DAY_IN_SECONDS;
		} else {
			$state['done'] = true;
		}

		$this->save_state( $state );
	}

	/**
	 * Tell the settings screen whether to ask.
	 *
	 * @param array<string, mixed> $settings The settings payload.
	 *
	 * @return array<string, mixed>
	 */
	public function add_to_settings( array $settings ): array {
		$settings['reviewAsk'] = $this->should_ask()
			? [ 'url' => self::REVIEW_URL ]
			: null;

		return $settings;
	}

	/**
	 * Handle the owner's choice from the settings screen.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		$this->record( (string) $request->get_param( 'action' ) );

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => null,
			]
		);
	}

	/**
	 * Read the stored state.
	 *
	 * Anything malformed reads as a fresh start, never as a fatal.
	 *
	 * @return array{first_seen: int|null, last_seen: int|null, snoozed_until: int, snoozes: int, done: bool}
	 */
	private function get_state(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'first_seen'    => isset( $stored['first_seen'] ) && is_numeric( $stored['first_seen'] ) ? (int) $stored['first_seen'] : null,
			'last_seen'     => isset( $stored['last_seen'] ) && is_numeric( $stored['last_seen'] ) ? (int) $stored['last_seen'] : null,
			'snoozed_until' => isset( $stored['snoozed_until'] ) && is_numeric( $stored['snoozed_until'] ) ? (int) $stored['snoozed_until'] : 0,
			'snoozes'       => isset( $stored['snoozes'] ) && is_numeric( $stored['snoozes'] ) ? (int) $stored['snoozes'] : 0,
			'done'          => ! empty( $stored['done'] ),
		];
	}

	/**
	 * Store the state.
	 *
	 * @param array{first_seen: int|null, last_seen: int|null, snoozed_until: int, snoozes: int, done: bool} $state The state.
	 *
	 * @return void
	 */
	private function save_state( array $state ): void {
		update_option( self::OPTION, $state, false );
	}
}
