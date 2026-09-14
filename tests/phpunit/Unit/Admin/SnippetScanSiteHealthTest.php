<?php
/**
 * Unit tests for how the tracking scan's Site Health test reads an empty page.
 *
 * The scan fetches the site's own pages, so on a site whose container is
 * withheld it will always find nothing. That absence is the intended result
 * and must never be reported as a fault, exactly like the consent-platform
 * case beside it.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Result tests for a page the scan found no tracking in.
 */
final class SnippetScanSiteHealthTest extends TestCase {

	/**
	 * Option values present on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * The value the stubbed `wp_get_environment_type` returns.
	 *
	 * @var string
	 */
	private string $environment = 'production';

	/**
	 * Stub every WordPress function the test and its dependencies touch.
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

		$this->stored      = [];
		$this->environment = 'production';

		Functions\stubs(
			[
				'__'                => null,
				'esc_html'          => null,
				'esc_html__'        => null,
				'esc_url'           => null,
				'esc_url_raw'       => null,
				'sanitize_key'      => static fn( $key ) => strtolower( (string) $key ),
				'admin_url'         => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'network_admin_url' => static fn( $path = '' ) => 'https://example.test/wp-admin/network/' . $path,
				'is_network_admin'  => false,
				'is_multisite'      => false,
				'is_plugin_active'  => false,
				'wp_create_nonce'   => 'nonce',
				'wp_nonce_url'      => 'https://example.test/wp-admin/site-health.php',
				'human_time_diff'   => '3 hours',
				'home_url'          => static fn( $path = '' ) => 'https://example.test' . $path,
			]
		);

		Functions\when( 'wp_get_environment_type' )->alias( fn() => $this->environment );
		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);
		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults = [] ) => array_merge( (array) $defaults, (array) $args )
		);
		Functions\when( 'add_query_arg' )->alias(
			static fn( $args, $url = '' ) => (string) $url
		);
	}

	/**
	 * Store a scan result that found no tracking.
	 *
	 * @param bool $container_output Whether GTM Kit intends to add a container.
	 *
	 * @return void
	 */
	private function store_empty_page_scan( bool $container_output = false ): void {
		$this->stored[ SnippetScan::OPTION ] = [
			'schema'           => SnippetScan::SCHEMA_VERSION,
			'url'              => 'https://example.test/',
			'final_url'        => 'https://example.test/',
			'scanned_at'       => 1750000000,
			'state'            => SnippetScan::STATE_NOT_FOUND,
			'reason'           => '',
			'status'           => 200,
			'implementations'  => [],
			'container_output' => $container_output,
			'cmp'              => null,
			'duplicate'        => [
				'type'       => '',
				'containers' => [],
				'culprit'    => '',
			],
		];
	}

	/**
	 * Run the test against the stubbed site.
	 *
	 * @param array<string, mixed> $general The stored general options.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function run_test( array $general = [] ): array {
		$this->stored[ Options::OPTION_NAME ] = [ 'general' => $general ];

		$options = new Options();
		$util    = new Util( $options, new RestAPIServer() );

		$scan = new SnippetScan( $options );

		return ( new SnippetScanSiteHealth( $scan, $options ) )->run_test( new SiteHealth( $options, $util ) );
	}

	/**
	 * An empty page on a site that is not production is a correct outcome.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_an_empty_page_is_expected_when_the_site_is_not_production(): void {
		$this->environment = 'staging';
		$this->store_empty_page_scan();

		$result = $this->run_test( [ 'container_active' => true ] );

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'staging', $result['description'] );
		$this->assertStringNotContainsString( 'something is removing it', $result['description'] );
	}

	/**
	 * With the setting switched on, the same page is a finding again.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_the_setting_restores_the_ordinary_finding(): void {
		$this->environment = 'staging';
		$this->store_empty_page_scan();

		$result = $this->run_test(
			[
				'container_active'       => true,
				'load_on_non_production' => true,
			]
		);

		$this->assertNotSame( 'good', $result['status'] );
	}

	/**
	 * On a production site the existing wording is untouched.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_a_production_site_keeps_its_existing_finding(): void {
		$this->store_empty_page_scan();

		$result = $this->run_test( [ 'container_active' => false ] );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'nothing else in the page is loading one', $result['description'] );
	}

	/**
	 * A consent platform explains an empty page only when one was expected.
	 *
	 * A platform that loads the container after the visitor answers is a
	 * complete explanation for a page the scan found nothing in, but only on
	 * a site that set a container up in the first place. Reported without
	 * that condition, a site that has entered no container ID at all is told
	 * its consent platform is loading a container that does not exist.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_a_consent_platform_does_not_explain_a_container_that_was_never_set_up(): void {
		Functions\when( 'is_plugin_active' )->alias(
			static fn( $plugin ) => 'cookiebot/cookiebot.php' === $plugin
		);

		$this->store_empty_page_scan();

		$result = $this->run_test( [ 'container_active' => false ] );

		$this->assertNotSame( 'good', $result['status'] );
		$this->assertStringNotContainsString( 'Cookiebot', $result['description'] );
	}

	/**
	 * With a container to load, the consent platform is the explanation.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_a_consent_platform_explains_a_container_that_is_set_up(): void {
		Functions\when( 'is_plugin_active' )->alias(
			static fn( $plugin ) => 'cookiebot/cookiebot.php' === $plugin
		);

		$this->store_empty_page_scan( true );

		$result = $this->run_test(
			[
				'container_active' => true,
				'gtm_id'           => 'GTM-TEST123',
			]
		);

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'Cookiebot', $result['description'] );
	}

	/**
	 * A Google tag beside the container stays a recommendation.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_a_google_tag_beside_the_container_stays_recommended(): void {
		$this->store_empty_page_scan( true );

		$this->stored[ SnippetScan::OPTION ]['state']     = SnippetScan::STATE_FOUND;
		$this->stored[ SnippetScan::OPTION ]['duplicate'] = [
			'type'       => SnippetScan::DUPLICATE_GTAG,
			'containers' => [ 'GTM-TEST123' ],
			'culprit'    => 'Google for WooCommerce',
		];

		$result = $this->run_test(
			[
				'container_active' => true,
				'gtm_id'           => 'GTM-TEST123',
			]
		);

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'A Google tag loads alongside your container', $result['label'] );
		$this->assertStringContainsString( 'The Google tag appears to come from <strong>Google for WooCommerce</strong>', $result['description'] );
		$this->assertStringNotContainsString( 'Remove the container', $result['description'] );
	}

	/**
	 * A named source of a second container is still told to remove it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth::run_test
	 */
	public function test_a_named_source_of_a_container_is_told_to_remove_it(): void {
		foreach ( [ SnippetScan::DUPLICATE_CONTAINERS, SnippetScan::DUPLICATE_REPEATED ] as $type ) {
			$this->store_empty_page_scan( true );

			$this->stored[ SnippetScan::OPTION ]['state']     = SnippetScan::STATE_FOUND;
			$this->stored[ SnippetScan::OPTION ]['duplicate'] = [
				'type'       => $type,
				'containers' => [ 'GTM-TEST123' ],
				'culprit'    => 'WPCode',
			];

			$result = $this->run_test(
				[
					'container_active' => true,
					'gtm_id'           => 'GTM-TEST123',
				]
			);

			$this->assertSame( 'critical', $result['status'], $type );
			$this->assertStringContainsString( 'Remove the container from there', $result['description'], $type );
		}
	}
}
