<?php
/**
 * Integration tests for GTM Kit's abilities in the WordPress Abilities API.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Abilities\Abilities}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Abilities;

use TLA_Media\GTM_Kit\Abilities\Abilities;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_Abilities_Registry;
use WP_Ability_Categories_Registry;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The three abilities register, refuse anyone but an administrator, and report what the admin reports.
 */
final class AbilitiesTest extends WP_UnitTestCase {

	/**
	 * The container environment token, which must never appear in an answer.
	 *
	 * @var string
	 */
	private const ENV_AUTH = 'envAuthTokenNeverShown1234';

	/**
	 * The container environment preview id, which must never appear in an answer.
	 *
	 * @var string
	 */
	private const ENV_PREVIEW = 'env-preview-never-shown-77';

	/**
	 * A licence key, which must never appear in an answer.
	 *
	 * @var string
	 */
	private const LICENCE_KEY = 'licence-key-never-shown-5f3a9c';

	/**
	 * The three ability names.
	 *
	 * @var array<int, string>
	 */
	private const NAMES = [
		Abilities::GET_CONFIGURATION,
		Abilities::GET_CONSENT_STATUS,
		Abilities::GET_TRACKING_HEALTH,
	];

	/**
	 * URLs requested while an ability ran.
	 *
	 * @var array<int, string>
	 */
	private array $requests = [];

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Configure a site and register the abilities against it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TW5FD4G7' );
		$options->set_option( 'general', 'gtm_auth', self::ENV_AUTH );
		$options->set_option( 'general', 'gtm_preview', self::ENV_PREVIEW );
		$options->set_option( 'general', 'container_active', true );
		$options->set_option( 'general', 'sgtm_domain', '' );
		$options->set_option( 'general', 'google_tag_gateway', false );
		$options->set_option( 'general', 'gcm_default_settings', false );
		$options->set_option( 'general', 'gcm_analytics_storage', false );

		update_option( 'gtmkit_premium_key', self::LICENCE_KEY );
		delete_option( GoogleTagGatewayHealth::OPTION );
		delete_option( SnippetScan::OPTION );

		remove_all_filters( 'gtmkit_active_cmp' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_consent_unknown' );

		$this->register_abilities();

		$this->requests = [];
		add_filter( 'pre_http_request', [ $this, 'record_request' ], 10, 3 );
	}

	/**
	 * Remove what a test added.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ] );
		remove_all_filters( 'gtmkit_abilities_enabled' );
		remove_all_filters( 'gtmkit_active_cmp' );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_consent_unknown' );
		delete_option( 'gtmkit_premium_key' );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Record a request and answer it locally, so a request never leaves the test.
	 *
	 * @param false|array<string, mixed>|WP_Error $preempt The short-circuit value.
	 * @param array<string, mixed>                $args The request arguments.
	 * @param string                              $url The requested URL.
	 *
	 * @return WP_Error
	 */
	public function record_request( $preempt, array $args, string $url ): WP_Error {
		unset( $preempt, $args );

		$this->requests[] = $url;

		return new WP_Error( 'http_request_blocked', 'Requests are blocked in this test.' );
	}

	/**
	 * Register GTM Kit's category and abilities from scratch.
	 *
	 * The registries are built first, with every hook in place, so that
	 * firing the hooks again below registers GTM Kit's entries alone.
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		WP_Ability_Categories_Registry::get_instance();
		WP_Abilities_Registry::get_instance();

		$this->unregister_abilities();

		remove_all_actions( 'wp_abilities_api_categories_init' );
		remove_all_actions( 'wp_abilities_api_init' );

		Abilities::register( OptionsFactory::get_instance() );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, fired here to register the plugin's category.
		do_action( 'wp_abilities_api_categories_init' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, fired here to register the plugin's abilities.
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Remove GTM Kit's abilities and category from the registries.
	 *
	 * @return void
	 */
	private function unregister_abilities(): void {
		foreach ( self::NAMES as $name ) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}

