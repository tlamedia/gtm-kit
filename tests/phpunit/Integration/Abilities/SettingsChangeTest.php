<?php
/**
 * Integration tests for changing settings through the Abilities API.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Abilities\SettingsChange}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Abilities;

use TLA_Media\GTM_Kit\Abilities\Abilities;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_Abilities_Registry;
use WP_Ability_Categories_Registry;
use WP_Error;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A change is previewed without writing, and applied only with the code the preview issued.
 */
final class SettingsChangeTest extends WP_UnitTestCase {

	/**
	 * Every GTM Kit ability name.
	 *
	 * @var array<int, string>
	 */
	private const NAMES = [
		Abilities::GET_CONFIGURATION,
		Abilities::GET_CONSENT_STATUS,
		Abilities::GET_TRACKING_HEALTH,
		Abilities::PREVIEW_SETTINGS_CHANGE,
		Abilities::APPLY_SETTINGS_CHANGE,
	];

	/**
	 * URLs requested during a test.
	 *
	 * @var array<int, string>
	 */
	private array $requests = [];

	/**
	 * Calls of the change action, as [ user ID, settings ].
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $changed = [];

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Configure a site and register the abilities with writes switched on.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-OLD1234' );
		$options->set_option( 'general', 'gtm_auth', '' );
		$options->set_option( 'general', 'gtm_preview', '' );
		$options->set_option( 'general', 'sgtm_domain', '' );
		$options->set_option( 'general', 'google_tag_gateway', false );
		$options->set_option( 'general', 'gcm_default_settings', false );
		$options->set_option( 'general', 'gcm_analytics_storage', false );

		delete_option( GoogleTagGatewayHealth::OPTION );
		remove_all_filters( 'gtmkit_consent_signal_sources' );
		remove_all_filters( 'gtmkit_abilities_writable_settings' );

		add_filter( 'gtmkit_write_abilities_enabled', '__return_true' );
		$this->register_abilities();

		$this->requests = [];
		$this->changed  = [];
		add_filter( 'pre_http_request', [ $this, 'record_request' ], 10, 3 );
		add_action( 'gtmkit_settings_changed_by_ability', [ $this, 'record_change' ], 10, 2 );

		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Remove what a test added.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ] );
		remove_action( 'gtmkit_settings_changed_by_ability', [ $this, 'record_change' ] );
		remove_all_filters( 'gtmkit_write_abilities_enabled' );
		remove_all_filters( 'gtmkit_abilities_enabled' );
		remove_all_filters( 'gtmkit_abilities_writable_settings' );
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
	 * Record a call of the change action.
	 *
	 * @param int                $user_id The user.
	 * @param array<int, string> $settings The settings written.
	 *
	 * @return void
	 */
	public function record_change( int $user_id, array $settings ): void {
		$this->changed[] = [ $user_id, $settings ];
	}

