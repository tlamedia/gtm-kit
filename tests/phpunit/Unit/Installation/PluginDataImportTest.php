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
}
