<?php
/**
 * Per-module geography test for src/Installation/.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Installation\PluginDataImport::get()}
 * on the negative path — an unknown plugin slug yields an empty array.
 * Stubs `sanitize_key` so the method's inner `preg_replace` has a
 * deterministic input without booting WordPress.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Installation;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use TLA_Media\GTM_Kit\Options\OptionValidator;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Geography unit test for src/Installation/ via PluginDataImport::get().
 */
final class PluginDataImportTest extends TestCase {

	/**
	 * Unknown plugin slugs round-trip to an empty array.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_get_returns_empty_array_for_unknown_plugin_slug(): void {
		Functions\stubs(
			[
				'sanitize_key' => static fn( $key ) => strtolower( (string) $key ),
			]
		);

		$import = new PluginDataImport();

		$this->assertSame( [], $import->get( 'plugin_that_does_not_exist' ) );
	}
	/**
	 * Imported values have to satisfy the schema they are written into.
	 *
	 * Every import is saved as one payload and the whole payload is rejected
	 * if a single value fails validation, so a toggle carried as a word
	 * rather than a boolean silently discards the container ID the import
	 * existed to copy.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\PluginDataImport::get
	 */
	public function test_gtm_for_woocommerce_imports_a_toggle_the_schema_accepts(): void {
		Functions\stubs(
			[
				'sanitize_key'     => static fn( $key ) => strtolower( (string) $key ),
				// Defining it here keeps OptionSchema from reaching for the
				// WordPress plugin API, and puts the schema on its
				// WooCommerce-active branch, where the toggle is a boolean.
				'is_plugin_active' => true,
			]
		);

		Functions\when( 'get_option' )->alias(
			static fn( $name, $default_value = false ) => ( 'gtm_ecommerce_woo_gtm_snippet_head' === $name )
				? "<script>(function(w,d,s,l,i){})(window,document,'script','dataLayer','GTM-WOO123');</script>"
				: $default_value
		);

		$data = ( new PluginDataImport() )->get( 'gtm_for_woocommerce' );

		$this->assertSame( 'GTM-WOO123', $data['general']['gtm_id'] );

		$toggle = $data['integrations']['woocommerce_integration'];

		$this->assertIsBool( $toggle );
		$this->assertTrue(
			( new OptionValidator() )->validate( 'integrations', 'woocommerce_integration', $toggle )->is_valid(),
			'The imported WooCommerce toggle must pass the option schema, or the whole import is discarded.'
		);
	}
}
