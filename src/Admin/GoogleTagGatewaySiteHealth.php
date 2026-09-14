<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\GoogleTagGateway;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Reports the two conditions the Google tag gateway depends on, in Site Health.
 *
 * Both conditions are properties of the hosting rather than of the settings,
 * which is why they are reported here rather than left to the settings screen:
 * a site owner cannot fix either one by changing a setting, and the person who
 * can fix them is usually the host.
 *
 * The tests read the stored result and perform no request of their own, so
 * they stay fast direct tests. The requests behind them run once a day on a
 * schedule and once when the setting is switched on.
 *
 * Nothing is registered on a site that has the gateway switched off. A test
 * reporting on a feature nobody enabled is noise, and the settings screen
 * already explains why the option is unavailable when it is.
 */
final class GoogleTagGatewaySiteHealth {

	/**
	 * The Site Health test id for the outbound service check.
	 *
	 * @var string
	 */
	private const SERVICE_TEST_ID = 'gtmkit_gateway_service';

	/**
	 * The Site Health test id for the proxy reachability check.
	 *
	 * @var string
	 */
	private const SCRIPT_TEST_ID = 'gtmkit_gateway_script';

	/**
	 * Register the tests.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {

		$gateway = new GoogleTagGateway( $options );

		if ( ! $gateway->is_enabled() ) {
			return;
		}

		$instance = new self();

		add_filter( 'gtmkit_site_health_tests', [ $instance, 'add_tests' ], 10, 2 );
	}

	/**
	 * Add the tests to GTM Kit's Site Health group.
	 *
	 * @param array<string, array<string, mixed>> $tests The GTM Kit tests.
	 * @param SiteHealth                          $site_health The Site Health integration.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_tests( array $tests, SiteHealth $site_health ): array {

		$tests[ self::SERVICE_TEST_ID ] = [
			'label' => __( 'Google tag gateway connection', 'gtm-kit' ),
			'test'  => function () use ( $site_health ): array {
				return $this->service_result( $site_health );
			},
		];

		$tests[ self::SCRIPT_TEST_ID ] = [
			'label' => __( 'Google tag gateway address', 'gtm-kit' ),
			'test'  => function () use ( $site_health ): array {
				return $this->script_result( $site_health );
			},
		];

		return $tests;
	}

	/**
	 * Report whether the server can reach the gateway service.
	 *
	 * @param SiteHealth $site_health The Site Health integration.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function service_result( SiteHealth $site_health ): array {

		$result = GoogleTagGatewayHealth::get_result();

		if ( $result === null ) {
			return $this->not_checked_yet( $site_health, self::SERVICE_TEST_ID );
		}

		if ( ! empty( $result['service'] ) ) {
			return $site_health->build_result(
				self::SERVICE_TEST_ID,
				__( 'Your server can reach the Google tag gateway', 'gtm-kit' ),
				'good',
				'<p>' . esc_html__( 'Your server can open a connection to the tag gateway, which is what lets it pass measurement on to Google.', 'gtm-kit' ) . '</p>'
			);
		}

		return $site_health->build_result(
			self::SERVICE_TEST_ID,
			__( 'Your server cannot reach the Google tag gateway', 'gtm-kit' ),
			'critical',
			'<p>' . esc_html__( 'Your server could not open a connection to the tag gateway. Some hosts block outgoing connections from the website, which stops the gateway from passing measurement on to Google.', 'gtm-kit' ) . '</p>'
			. '<p>' . esc_html__( 'Until this is resolved, GTM Kit is loading the container the standard way, so your tracking is still running. Ask your host to allow outgoing HTTPS connections from your site.', 'gtm-kit' ) . '</p>',
			$site_health->documentation_link( 'google-tag-gateway', 'google-tag-gateway', __( 'About the Google tag gateway', 'gtm-kit' ) )
		);
	}

	/**
	 * Report whether the gateway address answers over the web.
	 *
	 * @param SiteHealth $site_health The Site Health integration.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function script_result( SiteHealth $site_health ): array {

		$result = GoogleTagGatewayHealth::get_result();

		if ( $result === null ) {
			return $this->not_checked_yet( $site_health, self::SCRIPT_TEST_ID );
		}

		if ( ! empty( $result['script'] ) ) {
			return $site_health->build_result(
				self::SCRIPT_TEST_ID,
				__( 'Your gateway address is reachable', 'gtm-kit' ),
				'good',
				'<p>' . esc_html__( 'The address that serves the Google tag from your own domain is answering as expected.', 'gtm-kit' ) . '</p>'
			);
		}

		return $site_health->build_result(
			self::SCRIPT_TEST_ID,
			__( 'Your gateway address is not reachable', 'gtm-kit' ),
			'critical',
			'<p>' . esc_html__( 'The address that serves the Google tag from your own domain did not answer. Some hosts block this kind of address for security reasons.', 'gtm-kit' ) . '</p>'
			. '<p>' . esc_html__( 'Until this is resolved, GTM Kit is loading the container the standard way, so your tracking is still running. If your site uses a page cache or a firewall, this address also needs to be excluded from caching.', 'gtm-kit' ) . '</p>',
			$site_health->documentation_link( 'google-tag-gateway', 'google-tag-gateway', __( 'About the Google tag gateway', 'gtm-kit' ) )
		);
	}

	/**
	 * Report that the checks have not run on this site yet.
	 *
	 * @param SiteHealth $site_health The Site Health integration.
	 * @param string     $test_id The test id to report under.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	private function not_checked_yet( SiteHealth $site_health, string $test_id ): array {

		return $site_health->build_result(
			$test_id,
			__( 'The Google tag gateway has not been checked yet', 'gtm-kit' ),
			'recommended',
			'<p>' . esc_html__( 'GTM Kit checks once a day that the Google tag can be served from your own domain. That check has not run yet on this site.', 'gtm-kit' ) . '</p>'
		);
	}
}