	/**
	 * Register GTM Kit's category and abilities from scratch.
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		WP_Ability_Categories_Registry::get_instance();
		WP_Abilities_Registry::get_instance();

		foreach ( self::NAMES as $name ) {
			if ( wp_has_ability( $name ) ) {
				wp_unregister_ability( $name );
			}
		}

		if ( wp_has_ability_category( Abilities::CATEGORY ) ) {
			wp_unregister_ability_category( Abilities::CATEGORY );
		}

		remove_all_actions( 'wp_abilities_api_categories_init' );
		remove_all_actions( 'wp_abilities_api_init' );

		Abilities::register( OptionsFactory::get_instance() );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, fired here to register the plugin's category.
		do_action( 'wp_abilities_api_categories_init' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- A WordPress core hook, fired here to register the plugin's abilities.
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * The stored settings, exactly as the database holds them.
	 *
	 * @return string
	 */
	private function stored(): string {
		wp_cache_delete( Options::OPTION_NAME, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return (string) maybe_serialize( get_option( Options::OPTION_NAME ) );
	}

	/**
	 * Preview a change as the current user.
	 *
	 * @param array<string, mixed> $changes The change.
	 *
	 * @return array<string, mixed>
	 */
	private function preview( array $changes ): array {
		$ability = wp_get_ability( Abilities::PREVIEW_SETTINGS_CHANGE );
		$result  = $ability->execute( [ 'changes' => $changes ] );

		$this->assertNotWPError( $result );
		$this->assertTrue( rest_validate_value_from_schema( $result, $ability->get_output_schema(), 'output' ), 'The preview matches its schema.' );

		return $result;
	}

	/**
	 * Apply a confirmation code as the current user.
	 *
	 * @param string $token The code.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function apply( string $token ) {
		return wp_get_ability( Abilities::APPLY_SETTINGS_CHANGE )->execute( [ 'confirmation_token' => $token ] );
	}

	/**
	 * The preview's entry for a setting.
	 *
	 * @param array<string, mixed> $preview The preview.
	 * @param string               $setting The setting, as group.key.
	 *
	 * @return array<string, mixed>
	 */
	private static function entry( array $preview, string $setting ): array {
		foreach ( $preview['settings'] as $entry ) {
			if ( $setting === $entry['setting'] ) {
				return $entry;
			}
		}

		return [];
	}

	/**
	 * Without the filter, neither write ability is registered and the read abilities are as before.
	 */
	public function test_write_abilities_absent_by_default(): void {
		remove_all_filters( 'gtmkit_write_abilities_enabled' );
		$this->register_abilities();

		$this->assertFalse( wp_has_ability( Abilities::PREVIEW_SETTINGS_CHANGE ) );
		$this->assertFalse( wp_has_ability( Abilities::APPLY_SETTINGS_CHANGE ) );

		$in_category = array_filter(
			wp_get_abilities(),
			static fn( $ability ): bool => Abilities::CATEGORY === $ability->get_category()
		);
		$this->assertCount( 3, $in_category );

		foreach ( $in_category as $ability ) {
			$this->assertTrue( $ability->get_meta_item( 'annotations' )['readonly'], $ability->get_name() . ' stays read-only.' );
		}
	}

	/**
	 * The write abilities stay out when the abilities as a whole are switched off.
	 */
	public function test_write_abilities_need_the_abilities_filter_too(): void {
		add_filter( 'gtmkit_abilities_enabled', '__return_false' );
		$this->register_abilities();

		$this->assertFalse( wp_has_ability( Abilities::PREVIEW_SETTINGS_CHANGE ) );
		$this->assertFalse( wp_has_ability( Abilities::APPLY_SETTINGS_CHANGE ) );
	}

	/**
	 * With the filter on, both write abilities register with honest hints and are exposed to every MCP Adapter version.
	 */
	public function test_write_abilities_registered_with_filter_on(): void {
		$preview = wp_get_ability( Abilities::PREVIEW_SETTINGS_CHANGE );
		$apply   = wp_get_ability( Abilities::APPLY_SETTINGS_CHANGE );

		$this->assertNotNull( $preview );
		$this->assertNotNull( $apply );
		$this->assertSame(
			[
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => false,
			],
			$preview->get_meta_item( 'annotations' )
		);
		$this->assertSame(
			[
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			],
			$apply->get_meta_item( 'annotations' )
		);
		$this->assertSame( [ 'confirmation_token' ], array_keys( $apply->get_input_schema()['properties'] ) );
		$this->assertSame( [ 'public' => true ], $preview->get_meta_item( 'mcp' ) );
		$this->assertSame( [ 'public' => true ], $apply->get_meta_item( 'mcp' ) );
	}

	/**
	 * A preview writes nothing, sends nothing, and issues a code.
	 */
	public function test_preview_writes_nothing(): void {
		$before = $this->stored();

		$result = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] );

