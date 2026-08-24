<?php
/**
 * Unit tests for the `noscript_implementation` schema entry.
 *
 * The settings screen offers four placements and Site Health labels all four,
 * so the schema has to accept all four. These tests pin the declared range to
 * what the interface actually offers, and pin the save round-trip so the
 * fourth placement keeps persisting.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Options;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Options\OptionSchema;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Schema and round-trip tests for `general.noscript_implementation`.
 */
final class OptionSchemaNoscriptImplementationTest extends TestCase {

	/**
	 * The `gtmkit` option as stored on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * What was last written back to the `gtmkit` option.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $written = null;

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

		$this->stored  = [];
		$this->written = null;

		Functions\stubs(
			[
				'add_filter'          => null,
				'is_plugin_active'    => false,
				'is_multisite'        => false,
				'do_action'           => null,
				'apply_filters'       => static fn( $tag, $value = null ) => $value,
				'wp_cache_delete'     => null,
				'wp_cache_get'        => false,
				'wp_cache_set'        => null,
				'sanitize_text_field' => null,
			]
		);

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => ( Options::OPTION_NAME === $name ) ? $this->stored : $default_value
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				if ( Options::OPTION_NAME === $name ) {
					$this->written = $value;
				}
				return true;
			}
		);
	}

	/**
	 * The declared range covers every placement the interface offers.
	 *
	 * Read through the schema rather than asserting the literal bounds, so the
	 * test states the rule (the fourth placement is valid, a fifth is not)
	 * rather than restating the numbers.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::get_option_schema
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::validate_in_range
	 */
	public function test_the_declared_range_accepts_every_offered_placement(): void {
		$schema = OptionSchema::get_option_schema( 'general', 'noscript_implementation' );

		$this->assertIsArray( $schema );
		$this->assertSame( 'integer', $schema['type'] );

		[ , $method, $min, $max ] = $schema['validate'];

		$this->assertSame( 'validate_in_range', $method );

		// 0 opening body tag, 1 footer, 2 manual template call, 3 switched off.
		foreach ( [ 0, 1, 2, 3 ] as $placement ) {
			$this->assertTrue(
				OptionSchema::validate_in_range( $placement, $min, $max ),
				"Placement {$placement} is offered by the settings screen and must validate."
			);
		}

		$this->assertFalse(
			OptionSchema::validate_in_range( 4, $min, $max ),
			'A value the settings screen never offers must not validate.'
		);
	}

	/**
	 * Switching the fallback off persists.
	 *
	 * The 2.18.0 release notes tell users the fallback can be switched off, so
	 * placement 3 has to survive a save. This currently passes because the
	 * custom-validator guard in OptionValidator never invokes the range check;
	 * it has to keep passing once that guard is repaired, which is the whole
	 * point of widening the declared range alongside it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\Options::set
	 */
	public function test_switching_the_fallback_off_survives_a_save(): void {
		$this->stored = [ 'general' => [ 'noscript_implementation' => 0 ] ];

		$options = Options::create();
		$options->set( [ 'general' => [ 'noscript_implementation' => 3 ] ] );

		$this->assertNotNull( $this->written, 'The save was rejected and nothing was written.' );
		$this->assertSame( 3, $this->written['general']['noscript_implementation'] );
	}
}
