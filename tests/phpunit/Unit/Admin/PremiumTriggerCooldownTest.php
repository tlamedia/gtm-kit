<?php
/**
 * Unit tests for the dismissal record behind the contextual upgrade notices.
 *
 * These notices are only created while a site is in the situation they
 * describe, so a dismissal has to be remembered somewhere that survives the
 * notice not existing. The record therefore lives in an option of its own,
 * and what it has to guarantee is narrow: a dismissal holds for the whole
 * cooldown, and stops holding the moment the cooldown has run out.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Covers the dismissal record and its cooldown window.
 */
final class PremiumTriggerCooldownTest extends TestCase {

	/**
	 * A notice id to record dismissals against.
	 *
	 * @var string
	 */
	private const NOTICE = 'gtmkit-upgrade-woo-ecommerce';

	/**
	 * Option values present on the site under test.
	 *
	 * @var array<string, mixed>
	 */
	private array $stored = [];

	/**
	 * Stub the option read and write the record depends on.
	 *
	 * `update_option` writes back into the same array the read resolves
	 * against, so a recorded dismissal is visible to the next read exactly as
	 * it would be after a page load.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			// A WordPress core constant the cooldown reads, not a plugin one.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standing in for core in a bare PHPUnit process.
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$this->stored = [];

		Functions\when( 'get_option' )->alias(
			fn( $name, $default_value = false ) => $this->stored[ $name ] ?? $default_value
		);

		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->stored[ $name ] = $value;

				return true;
			}
		);
	}

	/**
	 * Store a dismissal that happened a given number of days ago.
	 *
	 * @param int $days How long ago the notice was dismissed.
	 *
	 * @return void
	 */
	private function dismissed_days_ago( int $days ): void {
		$this->stored[ PremiumTriggerCooldown::OPTION ] = [
			self::NOTICE => time() - ( $days * DAY_IN_SECONDS ),
		];
	}

	/**
	 * A notice nobody has dismissed is not held back.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::is_within_cooldown
	 */
	public function test_a_notice_that_was_never_dismissed_is_not_in_cooldown(): void {
		$this->assertFalse( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}

	/**
	 * Dismissing writes a record that the next request reads back.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::record
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::is_within_cooldown
	 */
	public function test_a_dismissal_persists_into_the_next_request(): void {
		PremiumTriggerCooldown::record( self::NOTICE );

		$this->assertArrayHasKey(
			PremiumTriggerCooldown::OPTION,
			$this->stored,
			'The dismissal has to be written to its own option, not to the notifications record.'
		);
		$this->assertTrue( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}

	/**
	 * A dismissal holds for the whole cooldown.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::is_within_cooldown
	 */
	public function test_a_dismissal_holds_until_the_cooldown_has_run_out(): void {
		$this->dismissed_days_ago( PremiumTriggerCooldown::COOLDOWN_DAYS - 1 );

		$this->assertTrue( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}

	/**
	 * Once the cooldown has run out the notice is free to return.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::is_within_cooldown
	 */
	public function test_a_dismissal_stops_holding_once_the_cooldown_expires(): void {
		$this->dismissed_days_ago( PremiumTriggerCooldown::COOLDOWN_DAYS + 1 );

		$this->assertFalse( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}

	/**
	 * One notice's dismissal says nothing about another's.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::is_within_cooldown
	 */
	public function test_a_dismissal_is_recorded_per_notice(): void {
		PremiumTriggerCooldown::record( self::NOTICE );

		$this->assertFalse( PremiumTriggerCooldown::is_within_cooldown( 'gtmkit-upgrade-server-side' ) );
	}

	/**
	 * An option holding something other than timestamps reads as no dismissal.
	 *
	 * @dataProvider provide_unusable_option_values
	 *
	 * @param mixed $value What the option holds.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::get_dismissals
	 */
	public function test_an_unusable_option_reads_as_not_dismissed( $value ): void {
		$this->stored[ PremiumTriggerCooldown::OPTION ] = $value;

		$this->assertFalse( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}

	/**
	 * Option values that carry no usable dismissal.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function provide_unusable_option_values(): array {
		return [
			'a scalar'            => [ 'not an array' ],
			'an unrelated notice' => [ [ 'gtmkit-something-else' => 1750000000 ] ],
			'a non-numeric stamp' => [ [ self::NOTICE => 'yesterday' ] ],
		];
	}

	/**
	 * A timestamp stored as a numeric string still counts.
	 *
	 * Options survive a round trip through serialisation, and a value written
	 * by anything other than this class can arrive as a string, so the record
	 * must not depend on the stored type.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown::get_dismissals
	 */
	public function test_a_timestamp_stored_as_a_string_still_counts(): void {
		$this->stored[ PremiumTriggerCooldown::OPTION ] = [ self::NOTICE => (string) time() ];

		$this->assertTrue( PremiumTriggerCooldown::is_within_cooldown( self::NOTICE ) );
	}
}
