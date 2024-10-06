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
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * Suggestions
 */
final class Suggestions {

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
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 * @param Options              $options Options.
	 * @param Util                 $util Util.
	 */
	public function __construct( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability, Options $options, Util $util ) {
		$this->notifications_handler = $notifications_handler;
		$this->plugin_availability   = $plugin_availability;
		$this->options               = $options;
		$this->util                  = $util;
	}

	/**
	 * Register
	 *
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 * @param Options              $options Options.
	 * @param Util                 $util Util.
	 *
	 * @return void
	 */
	public static function register( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability, Options $options, Util $util ): void {
		$page = new self( $notifications_handler, $plugin_availability, $options, $util );

		add_action( 'admin_init', [ $page->plugin_availability, 'register' ] );
		add_action( 'admin_init', [ $page, 'suggest_auto_update' ] );
		add_action( 'admin_init', [ $page, 'suggest_premium' ] );
		add_action( 'admin_init', [ $page, 'suggest_seo_plugin' ] );
		add_action( 'admin_init', [ $page, 'detect_conflicting_plugins' ] );
		add_action( 'admin_init', [ $page, 'suggest_grandfathered_wishlist' ] );
		add_action( 'admin_init', [ $page, 'suggest_inspector_deactivation' ] );
		add_action( 'admin_init', [ $page, 'suggest_container_injection' ] );
		add_action( 'admin_init', [ $page, 'suggest_log_deactivation' ] );
	}

	/**
	 * Suggest Auto-update.
	 *
	 * @return void
	 */
	public function suggest_auto_update(): void {

		$notification_id = 'gtmkit-auto-update';

		if ( $this->options->get( 'misc', 'auto_update' ) === true || ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_suggest_auto_update_notification( $notification_id );
		$this->notifications_handler->add_notification( $notification );
	}

	/**
	 * Suggest event inspector deactivation.
	 *
	 * @return void
	 */
	public function suggest_inspector_deactivation(): void {

		$notification_id = 'gtmkit-event-inspector';

		if ( $this->options->get( 'general', 'event_inspector' ) === false || ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' ) ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		if ( $this->options->get( 'general', 'event_inspector' ) === true ) {
			$notification = $this->get_suggest_inspector_deactivation_notification( $notification_id );
			$this->notifications_handler->add_notification( $notification );
		}
	}

	/**
	 * Suggest container injection
	 *
	 * @return void
	 */
	public function suggest_container_injection(): void {

		$notification_id = 'gtmkit-container-injection';

		$container_active = ( $this->options->get( 'general', 'container_active' ) && apply_filters( 'gtmkit_container_active', true ) );
		$gtm_id           = $this->options->get( 'general', 'gtm_id' );

		if ( ( $container_active && $gtm_id ) || ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' ) ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_suggest_container_injection_notification( $notification_id, $container_active, $gtm_id );
		$this->notifications_handler->add_notification( $notification );
	}

	/**
	 * Suggest container injection
	 *
	 * @return void
	 */
	public function suggest_log_deactivation(): void {

		$notification_id = 'gtmkit-log-active';

		$console_log = $this->options->get( 'general', 'console_log' );
		$debug_og    = $this->options->get( 'general', 'debug_log' );

		if ( ( ! $console_log && $debug_og ) || ( defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local' ) ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_suggest_log_deactivation_notification( $notification_id, $console_log, $debug_og );
		$this->notifications_handler->add_notification( $notification );
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
			! $this->options->get( 'misc', 'gf_wishlist' ) === true )
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
			$this->options->get( 'misc', 'gf_wishlist' ) === true )
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

		$url      = $this->util->get_admin_page_url() . 'upgrades';
		$link     = '<a href="' . $url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">GTM Kit Woo Add-On</a>';
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

		$upgrades_url = $this->util->get_admin_page_url() . 'upgrades';
		$link_1       = '<a href="' . $upgrades_url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">GTM Kit Woo Add-On</a>';
		$link_2       = '<a href="https://jump.gtmkit.com/link/3-63585" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">Grandfathered Wishlist Functionality</a>';

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
	 * Build suggestion of auto-update notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_auto_update_notification( string $notification_id ): Notification {

		$message = __( 'New releases of GTM Kit may contain important updates to comply with changes in Google Tag Manager or analytics in general. We recommend enabling automatic plugin updates for GTM Kit to ensure it is always up to date.', 'gtm-kit' );

		$url      = $this->util->get_admin_page_url() . 'general#/misc';
		$message .= ' <a href="' . $url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">';
		$message .= __( 'Go to settings', 'gtm-kit' );
		$message .= '</a>';

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Automatic Updates:', 'gtm-kit' )
		);
	}

	/**
	 * Build suggestion of inspector deactivation notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_inspector_deactivation_notification( string $notification_id ): Notification {

		$message = __( 'The event inspector is active and visible to all users. You should not keep it active longer than necessary.', 'gtm-kit' );

		$url      = $this->util->get_admin_page_url() . 'general#/misc';
		$message .= ' <a href="' . $url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">';
		$message .= __( 'Go to settings', 'gtm-kit' );
		$message .= '</a>';

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Event Inspector:', 'gtm-kit' ),
			Notification::PROBLEM
		);
	}


	/**
	 * Build suggestion of container injection notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 * @param bool   $container_active The container activation status.
	 * @param string $gtm_id The GTM Container ID.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_container_injection_notification( string $notification_id, bool $container_active, string $gtm_id ): Notification {

		$message = __( 'The Google Tag Manager container is not injected.', 'gtm-kit' );

		if ( ! $container_active ) {
			$message .= ' ' . __( 'The "Inject Container Code" option is not enabled.', 'gtm-kit' );
		}

		if ( ! $gtm_id ) {
			$message .= ' ' . __( 'The "GTM Container ID" value is empty.', 'gtm-kit' );
		}

		$url      = $this->util->get_admin_page_url() . 'general#/container';
		$message .= ' <a href="' . $url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">';
		$message .= __( 'Go to settings', 'gtm-kit' );
		$message .= '</a>';

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'GTM Container Injection:', 'gtm-kit' ),
			Notification::PROBLEM
		);
	}

	/**
	 * Build suggestion of container injection notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 * @param bool   $console_log Console log activation status.
	 * @param bool   $debug_log Debug log activation status.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_log_deactivation_notification( string $notification_id, bool $console_log, bool $debug_log ): Notification {

		$message = __( 'Debug logging should not be active in production environments longer than necessary as it affects performance.', 'gtm-kit' );

		if ( $console_log ) {
			$message .= ' ' . __( 'The browser console log is active.', 'gtm-kit' );
		}

		if ( $debug_log ) {
			$message .= ' ' . __( 'The debug log for "purchase" events is active.', 'gtm-kit' );
		}

		$url      = $this->util->get_admin_page_url() . 'general#/misc';
		$message .= ' <a href="' . $url . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">';
		$message .= __( 'Go to settings', 'gtm-kit' );
		$message .= '</a>';

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Logging and debugging:', 'gtm-kit' ),
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
