<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * Analytics
 */
final class Analytics {

	/**
	 * Instance of this class
	 *
	 * @var Analytics
	 */
	public static Analytics $instance;

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Utility
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
	 * Register analytics
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public static function register( Options $options, Util $util ): void {
		self::$instance = new Analytics( $options, $util );

		if ( $options->get( 'general', 'analytics_active' ) ) {
			add_action( 'init', [ self::$instance, 'schedule_daily_event' ] );
			add_action( 'gtmkit_send_anonymous_data', [ self::$instance, 'send_anonymous_data' ] );
		}
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Analytics
	 */
	public static function get_instance(): Analytics {
		return self::$instance;
	}

	/**
	 * Schedule daily event
	 *
	 * @return void
	 */
	public function schedule_daily_event(): void {
		$event = 'gtmkit_send_anonymous_data';

		if ( class_exists( 'ActionScheduler' ) ) {
			// Schedule event with ActionScheduler.
			if ( ! as_next_scheduled_action( $event ) ) {
				as_schedule_single_action( strtotime( 'midnight +25 hours' ), $event, [], 'gtmkit' );
			}
		} elseif ( ! wp_next_scheduled( $event ) ) {
			// Schedule event with WP-Cron.
			wp_schedule_event( strtotime( 'midnight' ), 'daily', $event );
		}
	}

	/**
	 * Send anonymous data
	 *
	 * @return void
	 */
	public function send_anonymous_data(): void {

		$data          = $this->util->get_site_data( $this->options->get_all_raw() );
		$data['token'] = 'a84d538948ddda17265f86785c80ca37';

		$events = [
			[
				'event'      => 'GTM Kit',
				'properties' => $data,
			],
		];

		\wp_remote_post(
			'https://api.mixpanel.com/track',
			[
				'headers'     => [
					'Content-Type' => 'application/json',
				],
				'body'        => \wp_json_encode( $events ),
				'method'      => 'POST',
				'data_format' => 'body',
			]
		);
	}
}