		if ( wp_has_ability_category( Abilities::CATEGORY ) ) {
			wp_unregister_ability_category( Abilities::CATEGORY );
		}
	}

	/**
	 * Run an ability as an administrator and fail on any request it makes.
	 *
	 * @param string $name The ability name.
	 *
	 * @return array<string, mixed>
	 */
	private function run_as_admin( string $name ): array {
		wp_set_current_user( $this->admin_id );

		$ability = wp_get_ability( $name );
		$this->assertNotNull( $ability, $name . ' is registered.' );

		$stored_before = $this->stored_state();

		$this->requests = [];
		$result         = $ability->execute();

		$this->assertSame( [], $this->requests, $name . ' made no HTTP request.' );
		$this->assertSame( $stored_before, $this->stored_state(), $name . ' stored and scheduled nothing.' );
		$this->assertNotWPError( $result, $name . ' ran and its output matched its schema.' );
		$this->assertIsArray( $result );

		$valid = rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'output' );
		$this->assertTrue( $valid, $name . ' output matches its schema.' );

		return $result;
	}

	/**
	 * The stored check results and the scheduled events.
	 *
	 * The gateway checks reach the network through their own transport, which
	 * the HTTP API filter cannot see, but every run of them stores its result.
	 * Comparing this before and after an ability runs catches a check or a
	 * schedule triggered by the ability, whatever transport it uses.
	 *
	 * @return array<string, mixed>
	 */
	private function stored_state(): array {
		return [
			'gateway' => get_option( GoogleTagGatewayHealth::OPTION ),
			'scan'    => get_option( SnippetScan::OPTION ),
			'cron'    => _get_cron_array(),
		];
	}

	/**
	 * Store a Google tag gateway check result.
	 *
	 * @param bool $service Whether the service check passed.
	 * @param bool $script Whether the address check passed.
	 *
	 * @return void
	 */
	private function store_gateway_result( bool $service, bool $script ): void {
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
	 * Store a sample page check result.
	 *
	 * @param string $state One of the SnippetScan::STATE_* constants.
	 * @param string $reason One of the SnippetScan::REASON_* constants, or an empty string.
	 *
	 * @return void
	 */
	private function store_scan( string $state, string $reason = '' ): void {
		update_option(
			SnippetScan::OPTION,
			[
				'schema'           => SnippetScan::SCHEMA_VERSION,
				'url'              => home_url( '/' ),
				'final_url'        => home_url( '/' ),
				'scanned_at'       => 1790000000,
				'state'            => $state,
				'reason'           => $reason,
				'status'           => SnippetScan::STATE_INCONCLUSIVE === $state ? 0 : 200,
				'implementations'  => [],
				'container_output' => true,
				'cmp'              => null,
				'duplicate'        => [
					'type'       => '',
					'containers' => [],
					'culprit'    => '',
				],
			],
			false
		);
	}

	/**
	 * The category and the three abilities are registered as read-only and visible over REST.
	 */
	public function test_abilities_are_registered_read_only_and_public(): void {
		$this->assertTrue( wp_has_ability_category( Abilities::CATEGORY ) );

		foreach ( self::NAMES as $name ) {
			$ability = wp_get_ability( $name );

			$this->assertNotNull( $ability, $name . ' is registered.' );
			$this->assertSame( Abilities::CATEGORY, $ability->get_category() );
			$this->assertTrue( $ability->get_meta_item( 'show_in_rest' ) );
			$this->assertTrue( $ability->get_meta_item( 'public' ) );

			$annotations = $ability->get_meta_item( 'annotations' );
			$this->assertTrue( $annotations['readonly'] );
			$this->assertFalse( $annotations['destructive'] );
		}

		// Filtered here rather than through wp_get_abilities() arguments,
		// which WordPress accepts only from 7.1.
		$in_category = array_filter(
			wp_get_abilities(),
			static fn( $ability ): bool => Abilities::CATEGORY === $ability->get_category()
		);
		$this->assertCount( 3, $in_category );
	}

	/**
	 * Returning false from the filter keeps the category and every ability out of the registry.
	 */
	public function test_filter_turns_registration_off(): void {
		add_filter( 'gtmkit_abilities_enabled', '__return_false' );

		$this->register_abilities();

		$this->assertFalse( wp_has_ability_category( Abilities::CATEGORY ) );

		foreach ( self::NAMES as $name ) {
			$this->assertFalse( wp_has_ability( $name ), $name . ' is not registered.' );
		}
	}

	/**
	 * Anyone without manage_options is refused, and nothing runs.
	 */
	public function test_non_admin_is_refused(): void {
		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'editor' ] ) ] as $user_id ) {
			wp_set_current_user( $user_id );

			foreach ( self::NAMES as $name ) {
				$result = wp_get_ability( $name )->execute();

				$this->assertWPError( $result, $name . ' refuses user ' . $user_id . '.' );
				$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
			}
		}
	}

	/**
	 * The abilities list over REST under the GTM Kit category, and a non-admin is refused there too.
	 */
	public function test_rest_lists_and_runs_for_admin_only(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'category', Abilities::CATEGORY );
		$request->set_param( 'per_page', 100 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$listed = wp_list_pluck( wp_list_filter( $response->get_data(), [ 'category' => Abilities::CATEGORY ] ), 'name' );
		sort( $listed );
		$expected = self::NAMES;
		sort( $expected );
		$this->assertSame( $expected, $listed );

		$run      = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . Abilities::GET_CONFIGURATION . '/run' );
		$response = rest_get_server()->dispatch( $run );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'GTM-TW5FD4G7', $response->get_data()['gtm_id'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$response = rest_get_server()->dispatch( $run );
		$this->assertContains( $response->get_status(), [ 401, 403 ] );
	}

	/**
	 * No answer carries environment values or licence data, and none makes a request.
	 */
	public function test_no_secrets_in_any_output(): void {
		$this->store_gateway_result( true, true );
		$this->store_scan( SnippetScan::STATE_FOUND );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'google_tag_gateway', true );

		foreach ( self::NAMES as $name ) {
			$json = (string) wp_json_encode( $this->run_as_admin( $name ) );

			$this->assertStringNotContainsString( self::ENV_AUTH, $json, $name );
			$this->assertStringNotContainsString( self::ENV_PREVIEW, $json, $name );
			$this->assertStringNotContainsString( self::LICENCE_KEY, $json, $name );
		}

		$configuration = $this->run_as_admin( Abilities::GET_CONFIGURATION );
		$this->assertTrue( $configuration['environment_parameters_configured'] );
	}

	/**
	 * The configuration reports the saved container and a site without the gateway.
	 */
	public function test_configuration_reports_saved_settings(): void {
		$result = $this->run_as_admin( Abilities::GET_CONFIGURATION );

		$this->assertSame( 'GTM-TW5FD4G7', $result['gtm_id'] );
		$this->assertSame( 'dataLayer', $result['datalayer_name'] );
		$this->assertNull( $result['server_side_tagging_domain'] );
		$this->assertSame( 'standard', $result['loader']['in_use'] );
		$this->assertSame( 'off', $result['google_tag_gateway'] );
		$this->assertSame( GTMKIT_VERSION, $result['version'] );
	}

	/**
	 * Each Google tag gateway state maps to its own answer.
	 */
	public function test_gateway_states_map_correctly(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'google_tag_gateway', true );

		$this->store_gateway_result( true, false );
		$this->assertSame( 'falling_back', $this->run_as_admin( Abilities::GET_CONFIGURATION )['google_tag_gateway'] );

		delete_option( GoogleTagGatewayHealth::OPTION );
		$this->assertSame( 'falling_back', $this->run_as_admin( Abilities::GET_CONFIGURATION )['google_tag_gateway'] );

		$this->store_gateway_result( true, true );
		$this->assertSame( 'active', $this->run_as_admin( Abilities::GET_CONFIGURATION )['google_tag_gateway'] );

		$options->set_option( 'general', 'sgtm_domain', 'collect.example.com' );
		$result = $this->run_as_admin( Abilities::GET_CONFIGURATION );
		$this->assertSame( 'blocked_by_custom_domain', $result['google_tag_gateway'] );
		$this->assertSame( 'collect.example.com', $result['server_side_tagging_domain'] );
	}

	/**
	 * Defaults that deny analytics with nothing to lift them are reported as such.
	 */
	public function test_consent_nothing_to_lift(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', true );
		$options->set_option( 'general', 'gcm_analytics_storage', false );

		$result = $this->run_as_admin( Abilities::GET_CONSENT_STATUS );

		$this->assertTrue( $result['consent_mode_defaults_enabled'] );
		$this->assertTrue( $result['defaults_are_only_source'] );
		$this->assertFalse( $result['defaults_grant_analytics'] );
		$this->assertTrue( $result['nothing_to_lift_defaults'] );
		$this->assertNull( $result['consent_platform'] );
		$this->assertSame( 'gtmkit_default', $result['consent_source'] );
		$this->assertSame( 'denied', $result['consent_state']['analytics_storage'] );
		$this->assertSame( 'allow', $result['unknown_consent'] );
	}

	/**
	 * A consent integration in charge is reported, and the defaults then have something to lift them.
	 */
	public function test_consent_integration_source(): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gcm_default_settings', true );

		add_filter(
			'gtmkit_consent_signal_sources',
			static function ( $sources ) {
				$sources['wp_consent_api'] = [
					'id'        => 'wp_consent_api',
					'priority'  => 100,
					'is_active' => static fn(): bool => true,
					'read'      => static fn(): array => [ 'analytics_storage' => 'denied' ],
				];

				return $sources;
			}
		);
		add_filter( 'gtmkit_consent_unknown', static fn(): string => 'deny' );

		$result = $this->run_as_admin( Abilities::GET_CONSENT_STATUS );

		$this->assertSame( 'wp_consent_api', $result['consent_source'] );
		$this->assertTrue( $result['integration_owns_consent'] );
		$this->assertFalse( $result['defaults_are_only_source'] );
		$this->assertFalse( $result['nothing_to_lift_defaults'] );
		$this->assertSame( 'deny', $result['unknown_consent'] );
	}

	/**
	 * An inconclusive scan stays unknown rather than becoming no.
	 */
	public function test_snippet_scan_unknown_stays_unknown(): void {
		$this->store_scan( SnippetScan::STATE_INCONCLUSIVE, SnippetScan::REASON_LOOPBACK_BLOCKED );

		$scan = $this->run_as_admin( Abilities::GET_TRACKING_HEALTH )['snippet_scan'];

		$this->assertSame( SnippetScan::STATE_INCONCLUSIVE, $scan['state'] );
		$this->assertSame( SnippetScan::REASON_LOOPBACK_BLOCKED, $scan['reason'] );
		$this->assertNull( $scan['has_container'] );
		$this->assertNull( $scan['has_duplicate_tracking'] );
		$this->assertSame( 1790000000, $scan['scanned_at'] );

		$this->store_scan( SnippetScan::STATE_NOT_FOUND );

		$scan = $this->run_as_admin( Abilities::GET_TRACKING_HEALTH )['snippet_scan'];
		$this->assertFalse( $scan['has_container'] );
		$this->assertNull( $scan['has_duplicate_tracking'] );
	}

	/**
	 * With no scan stored and the gateway off, the health answer says so and carries the Site Health results in plain text.
	 */
	public function test_tracking_health_without_stored_results(): void {
		$result = $this->run_as_admin( Abilities::GET_TRACKING_HEALTH );

		$this->assertNull( $result['snippet_scan'] );
		$this->assertNull( $result['google_tag_gateway'] );

		$tests = wp_list_pluck( $result['checks'], 'test' );
		$this->assertSame( [ 'gtmkit_container', 'gtmkit_consent', 'gtmkit_tracking_scan' ], $tests );

		foreach ( $result['checks'] as $check ) {
			$this->assertSame( wp_strip_all_tags( $check['description'] ), $check['description'] );
			$this->assertStringNotContainsString( '<', $check['label'] . $check['description'] );
		}
	}

	/**
	 * With the gateway on, the health answer carries its checks and stored result.
	 */
	public function test_tracking_health_with_gateway_on(): void {
		OptionsFactory::get_instance()->set_option( 'general', 'google_tag_gateway', true );
		$this->store_gateway_result( false, true );

		$result = $this->run_as_admin( Abilities::GET_TRACKING_HEALTH );

		$this->assertTrue( $result['google_tag_gateway']['checked'] );
		$this->assertFalse( $result['google_tag_gateway']['passing'] );
		$this->assertFalse( $result['google_tag_gateway']['service_reachable'] );
		$this->assertTrue( $result['google_tag_gateway']['address_reachable'] );

		$statuses = array_column( $result['checks'], 'status', 'test' );
		$this->assertSame( 'critical', $statuses['gtmkit_gateway_service'] );
		$this->assertSame( 'good', $statuses['gtmkit_gateway_script'] );
	}
}
