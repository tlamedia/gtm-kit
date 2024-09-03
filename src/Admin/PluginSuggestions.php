<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;

/**
 * Suggested plugins
 */
final class PluginSuggestions {

	/**
	 * An instance of PluginAvailability.
	 *
	 * @var PluginAvailability
	 */
	protected $plugin_availability;

	/**
	 * An instance of NotificationsHandler.
	 *
	 * @var NotificationsHandler
	 */
	private $notifications_handler;

	/**
	 * Constructor.
	 *
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 */
	public function __construct( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability ) {
		$this->notifications_handler = $notifications_handler;
		$this->plugin_availability   = $plugin_availability;
	}

	/**
	 * Register
	 *
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 *
	 * @return void
	 */
	public static function register( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability ): void {
		$page = new self( $notifications_handler, $plugin_availability );

		add_action( 'admin_init', [ $page->plugin_availability, 'register' ] );
		add_action( 'admin_init', [ $page, 'suggest_seo_plugin' ] );
		add_action( 'admin_init', [ $page, 'detect_conflicting_plugins' ] );
	}

	/**
	 * Suggest a supported SEO plugin.
	 *
	 * @return void
	 */
	public function suggest_seo_plugin(): void {
		if ( ! ( new WooCommerceConditional() )->is_met() && ! ( new EasyDigitalDownloadsConditional() )->is_met() ) {
			return;
		}

		$plugins = $this->plugin_availability->get_plugins( 'seo' );

		$add_notification = true;

		foreach ( $plugins as $plugin ) {
			if ( $this->plugin_availability->is_active( $plugin ) ) {
				$add_notification = false;

				break;
			}
		}

		$notification_id = 'gtmkit-suggest_seo_plugin';

		if ( $add_notification ) {
			$notification = $this->get_suggest_seo_plugin_notification( $notification_id );
			$this->notifications_handler->add_notification( $notification );
		} else {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
		}
	}

	/**
	 * Detect conflicting plugins.
	 *
	 * @return void
	 */
	public function detect_conflicting_plugins(): void {

		$plugins = $this->plugin_availability->get_plugins( 'conflicting' );

		foreach ( $plugins as $plugin ) {
			$notification_id = 'gtmkit-conflicting_plugin-' . $plugin['id'];

			if ( $this->plugin_availability->is_active( $plugin ) ) {
				$notification = $this->get_conflicting_plugin_notification( $notification_id, $plugin );
				$this->notifications_handler->add_notification( $notification );
			} else {
				$this->notifications_handler->remove_notification_by_id( $notification_id );
			}
		}
	}

	/**
	 * Build suggestion of SEO plugin notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_seo_plugin_notification( string $notification_id ): Notification {

		$message = __( 'It appears that you are not currently using a supported SEO plugin. By installing either WordPress SEO or Rank Math, you can assign a primary category to each product. This primary category will then be used in the data layer if the product is associated with multiple categories.', 'gtm-kit' );

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Plugin suggestion:', 'gtm-kit' )
		);
	}

	/**
	 * Build conflicting plugin notification.
	 *
	 * @param string                $notification_id The id of the notification to be created.
	 * @param array<string, string> $plugin The plugin data.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_conflicting_plugin_notification( string $notification_id, array $plugin ): Notification {

		$plugin_name = '<strong>' . $plugin['name'] . '</strong>';

		$message = sprintf(
			/* translators: %s is the name of the plugin. */
			__( 'It seems that you have installed the Google Tag Manager plugin called %1$s. Running two different GTM plugins simultaneously can lead to unexpected results, significantly impact data accuracy, and slow down page speed. Please consider deactivating %2$s unless you have carefully considered and addressed the potential challenges.', 'gtm-kit' ),
			$plugin_name,
			$plugin_name
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Possible Conflict:', 'gtm-kit' ),
			Notification::PROBLEM
		);
	}

	/**
	 * New notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 * @param string $message The message in the notification.
	 * @param string $header The header in the notification.
	 * @param string $type The notification type.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function new_notification( string $notification_id, string $message, string $header, string $type = Notification::NOTICE ): Notification {
		return new Notification(
			$message,
			$header,
			[
				'id'   => $notification_id,
				'type' => $type,
			]
		);
	}
}
