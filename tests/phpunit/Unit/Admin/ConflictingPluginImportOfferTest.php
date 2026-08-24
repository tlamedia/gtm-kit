<?php
/**
 * Unit tests for the import offer added to the conflicting-plugin notice.
 *
 * The offer must appear exactly when the detected plugin has settings GTM Kit
 * can actually read, so the notice never links to an import that would find
 * nothing.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use ReflectionMethod;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\PluginAvailability;
use TLA_Media\GTM_Kit\Admin\Suggestions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Import-offer tests for the conflicting-plugin notification.
 */
final class ConflictingPluginImportOfferTest extends TestCase {

	/**
	 * Option values the stubbed `get_option` resolves against.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Common setup.
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

		Functions\stubs(
			[
				'sanitize_key'      => static fn( $key ) => strtolower( (string) $key ),
				'admin_url'         => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'network_admin_url' => static fn( $path = '' ) => 'https://example.test/wp-admin/network/' . $path,
				'is_network_admin'  => false,
				'esc_url'           => null,
				'esc_html'          => null,
				'esc_html__'        => null,
				'__'                => null,
				'add_action'        => null,
				'add_filter'        => null,
				'get_option'        => null,
			]
		);

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);
	}

	/**
	 * Build the offer for one conflicting plugin against a stubbed database.
	 *
	 * @param string               $plugin_id The PluginAvailability plugin id.
	 * @param array<string, mixed> $stored Option values present on the site.
	 *
	 * @return string The offer sentence, or an empty string.
	 */
	private function offer_for( string $plugin_id, array $stored ): string {
		$this->stored = $stored;

		$options   = new Options();
		$util      = new Util( $options, new RestAPIServer() );
		$suggested = new Suggestions( new NotificationsHandler(), new PluginAvailability(), $options, $util, new SnippetScan( $options ) );

		$method = new ReflectionMethod( Suggestions::class, 'get_conflicting_plugin_import_offer' );

		// Private methods only became invokable without this in PHP 8.1, and the
		// plugin still supports 7.4. Calling it unconditionally would instead
		// raise a deprecation on PHP 8.5, so it is version-gated rather than
		// simply dropped.
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke(
			$suggested,
			[
				'id'   => $plugin_id,
				'name' => 'Some GTM Plugin',
			]
		);
	}

	/**
	 * Every conflicting plugin that has an importer offers the import once its
	 * settings are present.
	 *
	 * @dataProvider provide_importable_plugins
	 *
	 * @param string               $plugin_id The plugin id.
	 * @param array<string, mixed> $stored Options the plugin would have stored.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_conflicting_plugin_import_offer
	 */
	public function test_plugins_with_stored_settings_offer_an_import( string $plugin_id, array $stored ): void {
		$offer = $this->offer_for( $plugin_id, $stored );

		$this->assertStringContainsString( 'Import settings from Some GTM Plugin', $offer );
		$this->assertStringContainsString( 'page=gtmkit_general#/tools?focus=import', $offer );
	}

	/**
	 * Conflicting plugins that have an importer, with the options each one
	 * actually writes.
	 *
	 * @return array<string, array{0: string, 1: array<string, mixed>}>
	 */
	public static function provide_importable_plugins(): array {
		return [
			'GTM4WP'                  => [ 'gtm4wp', [ 'gtm4wp-options' => [ 'gtm-code' => 'GTM-ABC123' ] ] ],
			'GTM for WooCommerce'     => [
				'gtm-ecommerce-woo',
				[ 'gtm_ecommerce_woo_gtm_snippet_head' => "<script>(function(){'GTM-ABC123'})();</script>" ],
			],
			'GTM for WooCommerce Pro' => [
				'gtm-ecommerce-woo-pro',
				[ 'gtm_ecommerce_woo_gtm_snippet_head' => "<script>(function(){'GTM-ABC123'})();</script>" ],
			],
			'WEBKINDER'               => [
				'wk-google-analytics',
				[
					'ga_tag_manager_id'  => 'GTM-ABC123',
					'ga_use_tag_manager' => '1',
				],
			],
			'Google Tag Manager'      => [ 'google-tag-manager', [ 'google_tag_manager_id' => 'GTM-ABC123' ] ],
		];
	}

	/**
	 * A conflicting plugin GTM Kit cannot import from gets the plain notice.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_conflicting_plugin_import_offer
	 */
	public function test_plugin_without_an_importer_offers_nothing(): void {
		$this->assertSame(
			'',
			$this->offer_for( 'really-simple-google-tag-manager', [ 'gtm4wp-options' => [ 'gtm-code' => 'GTM-ABC123' ] ] )
		);
	}

	/**
	 * An importer that finds nothing must not produce a link to an empty
	 * import.
	 *
	 * @dataProvider provide_plugins_without_stored_settings
	 *
	 * @param string $plugin_id The plugin id.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_conflicting_plugin_import_offer
	 */
	public function test_plugin_with_no_stored_settings_offers_nothing( string $plugin_id ): void {
		$this->assertSame( '', $this->offer_for( $plugin_id, [] ) );
	}

	/**
	 * Every conflicting plugin that has an importer.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_plugins_without_stored_settings(): array {
		return [
			'GTM4WP'                  => [ 'gtm4wp' ],
			'GTM for WooCommerce'     => [ 'gtm-ecommerce-woo' ],
			'GTM for WooCommerce Pro' => [ 'gtm-ecommerce-woo-pro' ],
			'WEBKINDER'               => [ 'wk-google-analytics' ],
			'Google Tag Manager'      => [ 'google-tag-manager' ],
		];
	}

	/**
	 * The WEBKINDER plugin stores a container id and a separate switch; the
	 * switch being off means it is not tagging, so there is nothing to import.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\Suggestions::get_conflicting_plugin_import_offer
	 */
	public function test_disabled_tag_manager_switch_offers_nothing(): void {
		$this->assertSame(
			'',
			$this->offer_for(
				'wk-google-analytics',
				[
					'ga_tag_manager_id'  => 'GTM-ABC123',
					'ga_use_tag_manager' => '',
				]
			)
		);
	}
}
