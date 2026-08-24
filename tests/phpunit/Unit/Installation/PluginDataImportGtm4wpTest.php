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
			]
		);
	}

	/**
	 * Extract the GTM4WP settings from a stubbed option row.
	 *
	 * @param mixed $stored The value `gtm4wp-options` resolves to.
	 *
	 * @return array<string, mixed>
	 */
	private function extract( $stored ): array {
		Functions\when( 'get_option' )->alias(
			static fn( $name, $default_value = false ) => ( 'gtm4wp-options' === $name ) ? $stored : $default_value
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
						'path'        => 'custom.js',
						'no_id'       => '1',
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
}