		$this->assertSame( $before, $this->stored(), 'The stored settings are byte-identical.' );
		$this->assertSame( [], $this->requests );
		$this->assertSame( 'GTM-OLD1234', self::entry( $result, 'general.gtm_id' )['current'] );
		$this->assertSame( 'GTM-NEW5678', self::entry( $result, 'general.gtm_id' )['proposed'] );
		$this->assertTrue( self::entry( $result, 'general.gtm_id' )['kept'] );
		$this->assertSame( [], $result['refused'] );
		$this->assertIsString( $result['confirmation_token'] );
		$this->assertSame( 64, strlen( $result['confirmation_token'] ) );
		$this->assertGreaterThan( time(), $result['expires_at'] );
	}

	/**
	 * The code itself is never stored, only its hash.
	 */
	public function test_only_the_hash_of_a_code_is_stored(): void {
		global $wpdb;

		$token = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] )['confirmation_token'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Searching every stored option for the raw code.
		$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s", '%' . $wpdb->esc_like( $token ) . '%', '%' . $wpdb->esc_like( $token ) . '%' ) );

		$this->assertSame( 0, $found );
	}

	/**
	 * A setting outside the list, a never-writable setting added by the filter, and an invalid value are refused.
	 */
	public function test_preview_refuses_by_name(): void {
		add_filter(
			'gtmkit_abilities_writable_settings',
			static fn( array $settings ): array => array_merge( $settings, [ 'general.gtm_auth', 'premium.license_key' ] )
		);

		$before = $this->stored();
		$result = $this->preview(
			[
				'general' => [
					'console_log' => true,
					'gtm_auth'    => 'leak',
					'gtm_id'      => 'not a container',
				],
				'premium' => [ 'license_key' => 'x' ],
			]
		);

		$refused = wp_list_pluck( $result['refused'], 'setting' );
		sort( $refused );

		$this->assertSame( [ 'general.console_log', 'general.gtm_auth', 'general.gtm_id', 'premium.license_key' ], $refused );
		$this->assertSame( [], $result['settings'] );
		$this->assertNull( $result['confirmation_token'] );
		$this->assertSame( $before, $this->stored() );
	}

	/**
	 * An assistant can propose a valid data layer name, and is refused an
	 * invalid one with the reason the settings screen gives.
	 */
	public function test_preview_checks_the_datalayer_name(): void {
		$valid = $this->preview( [ 'general' => [ 'datalayer_name' => 'shopLayer' ] ] );

		$this->assertSame( [], $valid['refused'] );
		$this->assertSame( 'shopLayer', self::entry( $valid, 'general.datalayer_name' )['proposed'] );
		$this->assertNotNull( $valid['confirmation_token'] );

		$before  = $this->stored();
		$invalid = $this->preview( [ 'general' => [ 'datalayer_name' => 'shop layer' ] ] );

		$this->assertSame(
			[
				[
					'setting' => 'general.datalayer_name',
					'reason'  => 'The value was rejected: value not accepted.',
				],
			],
			$invalid['refused']
		);
		$this->assertNull( $invalid['confirmation_token'] );
		$this->assertSame( $before, $this->stored() );
	}

	/**
	 * A preview that renames the data layer warns that other scripts using
	 * the old name need updating, without refusing the change.
	 */
	public function test_preview_warns_when_the_datalayer_is_renamed(): void {
		$renamed = $this->preview( [ 'general' => [ 'datalayer_name' => 'shopLayer' ] ] );

		$this->assertCount( 1, $renamed['warnings'] );
		$this->assertStringContainsString( 'old name', $renamed['warnings'][0] );
		$this->assertNotNull( $renamed['confirmation_token'] );
	}

	/**
	 * No warning when the name stays the same or another setting changes.
	 */
	public function test_preview_does_not_warn_without_a_rename(): void {
		$current = (string) OptionsFactory::get_instance()->get( 'general', 'datalayer_name' );

		$this->assertSame( [], $this->preview( [ 'general' => [ 'datalayer_name' => $current ] ] )['warnings'] );
		$this->assertSame( [], $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] )['warnings'] );
		$this->assertSame( [], $this->preview( [ 'general' => [ 'datalayer_name' => 'shop layer' ] ] )['warnings'], 'A refused name renames nothing.' );
	}

	/**
	 * An empty name is the default, so spelling the default out renames nothing.
	 */
	public function test_preview_does_not_warn_when_the_default_is_spelled_out(): void {
		OptionsFactory::get_instance()->set_option( 'general', 'datalayer_name', '' );

		$this->assertSame( [], $this->preview( [ 'general' => [ 'datalayer_name' => 'dataLayer' ] ] )['warnings'] );
		$this->assertCount( 1, $this->preview( [ 'general' => [ 'datalayer_name' => 'shopLayer' ] ] )['warnings'] );
	}

	/**
	 * A setting the filter adds becomes writable.
	 */
	public function test_filter_can_add_a_setting(): void {
		add_filter(
			'gtmkit_abilities_writable_settings',
			static fn( array $settings ): array => array_merge( $settings, [ 'general.console_log' ] )
		);

		$result = $this->preview( [ 'general' => [ 'console_log' => true ] ] );

		$this->assertSame( [], $result['refused'] );
		$this->assertTrue( self::entry( $result, 'general.console_log' )['proposed'] );
	}

	/**
	 * The filter cannot take a core setting off the list.
	 */
	public function test_filter_cannot_remove_a_core_setting(): void {
		add_filter( 'gtmkit_abilities_writable_settings', '__return_empty_array' );

		$result = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] );

		$this->assertSame( [], $result['refused'] );
		$this->assertIsString( $result['confirmation_token'] );
	}

	/**
	 * A change that would store nothing new gets no code.
	 */
	public function test_no_code_when_nothing_changes(): void {
		$result = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-OLD1234' ] ] );

		$this->assertFalse( self::entry( $result, 'general.gtm_id' )['changes'] );
		$this->assertNull( $result['confirmation_token'] );
	}

	/**
	 * A normalised value is proposed as the save would store it, and stored that way.
	 */
	public function test_preview_proposes_what_the_save_stores(): void {
		$result = $this->preview(
			[
				'general' => [
					'sgtm_domain'           => 'https://collect.example.com/',
					'gcm_analytics_storage' => '1',
				],
			]
		);

		$domain = self::entry( $result, 'general.sgtm_domain' );
		$this->assertSame( 'collect.example.com', $domain['proposed'] );
		$this->assertFalse( $domain['kept'] );
		$this->assertTrue( self::entry( $result, 'general.gcm_analytics_storage' )['proposed'] );

		$applied = $this->apply( $result['confirmation_token'] );

		$this->assertNotWPError( $applied );
		$this->assertTrue( $applied['applied'] );

		$stored = maybe_unserialize( $this->stored() );
		$this->assertSame( 'collect.example.com', $stored['general']['sgtm_domain'] );
		$this->assertTrue( $stored['general']['gcm_analytics_storage'] );
	}

	/**
	 * A code applies its change, leaves the other settings alone, and reports the result.
	 */
	public function test_apply_with_a_fresh_code(): void {
		$token  = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] )['confirmation_token'];
		$result = $this->apply( $token );

		$this->assertNotWPError( $result );
		$this->assertTrue( rest_validate_value_from_schema( $result, wp_get_ability( Abilities::APPLY_SETTINGS_CHANGE )->get_output_schema(), 'output' ) );
		$this->assertTrue( $result['applied'] );
		$this->assertSame( [ 'general.gtm_id' ], $result['written'] );
		$this->assertSame( [], $result['not_kept'] );
		$this->assertNull( $result['refusal'] );
		$this->assertSame( 'GTM-NEW5678', $result['configuration']['gtm_id'] );
		$this->assertArrayHasKey( 'consent_state', $result['consent_status'] );

		$stored = maybe_unserialize( $this->stored() );
		$this->assertSame( 'GTM-NEW5678', $stored['general']['gtm_id'] );
		$this->assertFalse( $stored['general']['google_tag_gateway'], 'Other settings are kept.' );

		$this->assertSame( [ [ $this->admin_id, [ 'general.gtm_id' ] ] ], $this->changed );
	}

	/**
	 * A used, expired, other user's or stale code is refused and writes nothing.
	 */
	public function test_refused_codes_write_nothing(): void {
		// Used.
		$token = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] )['confirmation_token'];
		$this->assertNotWPError( $this->apply( $token ) );
		$before = $this->stored();
		$this->assert_refused( $this->apply( $token ), 'already been used' );
		$this->assertSame( $before, $this->stored() );

		// Unknown.
		$this->assert_refused( $this->apply( str_repeat( 'a', 64 ) ), 'not known' );

		// Expired.
		$token = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-EXP1234' ] ] )['confirmation_token'];
		$name  = 'gtmkit_settings_change_' . hash( 'sha256', $token );
		$entry = get_transient( $name );

		$entry['expires_at'] = time() - 1;
		set_transient( $name, $entry, HOUR_IN_SECONDS );
		$this->assert_refused( $this->apply( $token ), 'expired' );
		$this->assertSame( $before, $this->stored() );

		// Another user's.
		$token = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-OTH1234' ] ] )['confirmation_token'];
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assert_refused( $this->apply( $token ), 'another user' );
		wp_set_current_user( $this->admin_id );
		$this->assert_refused( $this->apply( $token ), 'already been used' );
		$this->assertSame( $before, $this->stored() );

		// Stale: the settings changed after the preview.
		$token = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-STL1234' ] ] )['confirmation_token'];
		OptionsFactory::get_instance()->set_option( 'general', 'gcm_default_settings', true );
		$before = $this->stored();
		$this->assert_refused( $this->apply( $token ), 'settings changed' );
		$this->assertSame( $before, $this->stored() );

		$this->assertCount( 1, $this->changed, 'Only the first apply fired the change action.' );
	}

	/**
	 * A code another request is applying at this moment is refused, and the claim is released afterwards.
	 */
	public function test_code_being_applied_elsewhere_is_refused(): void {
		global $wpdb;

		$token  = $this->preview( [ 'general' => [ 'gtm_id' => 'GTM-RACE1234' ] ] )['confirmation_token'];
		$lock   = 'gtmkit_settings_change_lock_' . hash( 'sha256', $token );
		$before = $this->stored();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Standing in for a simultaneous apply holding the claim.
		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => $lock,
				'option_value' => '1',
				'autoload'     => 'off',
			]
		);

		$this->assert_refused( $this->apply( $token ), 'already been used' );
		$this->assertSame( $before, $this->stored() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Releasing the stand-in claim.
		$wpdb->delete( $wpdb->options, [ 'option_name' => $lock ] );

		$this->assertTrue( $this->apply( $token )['applied'], 'The code was not used up by the refused attempt.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking the claim row directly.
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", $lock ) ), 'The claim is released.' );
	}

	/**
	 * Assert that an apply was refused with a reason.
	 *
	 * @param mixed  $result The apply result.
	 * @param string $reason Text the reason contains.
	 *
	 * @return void
	 */
	private function assert_refused( $result, string $reason ): void {
		$this->assertWPError( $result );
		$this->assertSame( 'gtmkit_confirmation_refused', $result->get_error_code() );
		$this->assertStringContainsString( $reason, $result->get_error_message() );
	}

	/**
	 * Switching the gateway on is not checked over the network in the preview.
	 */
	public function test_preview_never_requests_for_the_gateway(): void {
		$result = $this->preview( [ 'general' => [ 'google_tag_gateway' => true ] ] );

		$this->assertSame( [], $this->requests );
		$this->assertFalse( get_option( GoogleTagGatewayHealth::OPTION ), 'No gateway check ran.' );
		$this->assertTrue( $result['gateway_checked_on_apply'] );
		$this->assertNull( $result['gateway_refusal'] );
		$this->assertIsString( $result['confirmation_token'] );
	}

	/**
	 * A gateway switch-on ruled out by a custom domain is refused in the preview, with no code.
	 */
	public function test_preview_reports_gateway_refusal_for_a_custom_domain(): void {
		$result = $this->preview(
			[
				'general' => [
					'google_tag_gateway' => true,
					'sgtm_domain'        => 'collect.example.com',
				],
			]
		);

		$this->assertSame( [], $this->requests );
		$this->assertIsString( $result['gateway_refusal'] );
		$this->assertFalse( $result['gateway_checked_on_apply'] );
		$this->assertNull( $result['confirmation_token'] );
	}

	/**
	 * Anyone without manage_options is refused both abilities.
	 */
	public function test_non_admin_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$preview = wp_get_ability( Abilities::PREVIEW_SETTINGS_CHANGE )->execute( [ 'changes' => [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] ] );
		$apply   = $this->apply( str_repeat( 'a', 64 ) );

		$this->assertSame( 'ability_invalid_permissions', $preview->get_error_code() );
		$this->assertSame( 'ability_invalid_permissions', $apply->get_error_code() );
	}

	/**
	 * An administrator without the capability the settings screen requires is refused both abilities, and nothing is stored.
	 */
	public function test_settings_capability_is_required(): void {
		$before = $this->stored();

		add_filter(
			'gtmkit_admin_capability',
			static function (): string {
				return 'manage_network_options';
			}
		);

		$preview = wp_get_ability( Abilities::PREVIEW_SETTINGS_CHANGE )->execute( [ 'changes' => [ 'general' => [ 'gtm_id' => 'GTM-NEW5678' ] ] ] );
		$apply   = $this->apply( str_repeat( 'a', 64 ) );

		remove_all_filters( 'gtmkit_admin_capability' );

		$this->assertFalse( current_user_can( 'manage_network_options' ), 'The administrator lacks the capability.' );
		$this->assertSame( 'ability_invalid_permissions', $preview->get_error_code() );
		$this->assertSame( 'ability_invalid_permissions', $apply->get_error_code() );
		$this->assertSame( $before, $this->stored() );
		$this->assertNotWPError( wp_get_ability( Abilities::GET_CONFIGURATION )->execute(), 'Reading still needs only manage_options.' );
	}

	/**
	 * Over REST, the preview runs as GET and text values reach the save as their setting's type.
	 */
	public function test_rest_preview_and_apply(): void {
		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . Abilities::PREVIEW_SETTINGS_CHANGE . '/run' );
		$request->set_query_params(
			[
				'input' => [
					'changes' => [
						'general' => [
							'gtm_id'                => 'GTM-RST1234',
							'gcm_default_settings'  => 'true',
							'gcm_analytics_storage' => 'false',
						],
					],
				],
			]
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$preview = $response->get_data();
		$this->assertSame( [], $preview['refused'] );
		$this->assertTrue( self::entry( $preview, 'general.gcm_default_settings' )['proposed'] );

		$apply = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/' . Abilities::APPLY_SETTINGS_CHANGE . '/run' );
		$apply->set_header( 'Content-Type', 'application/json' );
		$apply->set_body( (string) wp_json_encode( [ 'input' => [ 'confirmation_token' => $preview['confirmation_token'] ] ] ) );
		$response = rest_get_server()->dispatch( $apply );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'GTM-RST1234', $response->get_data()['configuration']['gtm_id'] );

		$response = rest_get_server()->dispatch( $apply );
		$this->assertSame( 409, $response->get_status() );
	}
}
