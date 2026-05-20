<?php
/**
 * Unit tests for the `excluded_url_patterns` schema entry.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Options;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Options\OptionKeys;
use TLA_Media\GTM_Kit\Options\OptionSchema;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Schema-registration tests for `general.excluded_url_patterns`.
 */
final class OptionSchemaExcludedUrlPatternsTest extends TestCase {

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
				'add_filter'       => null,
				'is_plugin_active' => false,
				'wp_parse_url'     => static function ( $url, $component = -1 ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- BrainMonkey stub stands in for wp_parse_url() with no WP available; PHP's native parse_url() is the only option here.
					$parts = parse_url( $url );
					if ( ! is_array( $parts ) ) {
						return false;
					}
					if ( $component === PHP_URL_PATH ) {
						return $parts['path'] ?? null;
					}
					return $parts;
				},
			]
		);
	}

	/**
	 * The schema entry exists with an empty array default.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::get_option_schema
	 */
	public function test_schema_registers_default_empty_array(): void {
		$schema = OptionSchema::get_option_schema( 'general', 'excluded_url_patterns' );

		$this->assertIsArray( $schema );
		$this->assertSame( [], $schema['default'] );
		$this->assertSame( 'array', $schema['type'] );
		$this->assertSame(
			[ OptionSchema::class, 'sanitize_excluded_url_patterns' ],
			$schema['sanitize']
		);
		$this->assertSame(
			[ OptionSchema::class, 'validate_excluded_url_patterns' ],
			$schema['validate']
		);
	}

	/**
	 * `OptionKeys::GENERAL_EXCLUDED_URL_PATTERNS` is registered.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::exists
	 * @covers \TLA_Media\GTM_Kit\Options\OptionKeys::parse
	 */
	public function test_option_key_constant_is_registered(): void {
		$this->assertTrue( OptionKeys::exists( OptionKeys::GENERAL_EXCLUDED_URL_PATTERNS ) );
		$this->assertSame(
			[
				'group' => 'general',
				'key'   => 'excluded_url_patterns',
			],
			OptionKeys::parse( OptionKeys::GENERAL_EXCLUDED_URL_PATTERNS )
		);
	}

	/**
	 * Fresh install: a missing option resolves to the empty array default.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\Options::get
	 */
	public function test_fresh_install_returns_empty_array(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		$options = Options::create();

		$this->assertSame( [], $options->get( 'general', 'excluded_url_patterns' ) );
	}

	/**
	 * The sanitiser drops empty patterns, trims whitespace, and coerces the mode.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::sanitize_excluded_url_patterns
	 */
	public function test_sanitiser_drops_empty_trims_and_coerces_mode(): void {
		$result = OptionSchema::sanitize_excluded_url_patterns(
			[
				[
					'pattern' => '  /checkout  ',
					'mode'    => 'glob',
				],
				[
					'pattern' => '',
					'mode'    => 'regex',
				],
				[
					'pattern' => '/api/*',
					'mode'    => 'something-else',
				],
				[
					'pattern' => '^/admin',
					'mode'    => 'regex',
				],
				'not-an-entry',
			]
		);

		$this->assertSame(
			[
				[
					'pattern' => '/checkout',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/api/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => '^/admin',
					'mode'    => 'regex',
				],
			],
			$result
		);
	}

	/**
	 * A glob pattern pasted as a full URL is reduced to its path; regex
	 * patterns are left alone because `://` is legitimate regex syntax.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::sanitize_excluded_url_patterns
	 */
	public function test_sanitiser_reduces_full_url_glob_to_path(): void {
		$result = OptionSchema::sanitize_excluded_url_patterns(
			[
				[
					'pattern' => 'https://gtmkitdev.test/custom-page-type/',
					'mode'    => 'glob',
				],
				[
					'pattern' => 'http://example.test/foo/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => '//example.test/scheme-relative/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => 'https://example.test',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/already/a/path/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => 'https?://example\\.test/api',
					'mode'    => 'regex',
				],
			]
		);

		$this->assertSame(
			[
				[
					'pattern' => '/custom-page-type/',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/foo/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/scheme-relative/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/',
					'mode'    => 'glob',
				],
				[
					'pattern' => '/already/a/path/*',
					'mode'    => 'glob',
				],
				[
					'pattern' => 'https?://example\\.test/api',
					'mode'    => 'regex',
				],
			],
			$result
		);
	}

	/**
	 * The sanitiser caps the list length and individual pattern length.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::sanitize_excluded_url_patterns
	 */
	public function test_sanitiser_caps_list_and_pattern_length(): void {
		$long   = '/' . str_repeat( 'a', OptionSchema::URL_EXCLUSION_MAX_PATTERN_LENGTH + 50 );
		$result = OptionSchema::sanitize_excluded_url_patterns(
			[
				[
					'pattern' => $long,
					'mode'    => 'glob',
				],
			]
		);
		$this->assertSame(
			OptionSchema::URL_EXCLUSION_MAX_PATTERN_LENGTH,
			strlen( $result[0]['pattern'] )
		);

		$rows = [];
		for ( $i = 0; $i < OptionSchema::URL_EXCLUSION_MAX_PATTERNS + 10; $i++ ) {
			$rows[] = [
				'pattern' => '/p' . $i,
				'mode'    => 'glob',
			];
		}
		$capped = OptionSchema::sanitize_excluded_url_patterns( $rows );
		$this->assertCount( OptionSchema::URL_EXCLUSION_MAX_PATTERNS, $capped );
	}

	/**
	 * The validator rejects entries with an uncompilable regex pattern.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::validate_excluded_url_patterns
	 */
	public function test_validator_rejects_invalid_regex_entries(): void {
		$this->assertFalse(
			OptionSchema::validate_excluded_url_patterns(
				[
					[
						'pattern' => '(unterminated',
						'mode'    => 'regex',
					],
				]
			)
		);
	}

	/**
	 * The validator accepts a valid mix of glob and regex entries.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::validate_excluded_url_patterns
	 */
	public function test_validator_accepts_valid_entries(): void {
		$this->assertTrue(
			OptionSchema::validate_excluded_url_patterns(
				[
					[
						'pattern' => '/checkout/*',
						'mode'    => 'glob',
					],
					[
						'pattern' => '^/api/v\\d+',
						'mode'    => 'regex',
					],
				]
			)
		);
	}

	/**
	 * Non-array values fail validation outright.
	 *
	 * @covers \TLA_Media\GTM_Kit\Options\OptionSchema::validate_excluded_url_patterns
	 */
	public function test_validator_rejects_non_array_value(): void {
		$this->assertFalse( OptionSchema::validate_excluded_url_patterns( 'string' ) );
	}
}
