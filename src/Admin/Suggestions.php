<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Common\SiteEnvironment;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Suggestions
 */
final class Suggestions {

	/**
	 * The importer source each conflicting plugin's settings can be read from.
	 *
	 * Conflicting plugins with no importer are absent, so they get the plain
	 * notice without an import offer.
	 *
	 * @var array<string, string>
	 */
	private const CONFLICTING_PLUGIN_IMPORT_SOURCES = [
		'gtm4wp'                => 'gtm4wp',
		'gtm-ecommerce-woo'     => 'gtm_for_woocommerce',
		'gtm-ecommerce-woo-pro' => 'gtm_for_woocommerce',
		'wk-google-analytics'   => 'google_analytics_and_google_tag_manager',
		'google-tag-manager'    => 'google_tag_manager',
	];

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
	 * An instance of SnippetScan.
	 *
	 * @var SnippetScan
	 */
	private SnippetScan $snippet_scan;

	/**
	 * Constructor.
	 *
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 * @param Options              $options Options.
	 * @param Util                 $util Util.
	 * @param SnippetScan          $snippet_scan The tracking scan, read for evidence about the rendered page.
	 */
	public function __construct( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability, Options $options, Util $util, SnippetScan $snippet_scan ) {
		$this->notifications_handler = $notifications_handler;
		$this->plugin_availability   = $plugin_availability;
		$this->options               = $options;
		$this->util                  = $util;
		$this->snippet_scan          = $snippet_scan;
	}

