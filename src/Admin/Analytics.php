<?php

namespace TLA_Media\GTM_Kit\Admin;

use Mixpanel;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

final class Analytics {

	/**
	 * Instance of this class
	 *
	 * @var Analytics
	 */
	public static $instance;

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * @var Util
	 */
	private $util;

	/**
	 * Constructor.
	 *
	 * @param Options $options
 	 * @param Util $util
	 */
	final public function __construct( Options $options, Util $util) {
		$this->options = $options;
		$this->util = $util;
	}

	/**
	 * Register analytics
	 */
	public static function register( Options $options, Util $util): void {
		self::$instance = $page = new static( $options, $util );

		if ( $options->get( 'general', 'analytics_active' ) ) {
			add_action( 'init', [ $page, 'schedule_daily_event' ] );
			add_action( 'gtmkit_send_anonymous_data', [ $page, 'send_anonymous_data' ] );
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
	function schedule_daily_event(): void {
		$event = 'gtmkit_send_anonymous_data';

		if (class_exists('ActionScheduler')) {
			// Schedule event with ActionScheduler
			if (!as_next_scheduled_action($event)) {
				as_schedule_recurring_action(strtotime('midnight'), DAY_IN_SECONDS, $event);
			}
		} else {
			// Schedule event with WP-Cron
			if (!wp_next_scheduled($event)) {
				wp_schedule_event(strtotime('midnight'), 'daily', $event);
			}
		}
	}

	/**
	 * Send anonymous data
	 *
	 * @return void
	 */
	function send_anonymous_data(): void {
		$mp = Mixpanel::getInstance("a84d538948ddda17265f86785c80ca37");

		$mp->track("GTM Kit", $this->util->get_site_data( $this->options->get_all_raw() ));
	}

}
