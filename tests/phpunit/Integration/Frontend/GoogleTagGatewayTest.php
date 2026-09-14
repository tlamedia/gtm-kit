<?php
/**
 * Integration tests for serving the container through the Google tag gateway.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Frontend;

use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * What the page carries when the gateway is on, off, and asked for but unusable.
 */
final class GoogleTagGatewayTest extends WP_UnitTestCase {

	/**
	 * The container ID used throughout.
	 *
	 * @var string
	 */
	private const GTM_ID = 'GTM-ABC123';

	/**
	 * Reset the settings and the stored health result between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( GoogleTagGatewayHealth::OPTION );

		$options = OptionsFactory::get_instance();
		foreach (
			[
				'gtm_id'                    => self::GTM_ID,
				'datalayer_name'            => 'dataLayer',
				'gtm_auth'                  => '',
				'gtm_preview'               => '',
				'sgtm_domain'               => '',
				'sgtm_container_identifier' => '',
				'google_tag_gateway'        => false,
			] as $key => $value
		) {
			$options->set_option( 'general', $key, $value );
		}
	}

	/**
	 * Store a health result.
	 *
	 * @param bool $service Whether the outbound service check passed.
	 * @param bool $script Whether the proxy reachability check passed.
	 *
	 * @return void
	 */
	private function store_health( bool $service, bool $script ): void {
		update_option(
			GoogleTagGatewayHealth::OPTION,
			[
				'schema'  => GoogleTagGatewayHealth::SCHEMA_VERSION,
				'service' => $service,
				'script'  => $script,
				'checked' => time(),
			],
			false
		);
	}

	/**
	 * Switch the gateway on, bypassing the enable gate's network check.
	 *
	 * The gate is exercised separately; these tests are about what the page
	 * carries once the setting is on, so they write the stored value directly.
	 *
	 * @return Options
	 */
	private function enable_gateway(): Options {
		$options = OptionsFactory::get_instance();

		$options->set_option( 'general', 'google_tag_gateway', true );

		return $options;
	}

	/**
	 * Render the container loader.
	 *
	 * @param Options|null $options The options to render with.
	 *
	 * @return string
	 */
	private function render( ?Options $options = null ): string {
		$frontend = new Frontend( $options ?? OptionsFactory::get_instance() );

		ob_start();
		$frontend->get_gtm_script( self::GTM_ID );

		return (string) ob_get_clean();
	}

	/**
	 * With the gateway off the loader is the standard one, unchanged.
	 *
	 * @return void
	 */
	public function test_gateway_off_serves_the_standard_loader(): void {
		$output = $this->render();

		$this->assertStringContainsString( "j.src=\n'https://www.googletagmanager.com/gtm.js?id='+i+dl", $output );
		$this->assertStringContainsString( "'" . self::GTM_ID . "'", $output );
	}

	/**
	 * With the gateway on and both checks passing the loader is same-origin.
	 *
	 * @return void
	 */
	public function test_gateway_on_serves_the_loader_from_this_site(): void {
		$this->store_health( true, true );
		$output = $this->render( $this->enable_gateway() );

		$this->assertStringNotContainsString( 'www.googletagmanager.com', $output, 'The loader must not be fetched from Google in gateway mode.' );
		$this->assertStringContainsString( 'gtg/measurement.php?id=', $output );
		$this->assertStringContainsString( "'" . self::GTM_ID . "'", $output );
		$this->assertStringContainsString( "dl=l!='dataLayer'?'&l='+l:''", $output, 'The data layer name still reaches the container.' );
	}

	/**
	 * The environment parameters still reach the container through the gateway.
	 *
	 * @return void
	 */
	public function test_gateway_carries_the_environment_parameters(): void {
		$this->store_health( true, true );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_auth', 'aB3' );
		$options->set_option( 'general', 'gtm_preview', 'env-7' );

		$output = $this->render( $this->enable_gateway() );

		$this->assertStringContainsString( 'gtm_auth=aB3', $output );
		$this->assertStringContainsString( 'gtm_preview=env-7', $output );
		$this->assertStringContainsString( 'gtm_cookies_win=x', $output );
	}

	/**
	 * A failing check falls back to the standard loader rather than serving nothing.
	 *
	 * @dataProvider failing_check_provider
	 *
	 * @param bool $service Whether the outbound service check passed.
	 * @param bool $script Whether the proxy reachability check passed.
	 *
	 * @return void
	 */
	public function test_a_failing_check_falls_back_to_the_standard_loader( bool $service, bool $script ): void {
		$this->store_health( $service, $script );
		$output = $this->render( $this->enable_gateway() );

		$this->assertStringContainsString( 'www.googletagmanager.com', $output, 'A broken gateway must not become a tracking outage.' );
		$this->assertStringNotContainsString( 'measurement.php', $output );
	}

	/**
	 * The combinations in which the gateway cannot serve.
	 *
	 * @return array<string, array<int, bool>>
	 */
	public static function failing_check_provider(): array {
		return [
			'the server cannot reach the service' => [ false, true ],
			'the proxy is not reachable'          => [ true, false ],
			'neither check passes'                => [ false, false ],
		];
	}

	/**
	 * Checks that have never run are not treated as passing.
	 *
	 * @return void
	 */
	public function test_an_unchecked_site_serves_the_standard_loader(): void {
		$output = $this->render( $this->enable_gateway() );

		$this->assertStringContainsString( 'www.googletagmanager.com', $output );
	}

	/**
	 * A custom server-side tagging domain wins, and the gateway stands down.
	 *
	 * @return void
	 */
	public function test_a_custom_sgtm_domain_stands_the_gateway_down(): void {
		$this->store_health( true, true );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', 'gtm.example.com' );

		$output = $this->render( $this->enable_gateway() );

		$this->assertStringContainsString( 'gtm.example.com', $output );
		$this->assertStringNotContainsString( 'measurement.php', $output );
	}

	/**
	 * The resource hint naming Google is dropped in gateway mode only.
	 *
	 * @return void
	 */
	public function test_the_google_dns_prefetch_is_dropped_in_gateway_mode(): void {
		$standard = new Frontend( OptionsFactory::get_instance() );
		$this->assertSame( [ '//www.googletagmanager.com' ], $standard->dns_prefetch( [], 'dns-prefetch' ) );

		$this->store_health( true, true );
		$gateway = new Frontend( $this->enable_gateway() );

		$this->assertSame( [], $gateway->dns_prefetch( [], 'dns-prefetch' ) );
	}

	/**
	 * The no-script fallback keeps pointing at Google in gateway mode.
	 *
	 * The gateway does not serve the no-script iframe, and a visitor without
	 * JavaScript has neither of the problems the gateway solves, so the
	 * fallback stays where it works rather than being dropped.
	 *
	 * @return void
	 */
	public function test_the_noscript_fallback_is_kept_in_gateway_mode(): void {
		$this->store_health( true, true );

		$frontend = new Frontend( $this->enable_gateway() );

		ob_start();
		$frontend->get_body_script();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'https://www.googletagmanager.com/ns.html?id=' . self::GTM_ID, $output );
	}
}
