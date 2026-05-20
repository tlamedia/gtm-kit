<?php
/**
 * Unit tests for the URL exclusion matcher.
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded()}
 * and its supporting helpers across glob, regex, and edge-case inputs.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use Brain\Monkey\Filters;
use TLA_Media\GTM_Kit\Frontend\UrlExclusion;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see UrlExclusion::is_excluded()}.
 */
final class UrlExclusionTest extends TestCase {

	/**
	 * An empty pattern list short-circuits to false without filters firing.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_empty_pattern_list_short_circuits_to_false(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )
			->once()
			->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )
			->once()
			->andReturnFirstArg();

		$this->assertFalse( UrlExclusion::is_excluded( '/checkout', [] ) );
	}

	/**
	 * A glob pattern matches the equivalent path.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_glob_anchored_at_start_matches_descendant_paths(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/checkout-embed/payment',
				[
					[
						'pattern' => '/checkout-embed/*',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * Glob `*` matches the empty suffix when the user writes `/path*`.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_glob_with_open_star_matches_exact_path(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/checkout',
				[
					[
						'pattern' => '/checkout*',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * `/checkout/*` should not match the bare `/checkout` path.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_glob_requiring_trailing_segment_does_not_match_bare_path(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/checkout',
				[
					[
						'pattern' => '/checkout/*',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * Glob `?` matches exactly one character.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_glob_question_mark_matches_single_char(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/abc',
				[
					[
						'pattern' => '/ab?',
						'mode'    => 'glob',
					],
				]
			)
		);
		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/abcd',
				[
					[
						'pattern' => '/ab?',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * Glob regex metacharacters are escaped.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_glob_metacharacters_are_escaped(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/aXc',
				[
					[
						'pattern' => '/a.c',
						'mode'    => 'glob',
					],
				]
			)
		);
		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/a.c',
				[
					[
						'pattern' => '/a.c',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * The trailing slash on the pattern is normalised away.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_pattern_trailing_slash_is_normalised(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/checkout',
				[
					[
						'pattern' => '/checkout/',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * The trailing slash on the path is normalised away.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_path_trailing_slash_is_normalised(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/checkout/',
				[
					[
						'pattern' => '/checkout',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * Matching is case-insensitive.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_match_is_case_insensitive(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/CHECKOUT',
				[
					[
						'pattern' => '/checkout',
						'mode'    => 'glob',
					],
				]
			)
		);
		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/checkout',
				[
					[
						'pattern' => '/CHECKOUT',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * Subdirectory installs match the full request path including the prefix.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_subdirectory_path_matches_when_pattern_includes_prefix(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/shop/checkout',
				[
					[
						'pattern' => '/shop/checkout',
						'mode'    => 'glob',
					],
				]
			)
		);
		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/shop/checkout',
				[
					[
						'pattern' => '/checkout',
						'mode'    => 'glob',
					],
				]
			)
		);
	}

	/**
	 * A raw regex pattern matches with the `i` flag and a `~` delimiter.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_regex_pattern_runs_case_insensitively(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue(
			UrlExclusion::is_excluded(
				'/api/v2/users/42',
				[
					[
						'pattern' => '^/api/v\d+/users/\d+$',
						'mode'    => 'regex',
					],
				]
			)
		);
	}

	/**
	 * An invalid regex pattern fails open (no match) rather than blanking out the site.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_invalid_regex_fails_open(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/anything',
				[
					[
						'pattern' => '(unclosed',
						'mode'    => 'regex',
					],
				]
			)
		);
	}

	/**
	 * The percent-encoded path is decoded exactly once before matching.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::normalize_path
	 */
	public function test_normalize_path_decodes_once(): void {
		$this->assertSame( '/foo bar', UrlExclusion::normalize_path( '/Foo%20Bar' ) );
	}

	/**
	 * `gtmkit_excluded_url_patterns` can append patterns at runtime.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_excluded_url_patterns_filter_can_append_patterns(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )
			->once()
			->andReturnUsing(
				static function ( array $patterns ): array {
					$patterns[] = [
						'pattern' => '/runtime',
						'mode'    => 'glob',
					];
					return $patterns;
				}
			);
		Filters\expectApplied( 'gtmkit_is_url_excluded' )->andReturnFirstArg();

		$this->assertTrue( UrlExclusion::is_excluded( '/runtime', [] ) );
	}

	/**
	 * `gtmkit_is_url_excluded` overrides the computed result.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_is_url_excluded_filter_can_force_true(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )
			->once()
			->andReturn( true );

		$this->assertTrue( UrlExclusion::is_excluded( '/no-stored-pattern', [] ) );
	}

	/**
	 * `gtmkit_is_url_excluded` can flip a true result to false.
	 *
	 * @covers \TLA_Media\GTM_Kit\Frontend\UrlExclusion::is_excluded
	 */
	public function test_is_url_excluded_filter_can_force_false(): void {
		Filters\expectApplied( 'gtmkit_excluded_url_patterns' )->andReturnFirstArg();
		Filters\expectApplied( 'gtmkit_is_url_excluded' )
			->once()
			->andReturn( false );

		$this->assertFalse(
			UrlExclusion::is_excluded(
				'/checkout',
				[
					[
						'pattern' => '/checkout',
						'mode'    => 'glob',
					],
				]
			)
		);
	}
}