	/**
	 * Register
	 *
	 * @param NotificationsHandler $notifications_handler The notifications handler to add notifications to.
	 * @param PluginAvailability   $plugin_availability Plugin Availability.
	 * @param Options              $options Options.
	 * @param Util                 $util Util.
	 * @param SnippetScan          $snippet_scan The tracking scan, read for evidence about the rendered page.
	 *
	 * @return void
	 */
	public static function register( NotificationsHandler $notifications_handler, PluginAvailability $plugin_availability, Options $options, Util $util, SnippetScan $snippet_scan ): void {
		$page = new self( $notifications_handler, $plugin_availability, $options, $util, $snippet_scan );

		add_action( 'admin_init', [ $page->plugin_availability, 'register' ] );
		add_action( 'admin_init', [ $page, 'suggest_auto_update' ] );
		add_action( 'admin_init', [ $page, 'suggest_premium' ] );
		add_action( 'admin_init', [ $page, 'suggest_seo_plugin' ] );
		add_action( 'admin_init', [ $page, 'detect_conflicting_plugins' ] );
		add_action( 'admin_init', [ $page, 'suggest_grandfathered_wishlist' ] );
		add_action( 'admin_init', [ $page, 'report_suppressed_container' ] );
		add_action( 'admin_init', [ $page, 'suggest_declaring_a_copy' ] );
		add_action( 'admin_init', [ $page, 'suggest_container_injection' ] );
		add_action( 'admin_init', [ $page, 'suggest_duplicate_tracking' ] );
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
	 * Report that the container is withheld because of what the site reports.
	 *
	 * A site that declared itself as staging, development or local and used
	 * to track anyway stops tracking after this update. That is the intended
	 * correction, but it must never look like a malfunction, so the reason is
	 * stated where the site owner works and the setting that reverses it is
	 * one click away.
	 *
	 * @return void
	 */
	public function report_suppressed_container(): void {

		$notification_id = 'gtmkit-site-not-production';

		if ( ! SiteEnvironment::suppresses_container( $this->options ) ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_suppressed_container_notification( $notification_id );
		$this->notifications_handler->add_notification( $notification );
	}

	/**
	 * Suggest that a site whose address reads like a copy says so.
	 *
	 * Informational only: a guess about the address never changes what GTM
	 * Kit outputs, and a site that explicitly declared what it is has already
	 * answered the question, so it is never asked again.
	 *
	 * @return void
	 */
	public function suggest_declaring_a_copy(): void {

		$notification_id = 'gtmkit-undeclared-copy';

		if (
			SiteEnvironment::is_declared()
			|| ! SiteEnvironment::is_production()
			|| ! SiteEnvironment::url_looks_like_a_copy()
		) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_declare_a_copy_notification( $notification_id );
		$this->notifications_handler->add_notification( $notification );
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

		if ( ( $container_active && $gtm_id ) || SiteEnvironment::get_type() === 'local' ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_suggest_container_injection_notification( $notification_id, $container_active, $gtm_id );
		$this->notifications_handler->add_notification( $notification );
	}

	/**
	 * Report that the site loads tracking more than once.
	 *
	 * Raised only from a scan that positively identified more than one
	 * implementation in the page the server sends. A scan that could not be
	 * completed proves nothing and raises nothing.
	 *
	 * @return void
	 */
	public function suggest_duplicate_tracking(): void {

		$notification_id = 'gtmkit-duplicate-tracking';

		$container_active = ( $this->options->get( 'general', 'container_active' ) && apply_filters( 'gtmkit_container_active', true ) );
		$result           = $this->snippet_scan->get_result();

		if (
			! $container_active
			|| $result === null
			|| $result['state'] !== SnippetScan::STATE_FOUND
			|| $result['duplicate']['type'] === ''
		) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_duplicate_tracking_notification( $notification_id, $result['duplicate'] );
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

		if ( ( ! $console_log && ! $debug_og ) || SiteEnvironment::get_type() === 'local' ) {
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

		// Match by directory name only. See BricksConditional::is_met() for why
		// `get( 'Name' )` is avoided here.
		$theme = \wp_get_theme();
		if ( $theme->get_stylesheet() === 'woodmart' || $theme->get_template() === 'woodmart' ) {
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

		$message .= __( 'With the GTM Kit Woo Add-On, you can track the add_to_wishlist event and leverage server-side tracking for enhanced accuracy and deeper insights into customer behavior.', 'gtm-kit' );
		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'upgrades',
			__( 'Get the GTM Kit Woo Add-On', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Upgrade:', 'gtm-kit' )
		);
	}

	/**
	 * Build a notification's action link.
	 *
	 * Links belong at the end of a message, after a sentence that reads
	 * correctly without them: the dashboard lifts every link out of the message
	 * and renders it as an action button beside the notification, so a link left
	 * mid-sentence leaves a hole in the text it was part of.
	 *
	 * @param string $url The link target.
	 * @param string $label The link text.
	 *
	 * @return string The anchor markup.
	 */
	private function action_link( string $url, string $label ): string {
		return '<a href="' . esc_url( $url ) . '" class="gtmkit-text-color-primary gtmkit hover:gtmkit-underline gtmkit-font-bold">'
			. esc_html( $label )
			. '</a>';
	}

	/**
	 * Build suggestion of SEO plugin notification.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification containing the suggested plugin.
	 */
	protected function get_suggest_seo_plugin_notification( string $notification_id ): Notification {

		// phpcs:ignore -- "Rank" here is part of the SEO plugin name "Rank Math" in a translatable UI string, not an SQL identifier; the marketplace SQL reserved-word scan false-positives on the word.
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

		$import_offer = $this->get_conflicting_plugin_import_offer( $plugin );

		if ( '' !== $import_offer ) {
			$message .= ' ' . $import_offer;
		}

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Possible Conflict:', 'gtm-kit' ),
			Notification::PROBLEM
		);
	}

	/**
	 * Build the import offer appended to a conflicting plugin's notification.
	 *
	 * Returns an empty string when the plugin has no importer, or has one but
	 * stored nothing worth importing, so the notice never links to an import
	 * that would find no settings.
	 *
	 * @param array<string, string> $plugin The plugin data.
	 *
	 * @return string The sentence offering the import, or an empty string.
	 */
	private function get_conflicting_plugin_import_offer( array $plugin ): string {

		$source = self::CONFLICTING_PLUGIN_IMPORT_SOURCES[ $plugin['id'] ] ?? '';

		if ( '' === $source || empty( ( new PluginDataImport() )->get( $source ) ) ) {
			return '';
		}

		return __( 'Before you deactivate it, you can copy its configuration into GTM Kit.', 'gtm-kit' )
			. ' ' . $this->action_link(
				$this->util->get_admin_page_url() . 'general#/tools?focus=import',
				sprintf(
					/* translators: %s is the name of the conflicting plugin. */
					__( 'Import settings from %s', 'gtm-kit' ),
					$plugin['name']
				)
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

		$message  = __( 'Starting with GTM Kit version 2.0, the add_to_wishlist event is no longer supported in the free version of GTM Kit. To continue tracking it you need either the GTM Kit Woo Add-On, or the free Grandfathered Wishlist Functionality plugin.', 'gtm-kit' );
		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'upgrades',
			__( 'Get the GTM Kit Woo Add-On', 'gtm-kit' )
		);
		$message .= ' ' . $this->action_link(
			'https://jump.gtmkit.com/link/3-63585',
			__( 'Get the free plugin', 'gtm-kit' )
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

		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'general#/misc',
			__( 'Go to settings', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Automatic Updates:', 'gtm-kit' )
		);
	}

	/**
	 * Build the notification for a container withheld because of what the site reports.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification.
	 */
	protected function get_suppressed_container_notification( string $notification_id ): Notification {

		$message = sprintf(
			/* translators: %s is the kind of site WordPress reports, for example "staging", "development" or "local". */
			__( 'WordPress reports this site as %s, so GTM Kit leaves your Google Tag Manager container out of its pages and traffic from here stays out of your analytics and advertising audiences. The data layer is still built, so you can see what a live site would send.', 'gtm-kit' ),
			'<strong>' . esc_html( SiteEnvironment::get_type() ) . '</strong>'
		);

		$message .= ' ' . __( 'If you measure this site on purpose, you can load the container here as well.', 'gtm-kit' );

		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'general#/container',
			__( 'Go to the container settings', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Container not loaded here:', 'gtm-kit' )
		);
	}

	/**
	 * Build the notification suggesting an undeclared copy says what it is.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification.
	 */
	protected function get_declare_a_copy_notification( string $notification_id ): Notification {

		$message = __( 'The address of this site reads like a test or staging copy, but WordPress has not been told what kind of site this is and treats it as the real one. GTM Kit therefore loads your Google Tag Manager container here, and anything done on this site is measured together with your real traffic.', 'gtm-kit' );

		$message .= ' ' . sprintf(
			/* translators: %s is the name of a PHP constant, wrapped in code tags. */
			__( 'If this is a copy, set %s in wp-config.php and GTM Kit will leave the container out by itself.', 'gtm-kit' ),
			'<code>WP_ENVIRONMENT_TYPE</code>'
		);

		$message .= ' ' . $this->action_link(
			'https://developer.wordpress.org/apis/wp-config-php/#wp-environment-type',
			__( 'Read how to declare it', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Is this a copy of your site?', 'gtm-kit' )
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

		$cmp_explains = ( '' !== $gtm_id && CMPDetection::detect_active_cmp() !== null );
		$evidence     = $this->get_empty_page_evidence( $gtm_id );
		$type         = Notification::PROBLEM;

		if ( $evidence !== '' ) {
			$message .= ' ' . $evidence;

			if ( $cmp_explains || SiteEnvironment::suppresses_container( $this->options ) ) {
				// A container missing from the server's HTML is the intended
				// arrangement, not a fault, when a consent platform loads a
				// saved container in the browser instead or when the site
				// reports itself as somewhere the container does not belong.
				// Either way the finding is stated and not flagged. A site
				// that has saved no container ID at all is neither: nothing
				// is loading it anywhere, so that stays a problem.
				$type = Notification::NOTICE;
			}
		}

		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'general#/container',
			__( 'Go to settings', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'GTM Container Injection:', 'gtm-kit' ),
			$type
		);
	}

	/**
	 * Build the notification for a page that loads tracking more than once.
	 *
	 * @param string               $notification_id The id of the notification to be created.
	 * @param array<string, mixed> $duplicate The duplicate finding from the scan.
	 *
	 * @return Notification The notification.
	 */
	protected function get_duplicate_tracking_notification( string $notification_id, array $duplicate ): Notification {

		$containers = array_map( 'strval', (array) $duplicate['containers'] );
		$culprit    = (string) $duplicate['culprit'];

		switch ( (string) $duplicate['type'] ) {
			case SnippetScan::DUPLICATE_CONTAINERS:
				$message = sprintf(
					/* translators: %s is a comma-separated list of Google Tag Manager container IDs. */
					__( 'Your pages load more than one Google Tag Manager container: %s. Each container fires its own tags, so the same page views, events and purchases are counted more than once.', 'gtm-kit' ),
					implode( ', ', $containers )
				);
				break;

			case SnippetScan::DUPLICATE_GTAG:
				$message = __( 'Your pages load a Google tag directly as well as your Google Tag Manager container. If the same tag also fires inside the container, its data is collected twice.', 'gtm-kit' );
				break;

			case SnippetScan::DUPLICATE_REPEATED:
			default:
				$message = ( $containers !== [] )
					? sprintf(
						/* translators: %s is a Google Tag Manager container ID. */
						__( 'Your pages load the Google Tag Manager container %s more than once. Everything the container measures, purchases included, is counted twice.', 'gtm-kit' ),
						$containers[0]
					)
					: __( 'Your pages load a Google Tag Manager container more than once. Everything the container measures, purchases included, is counted twice.', 'gtm-kit' );
				break;
		}

		$message .= ' ' . (
			( $culprit !== '' )
			? sprintf(
				/* translators: %s is the name of a plugin or tool that also adds tracking code. */
				__( 'The extra tracking code appears to come from %s.', 'gtm-kit' ),
				$culprit
			)
			: __( 'GTM Kit could not tell what adds the extra tracking code. Your theme and any plugin that inserts code into the page header are the usual places to look.', 'gtm-kit' )
		);

		$message .= ' ' . $this->action_link(
			admin_url( 'site-health.php' ),
			__( 'See the details in Site Health', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Duplicate tracking:', 'gtm-kit' ),
			Notification::PROBLEM
		);
	}

	/**
	 * State what a scan of the site's own page found, when it found nothing.
	 *
	 * The daily scan requests a sample page the way a visitor receives it, so
	 * it can say something the settings screen cannot: whether anything at
	 * all is loading a container. It can only report on the HTML the server
	 * sends, so the wording stops there and never claims that nothing loads
	 * in the browser. A consent platform is exactly the case where the two
	 * differ, so its presence is stated alongside the finding. A site that
	 * reports itself as somewhere the container does not belong is the other
	 * expected absence, and is answered first because it explains the empty
	 * page outright.
	 *
	 * Returns an empty string unless a stored scan positively found no
	 * implementation: a failed, blocked or never-run scan is evidence of
	 * nothing and must add nothing.
	 *
	 * A consent platform only explains the absence on a site that has a
	 * container for it to load. With no ID saved there is nothing to load,
	 * so the finding is reported plainly instead.
	 *
	 * @param string $gtm_id The saved Google Tag Manager container ID.
	 *
	 * @return string The evidence sentence, or an empty string.
	 */
	private function get_empty_page_evidence( string $gtm_id ): string {

		$result = $this->snippet_scan->get_result();

		if ( $result === null || $result['state'] !== SnippetScan::STATE_NOT_FOUND ) {
			return '';
		}

		if ( SiteEnvironment::suppresses_container( $this->options ) ) {
			return sprintf(
				/* translators: %s is the kind of site WordPress reports, for example "staging", "development" or "local". */
				__( 'A check of your site found no container in the page your server sends, which is what to expect while WordPress reports this site as %s.', 'gtm-kit' ),
				SiteEnvironment::get_type()
			);
		}

		$cmp = ( '' !== $gtm_id ) ? CMPDetection::detect_active_cmp() : null;

		if ( $cmp !== null ) {
			return sprintf(
				/* translators: %s is the name of the detected consent platform, for example "Cookiebot". */
				__( 'A check of your site found no container in the page your server sends, which is what to expect when %s loads it in the browser instead.', 'gtm-kit' ),
				CMPDetection::get_display_name( $cmp )
			);
		}

		return __( 'A check of your site found no Google Tag Manager container in the page your server sends, and no consent platform that would load one later, so nothing appears to be measuring this site.', 'gtm-kit' );
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
			$message .= ' ' . __( 'The debug log for the purchase event and server-side webhooks is active.', 'gtm-kit' );
		}

		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'general#/misc',
			__( 'Go to settings', 'gtm-kit' )
		);

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
