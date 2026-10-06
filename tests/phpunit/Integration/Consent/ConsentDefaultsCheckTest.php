<?php
/**
 * Integration tests for Consent Mode defaults that nothing can lift.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\ConsentDefaultsCheck} and
 * {@see \TLA_Media\GTM_Kit\Admin\SiteHealth::test_consent()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Admin\ConsentDefaultsCheck;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * Site Health and the Consent settings page warn only when GTM Kit's own defaults are all there is.
 */
final class ConsentDefaultsCheckTest extends WP_UnitTestCase {

	/**
	 * Start from defaults switched off, every category denied, and no consent platform.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', false );
		$options->set_option( 'general', 'gcm_analytics_storage', false );

		remove_all_filters( 'gtmkit_active_cmp' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
	}

	/**
	 * Clean up the filters a test added.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'gtmkit_active_cmp' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );

		parent::tear_down();
	}

	/**
	 * Switch the defaults on.
	 *
	 * @param bool $grant_analytics Whether the defaults grant analytics storage.
	 *
	 * @return void
	 */
	private function defaults_on( bool $grant_analytics = false ): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', true );
		$options->set_option( 'general', 'gcm_analytics_storage', $grant_analytics );
	}

	/**
	 * Register a consent source above GTM Kit's defaults, the way the WP Consent API integration does.
	 *
	 * @return void
	 */
	private function register_higher_source(): void {
		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['wp_consent_api'] = [
					'id'        => 'wp_consent_api',
					'priority'  => 100,
					'is_active' => static fn(): bool => true,
					'read'      => static fn(): array => [],
				];

				return $sources;
			}
		);
	}

	/**
	 * The Site Health consent result.
	 *
	 * @return array<string, mixed>
	 */
	private function site_health(): array {
		$options = OptionsFactory::get_instance();

		return ( new SiteHealth( $options, new Util( $options, new RestAPIServer() ) ) )->test_consent();
	}

	/**
	 * The badges on the Consent settings page.
	 *
	 * @return array<int|string, mixed>
	 */
	private function badges(): array {
		return ( new ConsentDefaultsCheck( OptionsFactory::get_instance() ) )->add_badge( [] );
	}

	/**
	 * Denying defaults with nothing else owning consent warn on both surfaces.
	 *
	 * @return void
	 */
	public function test_denying_defaults_alone_warn_on_both_surfaces(): void {
		$this->defaults_on();

		$result = $this->site_health();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'measured without cookies', $result['description'] );

		$badges = $this->badges();
		$this->assertCount( 1, $badges );
		$this->assertSame( 'warning', $badges[0]['severity'] );
		$this->assertSame( ConsentDefaultsCheck::BADGE_ID, $badges[0]['id'] );
	}

	/**
	 * A higher-priority consent source owns consent, so neither surface warns.
	 *
	 * @return void
	 */
	public function test_a_higher_priority_consent_source_silences_both_surfaces(): void {
		$this->defaults_on();
		$this->register_higher_source();

		$this->assertSame( 'good', $this->site_health()['status'] );
		$this->assertSame( [], $this->badges() );
	}

	/**
	 * A detected consent platform owns consent, so neither surface warns.
	 *
	 * @return void
	 */
	public function test_a_detected_consent_platform_silences_both_surfaces(): void {
		$this->defaults_on();
		add_filter( 'gtmkit_active_cmp', static fn() => 'cookieyes' );

		$result = $this->site_health();
		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'CookieYes', $result['description'] );
		$this->assertSame( [], $this->badges() );
	}

	/**
	 * Defaults that grant analytics are deliberate: a note, no warning.
	 *
	 * @return void
	 */
	public function test_defaults_that_grant_analytics_pass_without_a_badge(): void {
		$this->defaults_on( true );

		$this->assertSame( 'good', $this->site_health()['status'] );
		$this->assertSame( [], $this->badges() );
	}

	/**
	 * With the defaults off and nothing else, the recommendation stays and names the platform requirement.
	 *
	 * @return void
	 */
	public function test_defaults_off_without_a_platform_recommend_and_name_the_platform(): void {
		$result = $this->site_health();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'alongside a platform that collects that answer', $result['description'] );
		$this->assertSame( [], $this->badges() );
	}

	/**
	 * With the defaults off, a consent integration that owns consent passes.
	 *
	 * @return void
	 */
	public function test_defaults_off_with_a_consent_integration_pass(): void {
		$this->register_higher_source();

		$result = $this->site_health();

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( 'Consent is configured', $result['label'] );
		$this->assertStringContainsString( 'A consent integration on this site', $result['description'] );
	}

	/**
	 * With the defaults off and only GTM Kit's own source registered, the recommendation stays as it was.
	 *
	 * @return void
	 */
	public function test_defaults_off_with_only_the_default_source_still_recommend(): void {
		$result = $this->site_health();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'Consent is not configured', $result['label'] );
		$this->assertStringContainsString( 'no supported consent platform was found', $result['description'] );
	}

	/**
	 * With the defaults off and a platform detected, the result is unchanged.
	 *
	 * @return void
	 */
	public function test_defaults_off_with_a_platform_is_unchanged(): void {
		add_filter( 'gtmkit_active_cmp', static fn() => 'cookieyes' );

		$result = $this->site_health();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'owns consent', $result['description'] );
	}
}
