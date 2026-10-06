<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Tells the site owner when the loader Stape issues is switched on but none is stored.
 *
 * Without a stored loader the pages load Google Tag Manager the standard way,
 * which keeps tracking running and looks exactly like a working setup. The
 * setting reads as on, so an owner who never opens the Server-side Tagging
 * screen has no way to know, which is why this is a notice across the admin
 * rather than a readout on that screen.
 *
 * The condition is read from the current state rather than from a record of
 * how the site got there, so it covers a loader that was removed because it
 * could not be trusted, an upgrade that removed it, and a first switch-on
 * before any loader was fetched, all the same way.
 *
 * The notice exists only while that state does, so it carries its own
 * dismissal rather than leaving it to the notifications system, whose record
 * covers only notifications currently in the set. The dismissal is cleared as
 * soon as the state ends, so if it returns later the owner is told again.
 */
final class StapeLoaderNotice {

	/**
	 * The notification id.
	 *
	 * @var string
	 */
	public const NOTIFICATION_ID = 'gtmkit-sgtm-loader-missing';

	/**
	 * The option recording that the notice was dismissed while the state lasts.
	 *
	 * @var string
	 */
	public const DISMISSED_OPTION = 'gtmkit_sgtm_loader_notice_dismissed';

	/**
	 * An instance of StapeLoader.
	 *
	 * @var StapeLoader
	 */
	private StapeLoader $loader;

	/**
	 * An instance of NotificationsHandler.
	 *
	 * @var NotificationsHandler
	 */
	private NotificationsHandler $notifications_handler;

	/**
	 * Constructor.
	 *
	 * @param StapeLoader          $loader An instance of StapeLoader.
	 * @param NotificationsHandler $notifications_handler An instance of NotificationsHandler.
	 */
	public function __construct( StapeLoader $loader, NotificationsHandler $notifications_handler ) {
		$this->loader                = $loader;
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

		$instance = new self( new StapeLoader( $options ), NotificationsHandler::get() );

		add_action( 'admin_init', [ $instance, 'evaluate' ], 20 );
	}

	/**
	 * Whether the loader is switched on and set up, but none is stored.
	 *
	 * @return bool
	 */
	public function is_due(): bool {
		return $this->loader->is_enabled() && null === $this->loader->get_stored();
	}

	/**
	 * Add or withdraw the notice to match the current state.
	 *
	 * @return void
	 */
	public function evaluate(): void {

		if ( ! $this->is_due() ) {
			$this->notifications_handler->remove_notification_by_id( self::NOTIFICATION_ID );

			// The state has ended, so a dismissal given while it lasted no
			// longer applies. If it returns, the owner is told again.
			delete_option( self::DISMISSED_OPTION );

			return;
		}

		if ( self::is_dismissed() ) {
			$this->notifications_handler->remove_notification_by_id( self::NOTIFICATION_ID );

			return;
		}

		$this->notifications_handler->add_notification( $this->build() );
	}

	/**
	 * Remember that the notice was dismissed.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		update_option( self::DISMISSED_OPTION, true, false );
	}

	/**
	 * Whether the notice has been dismissed while the current state lasts.
	 *
	 * @return bool
	 */
	public static function is_dismissed(): bool {
		return (bool) get_option( self::DISMISSED_OPTION, false );
	}

	/**
	 * Build the notice.
	 *
	 * @return Notification
	 */
	private function build(): Notification {

		// The dashboard lifts every link out of the message and renders it as
		// an action button beside the notice, so the link goes last and the
		// sentences before it are joined with a space to read correctly
		// without it.
		$message = esc_html__( 'GTM Kit has no loader from Stape for this site, so your pages load Google Tag Manager the standard way. Tracking continues.', 'gtm-kit' )
			. ' ' . esc_html__( 'To use the loader Stape issues, open Server-side Tagging and click Refresh.', 'gtm-kit' )
			. ' <a href="' . esc_url( admin_url( 'admin.php?page=gtmkit_general#/setup?focus=sgtm' ) ) . '">' . esc_html__( 'Open Server-side Tagging', 'gtm-kit' ) . '</a>';

		return new Notification(
			$message,
			esc_html__( 'Your pages are using the standard Server-side Tagging loader', 'gtm-kit' ),
			[
				'id'   => self::NOTIFICATION_ID,
				'type' => Notification::PROBLEM,
			]
		);
	}
}
