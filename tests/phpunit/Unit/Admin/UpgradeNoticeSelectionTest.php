<?php
/**
 * Unit tests for the contextual upgrade notices on the dashboard.
 *
 * Three notices describe three situations a free site can be in, and a site
 * can be in more than one of them at once. What has to hold is that the site
 * owner is told the most useful of them once, that saying "not now" is
 * answered with silence rather than with the next suggestion down the list,
 * and that every number a notice states is the site's own.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\Suggestions}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use ReflectionMethod;
use TLA_Media\GTM_Kit\Admin\Notification;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\PluginAvailability;
use TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown;
use TLA_Media\GTM_Kit\Admin\Suggestions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Covers which contextual upgrade notice a site is shown, and what it says.
 *
 * Every test runs in its own process. Whether WooCommerce and Premium are
 * loaded is answered by `function_exists`, so the stub that makes one of them
 * present for a test cannot be taken away again for the next test in the same
 * process, and the tests would otherwise decide each other's answers.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UpgradeNoticeSelectionTest extends TestCase {

	/**
	 * The notice about a store that is not reporting its ecommerce events.
	 *
	 * @var string
	 */
	private const WOO_ECOMMERCE = 'gtmkit-upgrade-woo-ecommerce';

	/**
	 * The notice about a site tagging through its own server.
	 *
	 * @var string
	 */
	private const SERVER_SIDE = 'gtmkit-upgrade-server-side';

	/**
	 * The notice about a site that outputs the container and nothing else.
	 *
	 * @var string
	 */
	private const CONTAINER_ONLY = 'gtmkit-upgrade-container-only';

	/**
	 * Option values present on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * The number of published products the stubbed count reports.
	 *
	 * @var int
	 */
	private int $products = 0;

	/**
	 * Stub every WordPress function the notices and their dependencies touch.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'GTMKIT_PATH' ) ) {
			define( 'GTMKIT_PATH', '/fake/plugin/path/' );
		}
		if ( ! defined( 'GTMKIT_URL' ) ) {
			define( 'GTMKIT_URL', 'https://example.test/wp-content/plugins/gtm-kit/' );
		}
		if ( ! defined( 'GTMKIT_VERSION' ) ) {
			define( 'GTMKIT_VERSION', '2.19.0' );
		}
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', '/fake/plugins' );
		}
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			// A WordPress core constant the cooldown reads, not a plugin one.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standing in for core in a bare PHPUnit process.
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$this->stored   = [];
		$this->products = 0;

		Functions\stubs(
			[
				'__'                  => null,
				'esc_html'            => null,
				'esc_url'             => null,
				'sanitize_key'        => static fn( $key ) => strtolower( (string) $key ),
				'admin_url'           => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'network_admin_url'   => static fn( $path = '' ) => 'https://example.test/wp-admin/network/' . $path,
				'number_format_i18n'  => static fn( $number ) => (string) $number,
				'is_network_admin'    => false,
				'is_multisite'        => false,
				'is_plugin_active'    => false,
				'get_current_user_id' => 1,
			]
		);

		Functions\when( '_n' )->alias(
			static fn( $single, $plural, $number ) => ( (int) $number === 1 ) ? $single : $plural
		);

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->stored[ $name ] = $value;

				return true;
			}
		);

		Functions\when( 'wp_count_posts' )->alias(
			fn() => (object) [ 'publish' => $this->products ]
		);

		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = [] ) => array_merge( (array) $defaults, (array) $args )
		);
	}

	/**
	 * Put the site in the situation the WooCommerce notice describes.
	 *
	 * @param int $products The number of published products the store has.
	 *
	 * @return void
	 */
	private function given_a_store_not_reporting_ecommerce( int $products = 12 ): void {
		Functions\when( 'WC' )->justReturn( true );

		$this->products = $products;

		$this->stored['gtmkit']['integrations']['woocommerce_integration'] = false;
	}

	/**
	 * Put the site in the situation the server-side notice describes.
	 *
	 * @param string $domain The site's own tagging server domain.
	 *
	 * @return void
	 */
	private function given_tagging_through_an_own_server( string $domain = 'sgtm.example.test' ): void {
		Functions\when( 'WC' )->justReturn( true );

		$this->stored['gtmkit']['general']['sgtm_domain'] = $domain;
	}

	/**
	 * A site without WooCommerce is not told about WooCommerce orders.
	 *
	 * The server-side notice describes how a WooCommerce purchase is reported,
	 * so on a blog or another shop it is simply untrue, and as the first match
	 * it would also hide the notice that does apply. Runs in a process of its
	 * own, because the store situations define the `WC` function WooCommerce's
	 * presence is read from, and a defined function stays defined.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_a_site_without_woocommerce_is_not_told_about_its_orders(): void {
		$this->stored['gtmkit']['general']['sgtm_domain'] = 'sgtm.example.test';

		$this->assertFalse( function_exists( 'WC' ) );
		$this->assertNull( $this->active_notice() );

		$this->given_only_the_container_is_loaded();

		$this->assertSame( self::CONTAINER_ONLY, $this->active_notice() );
	}

	/**
	 * Put the site in the situation the container-only notice describes.
	 *
	 * @return void
	 */
	private function given_only_the_container_is_loaded(): void {
		$this->stored['gtmkit']['general']['just_the_container'] = true;
	}

	/**
	 * Record that a notice was dismissed a given number of days ago.
	 *
	 * @param string $notification_id The dismissed notice.
	 * @param int    $days How long ago it was dismissed.
	 *
	 * @return void
	 */
	private function given_dismissed_days_ago( string $notification_id, int $days ): void {
		$this->stored[ PremiumTriggerCooldown::OPTION ][ $notification_id ] = time() - ( $days * DAY_IN_SECONDS );
	}

	/**
	 * Build a Suggestions instance against the stubbed site.
	 *
	 * @return Suggestions
	 */
	private function suggestions(): Suggestions {
		$options = new Options();

		return new Suggestions(
			new NotificationsHandler(),
			new PluginAvailability(),
			$options,
			new Util( $options, new RestAPIServer() ),
			new SnippetScan( $options )
		);
	}

	/**
	 * Invoke a private or protected method on a fresh Suggestions instance.
	 *
	 * Private and protected methods only became invokable without this in PHP
	 * 8.1, and the plugin still supports 7.4. Calling it unconditionally would
	 * instead raise a deprecation on PHP 8.5, so it is version-gated.
	 *
	 * @param string            $method The method name.
	 * @param array<int, mixed> $arguments The arguments to pass.
	 *
	 * @return mixed The method's return value.
	 */
	private function invoke( string $method, array $arguments = [] ) {
		$reflection = new ReflectionMethod( Suggestions::class, $method );

		if ( \PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $this->suggestions(), $arguments );
	}

	/**
	 * The notice the stubbed site is shown, if any.
	 *
	 * @return string|null
	 */
	private function active_notice(): ?string {
		return $this->invoke( 'get_active_upgrade_notice' );
	}

	/**
	 * Build one contextual upgrade notification.
	 *
	 * @param string $notification_id The notice to build.
	 *
	 * @return Notification
	 */
	private function notice( string $notification_id ): Notification {
		return $this->invoke( 'get_upgrade_notification', [ $notification_id ] );
	}

	/**
	 * A site in none of the three situations is left alone.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_a_site_matching_nothing_is_shown_nothing(): void {
		$this->assertNull( $this->active_notice() );
	}

	/**
	 * A store with its ecommerce events off is told so.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_a_store_not_reporting_ecommerce_is_told_about_it(): void {
		$this->given_a_store_not_reporting_ecommerce();

		$this->assertSame( self::WOO_ECOMMERCE, $this->active_notice() );
	}

	/**
	 * A store that reports its ecommerce events already is left alone.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_a_store_already_reporting_ecommerce_is_left_alone(): void {
		$this->given_a_store_not_reporting_ecommerce();

		$this->stored['gtmkit']['integrations']['woocommerce_integration'] = true;

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * A store with nothing published has no products to be told about.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_an_empty_store_is_not_told_how_many_products_it_has(): void {
		$this->given_a_store_not_reporting_ecommerce( 0 );

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * A site tagging through its own server is told what still runs in the browser.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_a_site_tagging_through_its_own_server_is_told_about_it(): void {
		$this->given_tagging_through_an_own_server();

		$this->assertSame( self::SERVER_SIDE, $this->active_notice() );
	}

	/**
	 * Pointing the setting at Google's own domain is not server-side tagging.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_tagging_server_domain
	 */
	public function test_googles_own_domain_does_not_count_as_a_tagging_server(): void {
		$this->given_tagging_through_an_own_server( 'www.googletagmanager.com' );

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * A site loading only the container is told what it is giving up.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_applies
	 */
	public function test_a_container_only_site_is_told_what_it_gives_up(): void {
		$this->given_only_the_container_is_loaded();

		$this->assertSame( self::CONTAINER_ONLY, $this->active_notice() );
	}

	/**
	 * A site in all three situations is shown one notice, the most useful one.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_only_one_notice_is_raised_when_every_situation_applies(): void {
		$this->given_a_store_not_reporting_ecommerce();
		$this->given_tagging_through_an_own_server();
		$this->given_only_the_container_is_loaded();

		$this->assertSame( self::WOO_ECOMMERCE, $this->active_notice() );
	}

	/**
	 * With the store answered, the server-side notice is next in line.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_the_server_side_notice_comes_next(): void {
		$this->given_tagging_through_an_own_server();
		$this->given_only_the_container_is_loaded();

		$this->assertSame( self::SERVER_SIDE, $this->active_notice() );
	}

	/**
	 * Premium sites are shown none of them.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_a_premium_site_is_shown_none_of_them(): void {
		Functions\when( 'TLA_Media\GTM_Kit\Premium\load_text_domain' )->justReturn( null );

		$this->given_a_store_not_reporting_ecommerce();
		$this->given_tagging_through_an_own_server();
		$this->given_only_the_container_is_loaded();

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * A dismissed notice stays away while its cooldown runs.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_a_dismissed_notice_stays_away_during_its_cooldown(): void {
		$this->given_a_store_not_reporting_ecommerce();
		$this->given_dismissed_days_ago( self::WOO_ECOMMERCE, PremiumTriggerCooldown::COOLDOWN_DAYS - 1 );

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * Dismissing is answered with silence, not with the next suggestion down.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_dismissing_does_not_hand_the_slot_to_the_next_notice(): void {
		$this->given_a_store_not_reporting_ecommerce();
		$this->given_tagging_through_an_own_server();
		$this->given_only_the_container_is_loaded();
		$this->given_dismissed_days_ago( self::WOO_ECOMMERCE, 1 );

		$this->assertNull( $this->active_notice() );
	}

	/**
	 * Once its cooldown has run out, a still-true notice returns.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_a_notice_returns_once_its_cooldown_has_run_out(): void {
		$this->given_a_store_not_reporting_ecommerce();
		$this->given_dismissed_days_ago( self::WOO_ECOMMERCE, PremiumTriggerCooldown::COOLDOWN_DAYS + 1 );

		$this->assertSame( self::WOO_ECOMMERCE, $this->active_notice() );
	}

	/**
	 * A notice dismissed on a site that has since changed gives way to the
	 * situation the site is actually in now.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_active_upgrade_notice
	 */
	public function test_a_dismissal_only_silences_the_situation_it_answered(): void {
		$this->given_tagging_through_an_own_server();
		$this->given_dismissed_days_ago( self::WOO_ECOMMERCE, 1 );

		$this->assertSame( self::SERVER_SIDE, $this->active_notice() );
	}

	/**
	 * The store notice states the store's own product count.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_woo_ecommerce_upgrade_notification
	 */
	public function test_the_store_notice_counts_the_products_the_store_has(): void {
		$this->given_a_store_not_reporting_ecommerce( 42 );

		$message = $this->notice( self::WOO_ECOMMERCE )->to_array()['message'];

		$this->assertStringContainsString( '42 published products', $message );
	}

	/**
	 * A store with one product is described in the singular.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_woo_ecommerce_upgrade_notification
	 */
	public function test_a_store_with_one_product_reads_correctly(): void {
		$this->given_a_store_not_reporting_ecommerce( 1 );

		$message = $this->notice( self::WOO_ECOMMERCE )->to_array()['message'];

		$this->assertStringContainsString( '1 published product,', $message );
	}

	/**
	 * The store notice offers the setting that fixes it for free.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_woo_ecommerce_upgrade_notification
	 */
	public function test_the_store_notice_links_the_setting_that_fixes_it(): void {
		$this->given_a_store_not_reporting_ecommerce();

		$message = $this->notice( self::WOO_ECOMMERCE )->to_array()['message'];

		$this->assertStringContainsString( 'page=gtmkit_general#/commerce?focus=woocommerce', $message );
	}

	/**
	 * The server-side notice names the site's own tagging server.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_server_side_upgrade_notification
	 */
	public function test_the_server_side_notice_names_the_tagging_server(): void {
		$this->given_tagging_through_an_own_server( 'sgtm.example.test' );

		$message = $this->notice( self::SERVER_SIDE )->to_array()['message'];

		$this->assertStringContainsString( 'sgtm.example.test', $message );
	}

	/**
	 * Every notice is a suggestion, never a reported fault.
	 *
	 * @dataProvider provide_notice_ids
	 *
	 * @param string $notification_id The notice to build.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_upgrade_notification
	 */
	public function test_a_notice_is_never_reported_as_a_problem( string $notification_id ): void {
		$this->given_a_store_not_reporting_ecommerce();

		$this->assertSame( Notification::NOTICE, $this->notice( $notification_id )->get_type() );
	}

	/**
	 * Every call to action goes somewhere, minted or not.
	 *
	 * @dataProvider provide_notice_ids
	 *
	 * @param string $notification_id The notice to build.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::upgrade_notice_link
	 */
	public function test_every_notice_links_somewhere_real( string $notification_id ): void {
		$this->given_a_store_not_reporting_ecommerce();

		$message = $this->notice( $notification_id )->to_array()['message'];

		$this->assertTrue(
			strpos( $message, 'https://jump.gtmkit.com/link/' ) !== false
				|| strpos( $message, 'https://gtmkit.com/pricing/' ) !== false,
			'A call to action has to resolve to its short link or to the pricing page, never to nothing.'
		);
	}

	/**
	 * The three contextual upgrade notices.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function provide_notice_ids(): array {
		return [
			'the store notice'          => [ self::WOO_ECOMMERCE ],
			'the server-side notice'    => [ self::SERVER_SIDE ],
			'the container-only notice' => [ self::CONTAINER_ONLY ],
		];
	}

	/**
	 * Raising a notice clears any dismissal the notifications system still holds.
	 *
	 * Dismissing writes two records: the cooldown, and the notifications
	 * system's own per-user flag. Only the cooldown decides whether the notice
	 * is held back, so the flag has to be cleared when the notice is raised
	 * again. Left in place it survives the cooldown, and the notice returns
	 * already marked dismissed: invisible, and permanently so, because nothing
	 * removes a notice that is being raised and removal is what would have
	 * cleared the flag.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::raise_upgrade_notice
	 */
	public function test_raising_a_notice_clears_a_stale_dismissal(): void {
		$this->given_a_store_not_reporting_ecommerce();

		// The dismissal key is built from the table prefix, which only $wpdb
		// knows. There is no database in this harness, so the global stands in
		// with the one method that is read. Each test runs in its own process,
		// so the override cannot reach another test.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Stubbing core's global in an isolated unit-test process.
		$GLOBALS['wpdb'] = new class() {
			/**
			 * The table prefix dismissal keys are stored under.
			 *
			 * @return string
			 */
			public function get_blog_prefix(): string {
				return 'wp_';
			}
		};

		Functions\when( 'get_user_by' )->justReturn(
			new class() {
				/**
				 * Every capability, so the notice is built for this user.
				 *
				 * @return bool
				 */
				public function has_cap(): bool {
					return true;
				}
			}
		);
		Functions\when( 'get_user_option' )->justReturn( false );

		Functions\expect( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'wp_' . self::WOO_ECOMMERCE, '', true )
			->andReturn( true );

		$this->invoke( 'raise_upgrade_notice', [ self::WOO_ECOMMERCE ] );
	}

	/**
	 * Only these notices keep a dismissal record of their own.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::is_upgrade_notice
	 */
	public function test_only_the_contextual_notices_keep_their_own_dismissal(): void {
		$this->assertTrue( Suggestions::is_upgrade_notice( self::WOO_ECOMMERCE ) );
		$this->assertTrue( Suggestions::is_upgrade_notice( self::SERVER_SIDE ) );
		$this->assertTrue( Suggestions::is_upgrade_notice( self::CONTAINER_ONLY ) );
		$this->assertFalse( Suggestions::is_upgrade_notice( 'gtmkit-premium-woo' ) );
		$this->assertFalse( Suggestions::is_upgrade_notice( 'gtmkit-container-injection' ) );
	}
}
