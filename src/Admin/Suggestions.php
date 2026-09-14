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
	 * Notice for a WooCommerce store that is not reporting its ecommerce events.
	 *
	 * @var string
	 */
	private const UPGRADE_NOTICE_WOO_ECOMMERCE = 'gtmkit-upgrade-woo-ecommerce';

	/**
	 * Notice for a site tagging server-side without server-side purchases.
	 *
	 * @var string
	 */
	private const UPGRADE_NOTICE_SERVER_SIDE = 'gtmkit-upgrade-server-side';

	/**
	 * Notice for a site that only lets GTM Kit output the container.
	 *
	 * @var string
	 */
	private const UPGRADE_NOTICE_CONTAINER_ONLY = 'gtmkit-upgrade-container-only';

	/**
	 * The contextual upgrade notices, most to least worth showing.
	 *
	 * Only ever one of these is raised at a time. A site can match more than
	 * one of them, and answering the same suggestion three times over is how a
	 * useful notice turns into noise, so the first match wins and the rest stay
	 * silent until the site no longer matches it.
	 *
	 * @var array<int, string>
	 */
	private const UPGRADE_NOTICE_ORDER = [
		self::UPGRADE_NOTICE_WOO_ECOMMERCE,
		self::UPGRADE_NOTICE_SERVER_SIDE,
		self::UPGRADE_NOTICE_CONTAINER_ONLY,
	];

	/**
	 * Where each contextual upgrade notice's call to action goes.
	 *
	 * One short link per notice, so each is attributable on its own rather than
	 * sharing a link with another surface: which of these three a reader acted
	 * on is answerable from the link alone. Where a link lands is set on the
	 * link itself, so a call to action can be repointed without shipping a
	 * release, and this file never needs to know the destination.
	 *
	 * @var array<string, string>
	 */
	private const UPGRADE_NOTICE_LINKS = [
		self::UPGRADE_NOTICE_WOO_ECOMMERCE  => 'https://jump.gtmkit.com/link/21-D190B',
		self::UPGRADE_NOTICE_SERVER_SIDE    => 'https://jump.gtmkit.com/link/22-62FD8',
		self::UPGRADE_NOTICE_CONTAINER_ONLY => 'https://jump.gtmkit.com/link/23-F490A',
	];

	/**
	 * The contextual upgrade notice this request raises, if any.
	 *
	 * Resolved once and reused: each notice asks for the winner, and the
	 * question costs an option read and a product count.
	 *
	 * @var string|null
	 */
	private ?string $active_upgrade_notice = null;

	/**
	 * Whether the active contextual upgrade notice has been resolved yet.
	 *
	 * Separate from the value itself because "no notice applies" is a real
	 * answer worth caching.
	 *
	 * @var bool
	 */
	private bool $upgrade_notice_resolved = false;

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
		add_action( 'admin_init', [ $page, 'suggest_woo_ecommerce_upgrade' ] );
		add_action( 'admin_init', [ $page, 'suggest_server_side_upgrade' ] );
		add_action( 'admin_init', [ $page, 'suggest_container_only_upgrade' ] );
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
	 * Point out that a store's ecommerce events are not being reported.
	 *
	 * @return void
	 */
	public function suggest_woo_ecommerce_upgrade(): void {
		$this->raise_upgrade_notice( self::UPGRADE_NOTICE_WOO_ECOMMERCE );
	}

	/**
	 * Point out what a server-side setup still leaves to the browser.
	 *
	 * @return void
	 */
	public function suggest_server_side_upgrade(): void {
		$this->raise_upgrade_notice( self::UPGRADE_NOTICE_SERVER_SIDE );
	}

	/**
	 * Point out what a container-only setup gives up.
	 *
	 * @return void
	 */
	public function suggest_container_only_upgrade(): void {
		$this->raise_upgrade_notice( self::UPGRADE_NOTICE_CONTAINER_ONLY );
	}

	/**
	 * Raise one contextual upgrade notice, or take it away again.
	 *
	 * @param string $notification_id The notice to raise.
	 *
	 * @return void
	 */
	private function raise_upgrade_notice( string $notification_id ): void {

		if ( $this->get_active_upgrade_notice() !== $notification_id ) {
			$this->notifications_handler->remove_notification_by_id( $notification_id );
			return;
		}

		$notification = $this->get_upgrade_notification( $notification_id );

		// The cooldown is the only thing that decides whether this notice is
		// being held back, so any dismissal the notifications system is still
		// holding is left over from the dismissal that started the cooldown and
		// has to go with it. Without this the notice comes back from its
		// cooldown already marked dismissed, and stays invisible for good: once
		// it is being raised again nothing removes it, and removal is the only
		// thing that would have cleared the stale record.
		$this->notifications_handler->clear_dismissal( $notification );

		$this->notifications_handler->add_notification( $notification );
	}

	/**
	 * Decide which contextual upgrade notice this site should see, if any.
	 *
	 * The site is matched against the notices in order and the first match
	 * wins, so a site that matches several is told the most useful thing once
	 * rather than three things at once. A win that was dismissed recently is
	 * not passed on to the next notice either: the answer to "not now" is
	 * silence, not a different suggestion on the same subject.
	 *
	 * @return string|null The notice to show, or null when none applies.
	 */
	private function get_active_upgrade_notice(): ?string {

		if ( $this->upgrade_notice_resolved ) {
			return $this->active_upgrade_notice;
		}

		$this->upgrade_notice_resolved = true;
		$this->active_upgrade_notice   = null;

		if ( ( new PremiumConditional() )->is_met() ) {
			return null;
		}

		foreach ( self::UPGRADE_NOTICE_ORDER as $notification_id ) {
			if ( ! $this->upgrade_notice_applies( $notification_id ) ) {
				continue;
			}

			if ( ! PremiumTriggerCooldown::is_within_cooldown( $notification_id ) ) {
				$this->active_upgrade_notice = $notification_id;
			}

			return $this->active_upgrade_notice;
		}

		return null;
	}

	/**
	 * Whether a site is in the situation one contextual upgrade notice is about.
	 *
	 * @param string $notification_id The notice to test the site against.
	 *
	 * @return bool True when the notice describes this site.
	 */
	private function upgrade_notice_applies( string $notification_id ): bool {

		switch ( $notification_id ) {
			case self::UPGRADE_NOTICE_WOO_ECOMMERCE:
				// A store with nothing in it is not missing any ecommerce
				// events, and the notice counts its products out loud, so it
				// stays away until there is something to count.
				return ( new WooCommerceConditional() )->is_met()
					&& ! $this->options->get( 'integrations', 'woocommerce_integration' )
					&& $this->get_published_product_count() > 0;

			case self::UPGRADE_NOTICE_SERVER_SIDE:
				// The notice is about WooCommerce orders, so a site without a
				// store has nothing it describes, whatever domain it tags from.
				return ( new WooCommerceConditional() )->is_met()
					&& $this->get_tagging_server_domain() !== '';

			case self::UPGRADE_NOTICE_CONTAINER_ONLY:
				return (bool) $this->options->get( 'general', 'just_the_container' );

			default:
				return false;
		}
	}

	/**
	 * The domain this site sends its tagging through, when it is its own.
	 *
	 * An empty setting and Google's own domain both mean the same thing: the
	 * site is not tagging server-side, so there is nothing for the notice to
	 * be true about.
	 *
	 * @return string The tagging server domain, or an empty string.
	 */
	private function get_tagging_server_domain(): string {

		$domain = trim( (string) $this->options->get( 'general', 'sgtm_domain' ) );

		return ( $domain === 'www.googletagmanager.com' ) ? '' : $domain;
	}

	/**
	 * Count the products a store has published.
	 *
	 * Counted at the moment the notice is written rather than stored, so the
	 * number a site owner reads is the number the site has. WordPress caches
	 * the count itself, so asking on each admin request is a cache read on a
	 * site with an object cache and a single counting query without one.
	 *
	 * @return int The number of published products.
	 */
	private function get_published_product_count(): int {

		$counts = \wp_count_posts( 'product' );

		if ( ! is_object( $counts ) || ! isset( $counts->publish ) ) {
			return 0;
		}

		return (int) $counts->publish;
	}

	/**
	 * Where a contextual upgrade notice's call to action goes.
	 *
	 * @param string $notification_id The notice the link belongs to.
	 *
	 * @return string The notice's destination.
	 */
	private function upgrade_notice_link( string $notification_id ): string {
		return self::UPGRADE_NOTICE_LINKS[ $notification_id ] ?? 'https://gtmkit.com/pricing/';
	}

	/**
	 * Build one contextual upgrade notification.
	 *
	 * @param string $notification_id The notice to build.
	 *
	 * @return Notification The notification.
	 */
	private function get_upgrade_notification( string $notification_id ): Notification {

		switch ( $notification_id ) {
			case self::UPGRADE_NOTICE_SERVER_SIDE:
				return $this->get_server_side_upgrade_notification( $notification_id );

			case self::UPGRADE_NOTICE_CONTAINER_ONLY:
				return $this->get_container_only_upgrade_notification( $notification_id );

			case self::UPGRADE_NOTICE_WOO_ECOMMERCE:
			default:
				return $this->get_woo_ecommerce_upgrade_notification( $notification_id );
		}
	}

	/**
	 * Build the notification for a store whose ecommerce events are switched off.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification.
	 */
	protected function get_woo_ecommerce_upgrade_notification( string $notification_id ): Notification {

		$products = $this->get_published_product_count();

		$message = sprintf(
			/* translators: %s is the number of published products in the store. */
			_n(
				'Your WooCommerce store has %s published product, but GTM Kit\'s WooCommerce integration is switched off, so purchases, add-to-carts and checkouts aren\'t reaching your data layer.',
				'Your WooCommerce store has %s published products, but GTM Kit\'s WooCommerce integration is switched off, so purchases, add-to-carts and checkouts aren\'t reaching your data layer.',
				$products,
				'gtm-kit'
			),
			\number_format_i18n( $products )
		);

		$message .= ' ' . $this->action_link(
			$this->util->get_admin_page_url() . 'general#/commerce?focus=woocommerce',
			__( 'Turn on the WooCommerce integration', 'gtm-kit' )
		);

		$message .= ' ' . $this->action_link(
			$this->upgrade_notice_link( $notification_id ),
			__( 'See what GTM Kit Premium adds', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Ecommerce events are switched off:', 'gtm-kit' )
		);
	}

	/**
	 * Build the notification for a site tagging server-side.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification.
	 */
	protected function get_server_side_upgrade_notification( string $notification_id ): Notification {

		$message = sprintf(
			/* translators: %s is the site's own tagging server domain. */
			__( "This site sends its tagging through %s, but the 'purchase' event still depends on the browser reaching your order confirmation page.", 'gtm-kit' ),
			'<strong>' . esc_html( $this->get_tagging_server_domain() ) . '</strong>'
		);

		$message .= ' ' . __( 'GTM Kit Premium reports purchases from your server, so an order counts once WooCommerce has it, whether or not the browser comes back.', 'gtm-kit' );

		$message .= ' ' . $this->action_link(
			$this->upgrade_notice_link( $notification_id ),
			__( 'See how server-side purchases work', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Server-side tagging:', 'gtm-kit' )
		);
	}

	/**
	 * Build the notification for a site that loads the container only.
	 *
	 * @param string $notification_id The id of the notification to be created.
	 *
	 * @return Notification The notification.
	 */
	protected function get_container_only_upgrade_notification( string $notification_id ): Notification {

		$message = __( 'GTM Kit is set to output your container and nothing else, so it generates none of its own events: page and post data, engagement events such as login, sign-up and search, and ecommerce tracking are all switched off.', 'gtm-kit' );

		$message .= ' ' . __( 'If your container is fed from somewhere else, this is working as you set it up and you can dismiss this notice.', 'gtm-kit' );

		$message .= ' ' . $this->action_link(
			$this->upgrade_notice_link( $notification_id ),
			__( 'See what GTM Kit can add', 'gtm-kit' )
		);

		return $this->new_notification(
			$notification_id,
			$message,
			__( 'Only the container is loaded:', 'gtm-kit' )
		);
	}

	/**
	 * Whether a notification is one of the contextual upgrade notices.
	 *
	 * Their dismissals are kept outside the notifications system, so the
	 * dismissal route has to be able to tell them apart from the rest.
	 *
	 * @param string $notification_id The id of the dismissed notification.
	 *
	 * @return bool True when the notice keeps its own dismissal record.
	 */
	public static function is_upgrade_notice( string $notification_id ): bool {
		return in_array( $notification_id, self::UPGRADE_NOTICE_ORDER, true );
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

		$message .= __( "With the GTM Kit Woo Add-On, you can track the 'add_to_wishlist' event and leverage server-side tracking for enhanced accuracy and deeper insights into customer behavior.", 'gtm-kit' );
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

		$message  = __( "Starting with GTM Kit version 2.0, the 'add_to_wishlist' event is no longer supported in the free version of GTM Kit. To continue tracking it you need either the GTM Kit Woo Add-On, or the free Grandfathered Wishlist Functionality plugin.", 'gtm-kit' );
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
	 * A second container and a repeated container are established double
	 * counting, so they are problems. A Google tag beside the container is
	 * only a duplicate when the same tag also fires inside the container,
	 * which the scan cannot see, so it is raised as a notice that does not
	 * claim duplication.
	 *
	 * @param string               $notification_id The id of the notification to be created.
	 * @param array<string, mixed> $duplicate The duplicate finding from the scan.
	 *
	 * @return Notification The notification.
	 */
	protected function get_duplicate_tracking_notification( string $notification_id, array $duplicate ): Notification {

		$containers = array_map( 'strval', (array) $duplicate['containers'] );
		$culprit    = (string) $duplicate['culprit'];
		$header     = __( 'Duplicate tracking:', 'gtm-kit' );
		$type       = Notification::PROBLEM;

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
				$header  = __( 'Tracking setup:', 'gtm-kit' );
				$type    = Notification::NOTICE;
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
			$header,
			$type
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
			$message .= ' ' . __( "The debug log for the 'purchase' event and server-side webhooks is active.", 'gtm-kit' );
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
