<?php
/**
 * Unit tests for the evidence the tracking scan adds to the dashboard notices.
 *
 * The container-injection notice has always reasoned from settings alone. The
 * scan lets it say what a request to the site actually returned, and the rule
 * that governs it is the one that keeps the feature honest: with a consent
 * platform active, a container missing from the server's HTML is the intended
 * arrangement and must never be flagged as a fault.
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
use TLA_Media\GTM_Kit\Admin\Suggestions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Covers the scan-derived evidence in the container and duplicate notices.
 */
final class TrackingNoticeEvidenceTest extends TestCase {

	/**
	 * Option values present on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Plugin files the stubbed `is_plugin_active` reports as active.
	 *
	 * @var array<int, string>
	 */
	private array $active_plugins = [];

	/**
	 * The value the stubbed `wp_get_environment_type` returns.
	 *
	 * @var string
	 */
	private string $environment = 'production';

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

		$this->stored         = [];
		$this->active_plugins = [];
		$this->environment    = 'production';

		Functions\stubs(
			[
				'__'                  => null,
				'esc_html'            => null,
				'esc_url'             => null,
				'sanitize_key'        => static fn( $key ) => strtolower( (string) $key ),
				'admin_url'           => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'network_admin_url'   => static fn( $path = '' ) => 'https://example.test/wp-admin/network/' . $path,
				'is_network_admin'    => false,
				'is_multisite'        => false,
				'get_current_user_id' => 1,
			]
		);

		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);

		Functions\when( 'is_plugin_active' )->alias(
			fn( $plugin_file ) => in_array( $plugin_file, $this->active_plugins, true )
		);

		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = [] ) => array_merge( (array) $defaults, (array) $args )
		);
	}

	/**
	 * Store a scan result for the notices to read.
	 *
	 * @param string               $state One of the SnippetScan STATE_* constants.
	 * @param array<string, mixed> $overrides Fields to override on the stored result.
	 *
	 * @return void
	 */
	private function store_scan_result( string $state, array $overrides = [] ): void {
		$this->stored[ SnippetScan::OPTION ] = array_merge(
			[
				'schema'           => SnippetScan::SCHEMA_VERSION,
				'url'              => 'https://example.test/',
				'final_url'        => 'https://example.test/',
				'scanned_at'       => 1750000000,
				'state'            => $state,
				'reason'           => '',
				'status'           => 200,
				'implementations'  => [],
				'container_output' => false,
				'cmp'              => null,
				'duplicate'        => [
					'type'       => '',
					'containers' => [],
					'culprit'    => '',
				],
			],
			$overrides
		);
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
	 * Invoke one of the protected notification builders.
	 *
	 * Private and protected methods only became invokable without this in PHP
	 * 8.1, and the plugin still supports 7.4. Calling it unconditionally would
	 * instead raise a deprecation on PHP 8.5, so it is version-gated.
	 *
	 * @param string            $method The method name.
	 * @param array<int, mixed> $arguments The arguments to pass.
	 *
	 * @return Notification
	 */
	private function build( string $method, array $arguments ): Notification {
		$reflection = new ReflectionMethod( Suggestions::class, $method );

		if ( \PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invokeArgs( $this->suggestions(), $arguments );
	}

	/**
	 * Build the container-injection notice.
	 *
	 * @return Notification
	 */
	private function container_notice(): Notification {
		return $this->build( 'get_suggest_container_injection_notification', [ 'gtmkit-container-injection', false, 'GTM-ABCD123' ] );
	}

	/**
	 * With no scan stored, the notice says exactly what it always said.
	 */
	public function test_without_a_scan_the_notice_is_unchanged(): void {
		$notice = $this->container_notice();

		$this->assertStringNotContainsString( 'A check of your site', $notice->to_array()['message'] );
		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
	}

	/**
	 * A scan that found nothing adds the evidence and stays a problem.
	 */
	public function test_an_empty_page_adds_evidence_and_stays_a_problem(): void {
		$this->store_scan_result( SnippetScan::STATE_NOT_FOUND );

		$notice = $this->container_notice();

		$this->assertStringContainsString( 'nothing appears to be measuring this site', $notice->to_array()['message'] );
		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
	}

	/**
	 * With a consent platform active, the same finding is stated, not flagged.
	 */
	public function test_a_consent_platform_turns_the_finding_into_a_statement(): void {
		$this->active_plugins = [ 'cookiebot/cookiebot.php' ];
		$this->store_scan_result( SnippetScan::STATE_NOT_FOUND );

		$notice  = $this->container_notice();
		$message = $notice->to_array()['message'];

		$this->assertStringContainsString( 'Cookiebot', $message );
		$this->assertStringNotContainsString( 'nothing appears to be measuring this site', $message );
		$this->assertSame( Notification::NOTICE, $notice->get_type() );
	}

	/**
	 * With no container saved, a consent platform explains nothing.
	 *
	 * The softening exists for a site that hands container injection to its
	 * consent platform on purpose. A site that has never entered a container
	 * ID has nothing for any platform to load, so the unfinished setup stays
	 * a problem rather than being dressed up as an arrangement.
	 */
	public function test_a_consent_platform_does_not_soften_a_site_with_no_container_id(): void {
		$this->active_plugins = [ 'cookiebot/cookiebot.php' ];
		$this->store_scan_result( SnippetScan::STATE_NOT_FOUND );

		$notice  = $this->build( 'get_suggest_container_injection_notification', [ 'gtmkit-container-injection', false, '' ] );
		$message = $notice->to_array()['message'];

		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
		$this->assertStringNotContainsString( 'Cookiebot', $message );
		$this->assertStringContainsString( 'nothing appears to be measuring this site', $message );
	}

	/**
	 * On a site that is not production, the same finding is stated, not flagged.
	 *
	 * The scan fetches the site's own pages, so a site whose container is
	 * withheld will always find nothing. That is the expected result, and it
	 * belongs to the same rule as the consent-platform case.
	 */
	public function test_a_site_that_is_not_production_turns_the_finding_into_a_statement(): void {
		$this->environment = 'staging';
		$this->store_scan_result( SnippetScan::STATE_NOT_FOUND );

		$notice  = $this->container_notice();
		$message = $notice->to_array()['message'];

		$this->assertStringContainsString( 'WordPress reports this site as staging', $message );
		$this->assertStringNotContainsString( 'nothing appears to be measuring this site', $message );
		$this->assertSame( Notification::NOTICE, $notice->get_type() );
	}

	/**
	 * With the setting switched on, the finding is a problem again.
	 */
	public function test_the_setting_restores_the_ordinary_finding(): void {
		$this->environment                    = 'staging';
		$this->stored[ Options::OPTION_NAME ] = [ 'general' => [ 'load_on_non_production' => true ] ];
		$this->store_scan_result( SnippetScan::STATE_NOT_FOUND );

		$notice = $this->container_notice();

		$this->assertStringContainsString( 'nothing appears to be measuring this site', $notice->to_array()['message'] );
		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
	}

	/**
	 * The notice about a withheld container names the site's report and the setting.
	 */
	public function test_the_withheld_container_notice_explains_itself(): void {
		$this->environment = 'staging';

		$notice  = $this->build( 'get_suppressed_container_notification', [ 'gtmkit-site-not-production' ] );
		$message = $notice->to_array()['message'];

		$this->assertStringContainsString( 'staging', $message );
		$this->assertStringContainsString( 'data layer is still built', $message );
		$this->assertStringContainsString( 'general#/container', $message );
		$this->assertSame( Notification::NOTICE, $notice->get_type() );
	}

	/**
	 * The suggestion to declare a copy links the WordPress documentation.
	 */
	public function test_the_declare_a_copy_notice_links_the_documentation(): void {
		$notice  = $this->build( 'get_declare_a_copy_notification', [ 'gtmkit-undeclared-copy' ] );
		$message = $notice->to_array()['message'];

		$this->assertStringContainsString( 'WP_ENVIRONMENT_TYPE', $message );
		$this->assertStringContainsString( 'developer.wordpress.org/apis/wp-config-php/#wp-environment-type', $message );
		$this->assertSame( Notification::NOTICE, $notice->get_type() );
	}

	/**
	 * A scan that could not answer the question adds nothing.
	 */
	public function test_an_inconclusive_scan_adds_no_evidence(): void {
		$this->store_scan_result(
			SnippetScan::STATE_INCONCLUSIVE,
			[ 'reason' => SnippetScan::REASON_LOOPBACK_BLOCKED ]
		);

		$notice = $this->container_notice();

		$this->assertStringNotContainsString( 'A check of your site', $notice->to_array()['message'] );
		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
	}

	/**
	 * A scan that found a container adds nothing to this notice either.
	 */
	public function test_a_scan_that_found_a_container_adds_no_evidence(): void {
		$this->store_scan_result( SnippetScan::STATE_FOUND );

		$this->assertStringNotContainsString( 'A check of your site', $this->container_notice()->to_array()['message'] );
	}

	/**
	 * The duplicate notice names the containers it found.
	 */
	public function test_the_duplicate_notice_names_two_containers(): void {
		$notice = $this->build(
			'get_duplicate_tracking_notification',
			[
				'gtmkit-duplicate-tracking',
				[
					'type'       => SnippetScan::DUPLICATE_CONTAINERS,
					'containers' => [ 'GTM-ABCD123', 'GTM-ZZZZ999' ],
					'culprit'    => 'Google Tag Manager for WordPress',
				],
			]
		);

		$message = $notice->to_array()['message'];

		$this->assertStringContainsString( 'GTM-ABCD123, GTM-ZZZZ999', $message );
		$this->assertStringContainsString( 'Google Tag Manager for WordPress', $message );
		$this->assertSame( Notification::PROBLEM, $notice->get_type() );
	}

	/**
	 * With no culprit identified, the notice says so instead of guessing.
	 */
	public function test_the_duplicate_notice_admits_an_unknown_culprit(): void {
		$notice = $this->build(
			'get_duplicate_tracking_notification',
			[
				'gtmkit-duplicate-tracking',
				[
					'type'       => SnippetScan::DUPLICATE_REPEATED,
					'containers' => [ 'GTM-ABCD123' ],
					'culprit'    => '',
				],
			]
		);

		$this->assertStringContainsString( 'could not tell what adds the extra tracking code', $notice->to_array()['message'] );
	}

	/**
	 * Build the duplicate notice for one kind of finding.
	 *
	 * @param string $type One of the SnippetScan DUPLICATE_* constants.
	 *
	 * @return Notification
	 */
	private function duplicate_notice( string $type ): Notification {
		return $this->build(
			'get_duplicate_tracking_notification',
			[
				'gtmkit-duplicate-tracking',
				[
					'type'       => $type,
					'containers' => [ 'GTM-ABCD123' ],
					'culprit'    => 'Google for WooCommerce',
				],
			]
		);
	}

	/**
	 * A Google tag beside the container is a notice that does not claim duplication.
	 *
	 * The scan cannot see whether the same tag also fires inside the
	 * container, so a legitimate setup must not be told it double-counts.
	 */
	public function test_a_google_tag_beside_the_container_is_a_notice(): void {
		$notice = $this->duplicate_notice( SnippetScan::DUPLICATE_GTAG );
		$fields = $notice->to_array();

		$this->assertSame( Notification::NOTICE, $notice->get_type() );
		$this->assertStringNotContainsString( 'Duplicate', $fields['header'] );
		$this->assertStringContainsString( 'Google for WooCommerce', $fields['message'] );
		$this->assertStringContainsString( 'site-health.php', $fields['message'] );
	}

	/**
	 * Established double counting stays a problem under its existing header.
	 */
	public function test_established_duplicates_stay_problems(): void {
		foreach ( [ SnippetScan::DUPLICATE_CONTAINERS, SnippetScan::DUPLICATE_REPEATED ] as $type ) {
			$notice = $this->duplicate_notice( $type );

			$this->assertSame( Notification::PROBLEM, $notice->get_type(), $type );
			$this->assertSame( 'Duplicate tracking:', $notice->to_array()['header'], $type );
		}
	}
}
