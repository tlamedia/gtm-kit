<?php
/**
 * Integration tests for the consent state a new visitor gets.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry::read_default_state()}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Consent;

use TLA_Media\GTM_Kit\Abilities\Abilities;
use TLA_Media\GTM_Kit\Admin\ConsentDefaultsCheck;
use TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * The new-visitor state never comes from the request's consent cookies.
 */
final class DefaultStateTest extends WP_UnitTestCase {

	/**
	 * The cookie a test source reads, standing in for a visitor's stored consent.
	 *
	 * @var string
	 */
	private const COOKIE = 'test_consent_statistics';

	/**
	 * Start each test with a clean registry and no consent cookie.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_consent_default_state' );
		remove_all_filters( 'gtmkit_consent_default_settings_enabled' );
		remove_all_filters( 'gtmkit_active_cmp' );
		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Remove the consent cookie.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_COOKIE[ self::COOKIE ] );
		remove_all_filters( 'gtmkit_consent_signal_sources' );

		parent::tear_down();
	}

	/**
	 * Register a source that reads the request's cookie, optionally with a new-visitor reader.
	 *
	 * @param bool $with_default Whether the source can say what a new visitor gets.
	 *
	 * @return void
	 */
	private static function add_cookie_source( bool $with_default ): void {
		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) use ( $with_default ) {
				$source = [
					'id'        => 'cookie_source',
					'priority'  => 100,
					'is_active' => static fn(): bool => true,
					'read'      => static fn(): array => [
						// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A test double reading a fixed test cookie.
						'analytics_storage' => ( 'allow' === ( $_COOKIE[ self::COOKIE ] ?? '' ) ) ? 'granted' : 'denied',
					],
				];

				if ( $with_default ) {
					$source['read_default'] = static fn(): array => [ 'analytics_storage' => 'granted' ];
				}

				$sources['cookie_source'] = $source;

				return $sources;
			}
		);
	}

	/**
	 * The new-visitor state ignores a consent cookie on the request.
	 */
	public function test_default_state_ignores_the_request_cookie(): void {
		self::add_cookie_source( true );
		$registry = new ConsentSignalSourceRegistry( OptionsFactory::get_instance() );

		$_COOKIE[ self::COOKIE ] = 'deny';

		$this->assertSame( 'denied', $registry->read_state()['analytics_storage'], 'The visitor read follows the cookie.' );
		$this->assertSame( 'granted', $registry->read_default_state()['analytics_storage'], 'The new-visitor read does not.' );
		$this->assertTrue( $registry->is_default_state_available() );
	}

	/**
	 * A source that cannot say what a new visitor gets reports no state, never the cookie's.
	 */
	public function test_source_without_default_reader_is_unavailable(): void {
		self::add_cookie_source( false );
		$registry = new ConsentSignalSourceRegistry( OptionsFactory::get_instance() );

		$_COOKIE[ self::COOKIE ] = 'allow';

		$this->assertNull( $registry->read_default_state() );
		$this->assertFalse( $registry->is_default_state_available() );
	}

	/**
	 * GTM Kit's own defaults are the new-visitor state.
	 */
	public function test_default_source_reads_its_settings(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', true );
		$options->set_option( 'general', 'gcm_analytics_storage', true );

		$registry = new ConsentSignalSourceRegistry( $options );

		$this->assertSame( $registry->read_state(), $registry->read_default_state() );
		$this->assertSame( 'granted', $registry->read_default_state()['analytics_storage'] );
	}

	/**
	 * The consent check behind Site Health answers for a new visitor, whatever cookie the request carries.
	 */
	public function test_defaults_check_uses_new_visitor_state(): void {
		self::add_cookie_source( true );
		$options  = OptionsFactory::get_instance();
		$registry = new ConsentSignalSourceRegistry( $options );

		$_COOKIE[ self::COOKIE ] = 'deny';

		$this->assertTrue( ( new ConsentDefaultsCheck( $options, $registry ) )->grants_analytics() );
	}

	/**
	 * The consent status an assistant reads is the new-visitor state, and says so.
	 */
	public function test_consent_status_reports_the_basis(): void {
		$abilities = new Abilities( OptionsFactory::get_instance() );

		self::add_cookie_source( true );
		$_COOKIE[ self::COOKIE ] = 'deny';

		$status = $abilities->get_consent_status();
		$this->assertSame( 'new_visitor_default', $status['consent_state_basis'] );
		$this->assertSame( 'granted', $status['consent_state']['analytics_storage'] );

		remove_all_filters( 'gtmkit_consent_signal_sources' );
		self::add_cookie_source( false );

		$status = $abilities->get_consent_status();
		$this->assertSame( 'unavailable', $status['consent_state_basis'] );
		$this->assertNull( $status['consent_state'] );
	}
}
