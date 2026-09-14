<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\GoogleTagGateway;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Tells the site owner when the Google tag gateway asked for is not the one running.
 *
 * The gateway falls back to the standard loader rather than stopping when its
 * checks fail, so tracking keeps working. That is the right behaviour and it is
 * also the dangerous one: nothing on the site looks different, so without this
 * notice the setting would read as on while the container was being served the
 * old way, possibly for months.
 *
 * The notice exists only while that mismatch does, which is why it carries its
 * own dismissal rather than leaving it to the notifications system. That record
 * covers notifications currently in the set, and a notice held back by its own
 * dismissal is not one of them. The dismissal is cleared as soon as the gateway
 * recovers, so a later outage is reported again instead of being silently
 * covered by a dismissal from the previous one.
 */
final class GoogleTagGatewayNotice {

	/**
	 * The notification id.
	 *
	 * @var string
	 */
	public const NOTIFICATION_ID = 'gtmkit-google-tag-gateway-fallback';

	/**
	 * An instance of GoogleTagGateway.
	 *
	 * @var GoogleTagGateway
	 */
	private GoogleTagGateway $gateway;

	/**
	 * An instance of NotificationsHandler.
	 *
	 * @var NotificationsHandler
	 */
	private NotificationsHandler $notifications_handler;

	/**
	 * Constructor.
	 *
	 * @param GoogleTagGateway     $gateway An instance of GoogleTagGateway.
	 * @param NotificationsHandler $notifications_handler An instance of NotificationsHandler.
	 */
	public function __construct( GoogleTagGateway $gateway, NotificationsHandler $notifications_handler ) {
		$this->gateway               = $gateway;
		$this->notifications_handler = $notifications_handler;
	}

	/**
	 * Register the notice.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {

		if ( ! is_admin() ) {
			return;
		}

		$instance = new self( new GoogleTagGateway( $options ), NotificationsHandler::get() );

		// Priority 20 so the daily check's scheduling and any freshly stored
		// result on this request are already in place when the state is read.
		add_action( 'admin_init', [ $instance, 'evaluate' ], 20 );
	}

	/**
	 * Add or withdraw the notice to match the current state.
	 *
	 * @return void
	 */
	public function evaluate(): void {

		if ( ! $this->gateway->is_falling_back() ) {
			$this->notifications_handler->remove_notification_by_id( self::NOTIFICATION_ID );

			return;
		}

		if ( GoogleTagGatewayHealth::is_notice_dismissed() ) {
			$this->notifications_handler->remove_notification_by_id( self::NOTIFICATION_ID );

			return;
		}

		$this->notifications_handler->add_notification( $this->build() );
	}

	/**
	 * Build the notice.
	 *
	 * @return Notification
	 */
	private function build(): Notification {

		// The dashboard lifts every link out of the message and renders it as
		// an action button beside the notice, so the link goes last and the
		// sentences before it have to read correctly without it. Written as one
		// space-joined string for the same reason: paragraph markup contributes
		// no separator once the tags are gone, and the two sentences would run
		// together.
		$message = esc_html__( 'You have switched on serving the Google tag from your own domain, but it is not currently working on this site, so GTM Kit has gone back to loading the container the standard way. Your tracking is still running.', 'gtm-kit' )
			. ' ' . esc_html__( 'Site Health shows which of the two checks is failing and what to ask your host.', 'gtm-kit' )
			. ' <a href="' . esc_url( admin_url( 'site-health.php' ) ) . '">' . esc_html__( 'Open Site Health', 'gtm-kit' ) . '</a>';

		return new Notification(
			$message,
			esc_html__( 'The Google tag is not being served from your domain', 'gtm-kit' ),
			[
				'id'   => self::NOTIFICATION_ID,
				'type' => Notification::PROBLEM,
			]
		);
	}
}
