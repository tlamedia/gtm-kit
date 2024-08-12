<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Options;

/**
 * Suggested plugins
 */
final class PluginSuggestions {

	/**
	 * An instance of PluginAvailability.
	 *
	 * @var PluginAvailability
	 */
	protected PluginAvailability $plugin_availability;

	/**
	 * An instance of NotificationsHandler.
	 *
	 * @var NotificationsHandler
	 */
	private NotificationsHandler $notifications_handler;

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
		add_action( 'admin_init', [ $page, 'suggest_premium' ] );
		add_action( 'admin_init', [ $page, 'suggest_seo_plugin' ] );
		add_action( 'admin_init', [ $page, 'detect_conflicting_plugins' ] );
		add_action( 'admin_init', [ $page, 'suggest_grandfathered_wishlist' ] );
	}

	/**
	 * Suggest GTM Kit Woo Add-On.
	 *
	 * @return void
	 */
	public function suggest_premium(): void {

		$notification_id = 'gtmkit-premium-woo';

		if ( ! (
			( new WooCommerceConditional() )->is_met() &&
			! ( new PremiumConditional() )->is_met() &&
			! Options::init()->get( 'misc', 'gf_wishlist' ) === true )
		) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$add_notification = false;

		$plugins    = $this->plugin_availability->get_plugins( 'wishlist_plugins' );
		$extensions = [
			'plugin' => '',
			'theme'  => '',
		];

		foreach ( $plugins as $plugin ) {
			if ( $this->plugin_availability->is_active( $plugin ) ) {
				$extensions['plugin'] = $plugin['name'];
				$add_notification     = true;

				break;
			}
		}

		if ( \wp_get_theme()->get( 'Name' ) === 'Woodmart' || wp_get_theme()->get( 'Template' ) === 'woodmart' ) {
			$extensions['theme'] = 'woodmart';
		}

		if ( $add_notification ) {
			$notification = $this->get_premium_notification( $notification_id, $extensions );
			$this->notifications_handler->add_notification( $notification );
		} else {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
		}
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
	 * Suggest a supported SEO plugin.
	 *
	 * @return void
	 */
	public function suggest_grandfathered_wishlist(): void {
		$notification_id = 'gtmkit-gf_wishlist';

		if ( ! (
			( new WooCommerceConditional() )->is_met() &&
			! ( new PremiumConditional() )->is_met() &&
			Options::init()->get( 'misc', 'gf_wishlist' ) === true )
		) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$add_notification = false;

		if ( ! defined( 'GTMKIT_GF_WISHLIST_VERSION' ) ) {
			$plugins = $this->plugin_availability->get_plugins( 'wishlist_plugins' );

			foreach ( $plugins as $plugin ) {
				if ( $this->plugin_availability->is_active( $plugin ) && $this->plugin_availability->gf_polyfill_available( $plugin ) ) {
					$add_notification = true;

					break;
				}
			}
		}

		if ( $add_notification ) {
			$notification = $this->get_gf_wishlist_plugin_notification( $notification_id );
			$this->notifications_handler->add_notification( $notification );
		} else {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
		}
	}

	/**
	 * Build premium plugin notification.
	 *
	 * @param string                $notification_id The id of the notification to be created.
	 * @param array<string, string> $extensions The plugin data.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_premium_notification( string $notification_id, array $extensions ): Notification {

		$message = '';

		if ( ! empty( $extensions['plugin'] ) && ! empty( $extensions['theme'] ) ) {
			$message = sprintf(
			/* translators: %1$s is the name of the plugin and %2$s is the name of the theme. */
				__( 'It seems that you have installed the %1$s plugin and %1$s theme.', 'gtm-kit' ),
				$extensions['plugin'],
				$extensions['theme']
			) . ' ';
		} elseif ( ! empty( $extensions['plugin'] ) ) {
			$message = sprintf(
			/* translators: %1$s is the name of the plugin. */
				__( 'It seems that you have installed the %1$s plugin.', 'gtm-kit' ),
				$extensions['plugin'],
				$extensions['theme']
			) . ' ';
		} elseif ( ! empty( $extensions['theme'] ) ) {
			$message = sprintf(
			/* translators: %1$s is the name of the theme. */
				__( 'It seems that you have installed the %1$s theme.', 'gtm-kit' ),
				$extensions['plugin'],
				$extensions['theme']
			) . ' ';
		}

		$link     = '<a href="https://jump.gtmkit.com/link/2-30DDC" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">GTM Kit Woo Add-On</a>';
		$message .= sprintf(
		/* translators: %1$s is a link with the text 'GTM Kit Woo Add-On'. */
			__( 'With the %1$s, you can track the add_to_wishlist event and leverage server-side tracking for enhanced accuracy and deeper insights into customer behavior.', 'gtm-kit' ),
			$link,
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Upgrade:', 'gtm-kit' )
		);
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
	 * Build GF wishlist notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_gf_wishlist_plugin_notification( string $notification_id ): Notification {

		$link_1 = '<a href="https://jump.gtmkit.com/link/2-30DDC" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">GTM Kit Woo Add-On</a>';
		$link_2 = '<a href="https://jump.gtmkit.com/link/3-63585" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">Grandfathered Wishlist Functionality</a>';

		$message = sprintf(
		/* translators: %1$s and %2$s are links with the text 'GTM Kit Woo Add-On' and 'Grandfathered Wishlist Functionality' respectively. */
			__( 'Starting with GTM Kit version 2.0, the add_to_wishlist event is no longer supported in the free version of GTM Kit. To continue tracking the add_to_wishlist event, you must either purchase the %1$s or download the free %2$s plugin.', 'gtm-kit' ),
			$link_1,
			$link_2
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Breaking change:', 'gtm-kit' ),
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
