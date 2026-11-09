<?php
/**
 * Unit tests for the GTM4WP branch of the settings importer.
 *
 * Covers both storage shapes the source plugin has shipped: the per-container
 * row array of the current line, and the flat option keys of the older one
 * where every container ID shares a single delimited string.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Installation;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Extraction tests for PluginDataImport::get( 'gtm4wp' ).
 */
final class PluginDataImportGtm4wpTest extends TestCase {

	/**
	 * Common setup.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		Functions\stubs(
			[
				'sanitize_key' => static fn( $key ) => strtolower( (string) $key ),
				'wp_parse_url' => static fn( $url ) => parse_url( $url ), // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Stands in for the WordPress wrapper in a unit test.
			]
		);
	}

	/**
	 * Extract the GTM4WP settings from a stubbed option row.
	 *
	 * @param mixed                $stored The value `gtm4wp-options` resolves to.
	 * @param array<string, mixed> $gtmkit The GTM Kit settings already stored.
	 *
	 * @return array<string, mixed>
	 */
	private function extract( $stored, array $gtmkit = [] ): array {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) use ( $stored, $gtmkit ) {
				if ( 'gtm4wp-options' === $name ) {
					return $stored;
				}

				return ( 'gtmkit' === $name && [] !== $gtmkit ) ? $gtmkit : $default_value;
			}
		);

		return ( new PluginDataImport() )->get( 'gtm4wp' );
	}

	/**
	 * A site without the source plugin's option offers nothing to import.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_no_stored_options_yields_no_import_data(): void {
		$this->assertSame( [], $this->extract( false ) );
		$this->assertSame( [], $this->extract( [] ) );
	}

	/**
	 * A single flat container ID imports as-is.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_flat_options_import_the_container_and_its_environment(): void {
		$data = $this->extract(
			[
				'gtm-code'            => 'GTM-FLAT01',
				'gtm-domain-name'     => 'sgtm.example.com',
				'gtm-env-gtm-auth'    => 'AUTHVALUE',
				'gtm-env-gtm-preview' => 'env-12',
			]
		);

		$this->assertSame( 1, $data['container_count'] );
		$this->assertSame( 'GTM-FLAT01', $data['general']['gtm_id'] );
		$this->assertSame( 'sgtm.example.com', $data['general']['sgtm_domain'] );
		$this->assertSame( 'AUTHVALUE', $data['general']['gtm_auth'] );
		$this->assertSame( 'env-12', $data['general']['gtm_preview'] );
	}

	/**
	 * The flat key holds a delimited list; only the first container is used and
	 * the total is reported so the UI can say the rest were skipped.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_flat_options_with_several_containers_import_only_the_first(): void {
		$data = $this->extract( [ 'gtm-code' => 'GTM-FIRST1, GTM-SECOND;GTM-THIRD3' ] );

		$this->assertSame( 'GTM-FIRST1', $data['general']['gtm_id'] );
		$this->assertSame( 3, $data['container_count'] );
	}

	/**
	 * Container rows are authoritative: the flat keys are stale mirrors on a
	 * site that has since edited its containers.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_container_rows_take_precedence_over_the_flat_mirrors(): void {
		$data = $this->extract(
			[
				'gtm-containers'  => [
					[
						'id'          => 'GTM-ROW001',
						'domain'      => 'rows.example.com',
						'gtm_auth'    => 'ROWAUTH',
						'gtm_preview' => 'env-9',
					],
				],
				'gtm-code'        => 'GTM-STALE1',
				'gtm-domain-name' => 'stale.example.com',
			]
		);

		$this->assertSame( 1, $data['container_count'] );
		$this->assertSame( 'GTM-ROW001', $data['general']['gtm_id'] );
		$this->assertSame( 'rows.example.com', $data['general']['sgtm_domain'] );
		$this->assertSame( 'ROWAUTH', $data['general']['gtm_auth'] );
		$this->assertSame( 'env-9', $data['general']['gtm_preview'] );
	}

	/**
	 * With several rows the first one's settings are imported and the rest are
	 * only counted.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_several_container_rows_import_only_the_first(): void {
		$data = $this->extract(
			[
				'gtm-containers' => [
					[
						'id'          => 'GTM-FIRST1',
						'gtm_auth'    => 'FIRSTAUTH',
						'gtm_preview' => 'env-1',
					],
					[ 'id' => 'GTM-SECOND' ],
					[ 'id' => 'GTM-THIRD3' ],
				],
			]
		);

		$this->assertSame( 3, $data['container_count'] );
		$this->assertSame( 'GTM-FIRST1', $data['general']['gtm_id'] );
		$this->assertSame( 'FIRSTAUTH', $data['general']['gtm_auth'] );
	}

	/**
	 * Rows without a container ID are not containers, and an empty row list
	 * falls back to the flat keys rather than reporting nothing.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_unusable_container_rows_fall_back_to_the_flat_options(): void {
		$data = $this->extract(
			[
				'gtm-containers' => [ [ 'id' => '' ], 'not-a-row' ],
				'gtm-code'       => 'GTM-FALLBK',
			]
		);

		$this->assertSame( 1, $data['container_count'] );
		$this->assertSame( 'GTM-FALLBK', $data['general']['gtm_id'] );
	}

	/**
	 * A configured container with no settings beyond it imports only what is
	 * there: absent source options must not overwrite GTM Kit's own defaults.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_settings_the_source_never_stored_are_left_out(): void {
		$data = $this->extract( [ 'gtm-code' => 'GTM-BARE01' ] );

		$this->assertArrayNotHasKey( 'datalayer_post_type', $data['general'] );
		$this->assertArrayNotHasKey( 'gcm_default_settings', $data['general'] );
		$this->assertArrayNotHasKey( 'exclude_user_roles', $data['general'] );
		$this->assertSame( [], $data['integrations'] );
	}

	/**
	 * A stored `false` is a real setting and must survive, unlike an absent one.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_stored_false_values_are_imported_rather_than_skipped(): void {
		$data = $this->extract(
			[
				'gtm-code'               => 'GTM-BOOL01',
				'include-posttype'       => false,
				'integrate-consent-mode' => false,
				'integrate-wpcf7'        => false,
			]
		);

		$this->assertFalse( $data['general']['datalayer_post_type'] );
		$this->assertFalse( $data['general']['gcm_default_settings'] );
		$this->assertFalse( $data['integrations']['cf7_integration'] );
	}

	/**
	 * The data layer, consent and engagement settings map onto their GTM Kit
	 * counterparts.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_datalayer_consent_and_engagement_settings_are_mapped(): void {
		$data = $this->extract(
			[
				'gtm-code'                            => 'GTM-MAP001',
				'gtm-datalayer-variable-name'         => 'customLayer',
				'include-posttitle'                   => true,
				'include-userrole'                    => true,
				'integrate-consent-mode'              => true,
				'integrate-consent-mode-ads'          => true,
				'integrate-consent-mode-ad-user-data' => true,
				'integrate-consent-mode-ad-perso'     => true,
				'integrate-consent-mode-analytics'    => true,
				'integrate-consent-mode-perso'        => true,
				'integrate-consent-mode-func'         => true,
				'integrate-consent-mode-security'     => true,
				'event-user-logged-in'                => true,
				'event-new-user-registration'         => true,
			]
		);

		$this->assertSame( 'customLayer', $data['general']['datalayer_name'] );
		$this->assertTrue( $data['general']['datalayer_post_title'] );
		$this->assertTrue( $data['general']['datalayer_user_role'] );
		$this->assertTrue( $data['general']['gcm_ad_storage'] );
		$this->assertTrue( $data['general']['gcm_ad_user_data'] );
		$this->assertTrue( $data['general']['gcm_ad_personalization'] );
		$this->assertTrue( $data['general']['gcm_analytics_storage'] );
		$this->assertTrue( $data['general']['gcm_personalization_storage'] );
		$this->assertTrue( $data['general']['gcm_functionality_storage'] );
		$this->assertTrue( $data['general']['gcm_security_storage'] );
		$this->assertTrue( $data['general']['engagement_event_login_enabled'] );
		$this->assertTrue( $data['general']['engagement_event_signup_enabled'] );
	}

	/**
	 * Excluded user roles are a delimited string on the source side and a list
	 * on ours.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_excluded_user_roles_become_a_list_of_role_slugs(): void {
		$data = $this->extract(
			[
				'gtm-code'                 => 'GTM-ROLE01',
				'gtm-no-gtm-for-logged-in' => 'administrator, editor,,shop_manager',
			]
		);

		$this->assertSame(
			[ 'administrator', 'editor', 'shop_manager' ],
			$data['general']['exclude_user_roles']
		);
	}

	/**
	 * The customer-data toggle is the counterpart; the order-data toggle only
	 * stands in when the source predates it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_customer_data_prefers_its_own_toggle_over_the_order_data_fallback(): void {
		$both = $this->extract(
			[
				'gtm-code'                            => 'GTM-CUST01',
				'integrate-woocommerce-customer-data' => true,
				'integrate-woocommerce-order-data'    => false,
			]
		);

		$this->assertTrue( $both['integrations']['woocommerce_include_customer_data'] );

		$fallback = $this->extract(
			[
				'gtm-code'                         => 'GTM-CUST02',
				'integrate-woocommerce-order-data' => true,
			]
		);

		$this->assertTrue( $fallback['integrations']['woocommerce_include_customer_data'] );
	}

	/**
	 * The WooCommerce settings map onto their GTM Kit counterparts.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_woocommerce_settings_are_mapped(): void {
		$data = $this->extract(
			[
				'gtm-code'                                 => 'GTM-WOO001',
				'integrate-woocommerce-track-enhanced-ecommerce' => true,
				'integrate-woocommerce-brand-taxonomy'     => 'product_brand',
				'integrate-woocommerce-remarketing-usesku' => true,
				'integrate-woocommerce-business-vertical'  => 'retail',
				'integrate-woocommerce-remarketing-productidprefix' => 'prefix_',
				'integrate-woocommerce-exclude-tax'        => true,
				'integrate-woocommerce-exclude-shipping'   => true,
			]
		);

		$this->assertTrue( $data['integrations']['woocommerce_integration'] );
		$this->assertSame( 'product_brand', $data['integrations']['woocommerce_brand'] );
		$this->assertTrue( $data['integrations']['woocommerce_use_sku'] );
		$this->assertSame( 'retail', $data['integrations']['woocommerce_google_business_vertical'] );
		$this->assertSame( 'prefix_', $data['integrations']['woocommerce_product_id_prefix'] );
		$this->assertTrue( $data['integrations']['woocommerce_exclude_tax'] );
		$this->assertTrue( $data['integrations']['woocommerce_exclude_shipping'] );
	}

	/**
	 * Reading import data must not consume first-install state, or the settings
	 * screen and a second wizard run would find nothing to offer.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get_all
	 */
	public function test_reading_all_import_data_has_no_side_effects(): void {
		Functions\stubs( [ 'is_plugin_active' => false ] );
		Functions\when( 'get_option' )->alias(
			static fn( $name, $default_value = false ) => ( 'gtm4wp-options' === $name )
				? [ 'gtm-code' => 'GTM-SIDE01' ]
				: $default_value
		);

		Functions\expect( 'delete_transient' )->never();
		Functions\expect( 'set_transient' )->never();

		$data = ( new PluginDataImport() )->get_all();

		$this->assertTrue( $data['importAvailable'] );
		$this->assertSame( 'GTM-SIDE01', $data['import_data']['gtm4wp']['general']['gtm_id'] );
	}
	/**
	 * Container fields the source never stored are left out of the import.
	 *
	 * The confirmation step lists every key the import carries, so a key
	 * carried as an empty string reads as "this will be replaced" and then
	 * blanks a working server container domain or environment on the way
	 * through. Only what the source actually stored may travel.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_container_fields_the_source_never_stored_are_left_out(): void {
		$data = $this->extract( [ 'gtm-code' => 'GTM-FLAT01' ] );

		$this->assertSame( 'GTM-FLAT01', $data['general']['gtm_id'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
		$this->assertArrayNotHasKey( 'gtm_auth', $data['general'] );
		$this->assertArrayNotHasKey( 'gtm_preview', $data['general'] );
	}

	/**
	 * A source with no container at all never carries an empty container ID.
	 *
	 * A GTM4WP install that was activated and never configured still has an
	 * option row, so it is still offered as an import source. Importing from
	 * it must not blank the container ID the site is tracking with.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_source_without_a_container_never_blanks_the_container_id(): void {
		$data = $this->extract( [ 'gtm-datalayer-variable-name' => 'dataLayer' ] );

		$this->assertSame( 0, $data['container_count'] );
		$this->assertArrayNotHasKey( 'gtm_id', $data['general'] );
		$this->assertSame( 'dataLayer', $data['general']['datalayer_name'] );
	}

	/**
	 * A domain that GTM4WP pairs with a custom loader path is left out and
	 * reported, while the container and its environment still import.
	 *
	 * GTM Kit has no setting for the path, so the domain alone would build a
	 * loader address that does not serve the container.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_domain_with_a_custom_loader_path_is_skipped_and_reported(): void {
		$data = $this->extract(
			[
				'gtm-containers'              => [
					[
						'id'          => 'GTM-ROW001',
						'domain'      => 'sgtm.example.com',
						'gtm_auth'    => 'ROWAUTH',
						'gtm_preview' => 'env-9',
						'path'        => 'metrics',
						'no_id'       => '',
					],
				],
				'gtm-datalayer-variable-name' => 'dataLayer',
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
		$this->assertSame( 'GTM-ROW001', $data['general']['gtm_id'] );
		$this->assertSame( 'ROWAUTH', $data['general']['gtm_auth'] );
		$this->assertSame( 'env-9', $data['general']['gtm_preview'] );
		$this->assertSame( 'dataLayer', $data['general']['datalayer_name'] );
	}

	/**
	 * Omitting the container ID together with a custom path skips the domain
	 * just like the path alone.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_domain_with_a_custom_path_and_omitted_id_is_skipped_and_reported(): void {
		$data = $this->extract(
			[
				'gtm-containers' => [
					[
						'id'     => 'GTM-ROW001',
						'domain' => 'sgtm.example.com',
						'path'   => 'metrics',
						'no_id'  => '1',
					],
				],
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
		$this->assertSame( 'GTM-ROW001', $data['general']['gtm_id'] );
	}

	/**
	 * Omitting the container ID without a custom path has no effect on the
	 * source plugin's loader, so the domain still imports.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_an_omitted_id_without_a_custom_path_keeps_the_domain(): void {
		$data = $this->extract(
			[
				'gtm-containers' => [
					[
						'id'     => 'GTM-ROW001',
						'domain' => 'sgtm.example.com',
						'path'   => '',
						'no_id'  => '1',
					],
				],
			]
		);

		$this->assertFalse( $data['sgtm_domain_skipped'] );
		$this->assertSame( 'sgtm.example.com', $data['general']['sgtm_domain'] );
	}

	/**
	 * Without a custom path the domain imports as before and nothing is
	 * reported.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_domain_without_a_custom_path_is_imported(): void {
		$data = $this->extract(
			[
				'gtm-containers' => [
					[
						'id'     => 'GTM-ROW001',
						'domain' => 'sgtm.example.com',
					],
				],
			]
		);

		$this->assertFalse( $data['sgtm_domain_skipped'] );
		$this->assertSame( 'sgtm.example.com', $data['general']['sgtm_domain'] );
	}

	/**
	 * The flat options of older releases follow the same rule through their
	 * custom path key.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_flat_options_with_a_custom_path_skip_the_domain(): void {
		$data = $this->extract(
			[
				'gtm-code'            => 'GTM-FLAT01',
				'gtm-domain-name'     => 'sgtm.example.com',
				'gtm-custom-path'     => 'metrics',
				'gtm-env-gtm-auth'    => 'AUTHVALUE',
				'gtm-env-gtm-preview' => 'env-12',
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
		$this->assertSame( 'GTM-FLAT01', $data['general']['gtm_id'] );
		$this->assertSame( 'AUTHVALUE', $data['general']['gtm_auth'] );
	}

	/**
	 * A custom path without a domain has no domain to skip, so nothing is
	 * reported.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_custom_path_without_a_domain_reports_nothing(): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-custom-path' => 'metrics',
			]
		);

		$this->assertFalse( $data['sgtm_domain_skipped'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
	}

	/**
	 * GTM4WP's own `gtm.js` path, with or without a leading slash, is the
	 * standard loader path, so the domain imports.
	 *
	 * @dataProvider default_path_provider
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 *
	 * @param string $path The stored loader path.
	 */
	public function test_the_default_gtm_js_path_imports_the_domain( string $path ): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => 'sgtm.example.com',
				'gtm-custom-path' => $path,
			]
		);

		$this->assertFalse( $data['sgtm_domain_skipped'] );
		$this->assertFalse( $data['sgtm_domain_cleared'] );
		$this->assertSame( 'sgtm.example.com', $data['general']['sgtm_domain'] );
	}

	/**
	 * Paths GTM4WP treats as its standard loader.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function default_path_provider(): array {
		return [
			'gtm.js'         => [ 'gtm.js' ],
			'/gtm.js'        => [ '/gtm.js' ],
			'padded /gtm.js' => [ ' /gtm.js ' ],
		];
	}

	/**
	 * A path that only resembles the default still counts as custom.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_real_custom_path_still_skips_the_domain(): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => 'sgtm.example.com',
				'gtm-custom-path' => 'metrics/gtm.js',
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
	}

	/**
	 * A re-import clears the domain an earlier import stored without its
	 * custom path, and says so.
	 *
	 * @dataProvider earlier_import_provider
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 *
	 * @param string $source_domain The domain GTM4WP stores.
	 * @param string $stored_domain The domain the earlier import left in GTM Kit.
	 */
	public function test_a_reimport_clears_the_domain_an_earlier_import_stored( string $source_domain, string $stored_domain ): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => $source_domain,
				'gtm-custom-path' => 'metrics',
			],
			[ 'general' => [ 'sgtm_domain' => $stored_domain ] ]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertTrue( $data['sgtm_domain_cleared'] );
		$this->assertSame( '', $data['general']['sgtm_domain'] );
	}

	/**
	 * GTM4WP domains and what an earlier import stored from them, after the
	 * save normalised them.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function earlier_import_provider(): array {
		return [
			'a bare domain' => [ 'sgtm.example.com', 'sgtm.example.com' ],
			'a full URL'    => [ 'https://sgtm.example.com/', 'sgtm.example.com' ],
		];
	}

	/**
	 * A domain the site owner entered is never cleared by a re-import.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_reimport_leaves_an_owner_entered_domain_alone(): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => 'sgtm.example.com',
				'gtm-custom-path' => 'metrics',
			],
			[ 'general' => [ 'sgtm_domain' => 'tagging.example.com' ] ]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertFalse( $data['sgtm_domain_cleared'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
	}

	/**
	 * A domain the Stape-issued loader serves from is in use, so a re-import
	 * leaves it alone even when it matches what an earlier import wrote.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_reimport_leaves_a_domain_the_stape_loader_uses_alone(): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => 'sgtm.example.com',
				'gtm-custom-path' => 'metrics',
			],
			[
				'general' => [
					'sgtm_domain'              => 'sgtm.example.com',
					'sgtm_stape_issued_loader' => true,
				],
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertFalse( $data['sgtm_domain_cleared'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
	}

	/**
	 * A domain a custom loader serves from is in use, so a re-import leaves it
	 * alone even when it matches what an earlier import wrote. An import never
	 * stores a loader identifier, so one being set means the site owner
	 * configured the loader.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_a_reimport_leaves_a_domain_a_custom_loader_uses_alone(): void {
		$data = $this->extract(
			[
				'gtm-code'        => 'GTM-FLAT01',
				'gtm-domain-name' => 'collect.example.com',
				'gtm-custom-path' => 'abc123.js',
			],
			[
				'general' => [
					'sgtm_domain'               => 'collect.example.com',
					'sgtm_container_identifier' => 'abc123',
					'sgtm_stape_issued_loader'  => false,
				],
			]
		);

		$this->assertTrue( $data['sgtm_domain_skipped'] );
		$this->assertFalse( $data['sgtm_domain_cleared'] );
		$this->assertArrayNotHasKey( 'sgtm_domain', $data['general'] );
	}
}
