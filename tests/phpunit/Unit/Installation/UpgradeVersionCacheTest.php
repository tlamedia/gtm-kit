<?php
/**
 * Unit tests for how the upgrade routine records the installed version.
 *
 * The version check runs on every admin request and reads `gtmkit_version`
 * through the object cache. If a persistent cache still holds an older
 * version than the database, `update_option()` sees a change, updates no
 * rows, returns false and never refreshes the cache, so every later request
 * would upgrade again until the cache is flushed.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Installation\Upgrade}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Installation;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Version cache tests for the upgrade routine.
 *
 * Other suites define `GTMKIT_VERSION` with their own value, so each test
 * runs in its own process where the current and stale versions are known.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class UpgradeVersionCacheTest extends TestCase {

	/**
	 * Option values as stored in the database.
	 *
	 * @var array<string, mixed>
	 */
	private array $db = [];

	/**
	 * Option values held in the object cache.
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	/**
	 * Option and cache writes recorded during a test, in order.
	 *
	 * @var array<int, string>
	 */
	private array $writes = [];

	/**
	 * Common setup.
	 *
	 * Models the parts of the options API that matter here: reads go through
	 * the cache, and `update_option()` compares against the cached value but
	 * only refreshes the cache when the database row actually changed.
	 *
	 * @inheritDoc
	 */
	protected function set_up(): void {
		parent::set_up();

		if ( ! defined( 'GTMKIT_VERSION' ) ) {
			define( 'GTMKIT_VERSION', '2.19.0' );
		}

		$this->db     = [];
		$this->cache  = [];
		$this->writes = [];

		Functions\stubs( [ 'is_plugin_active' => false ] );

		Functions\when( 'get_option' )->alias(
			function ( $key, $fallback = false ) {
				if ( ! array_key_exists( $key, $this->cache ) && array_key_exists( $key, $this->db ) ) {
					$this->cache[ $key ] = $this->db[ $key ];
				}
				return $this->cache[ $key ] ?? $fallback;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) {
				$this->writes[] = 'update_option:' . $key;

				if ( get_option( $key ) === $value ) {
					return false;
				}

				if ( ( $this->db[ $key ] ?? null ) === $value ) {
					// The row already holds the value: nothing changed, so
					// WordPress returns early without touching the cache.
					return false;
				}

				$this->db[ $key ]    = $value;
				$this->cache[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'wp_cache_delete' )->alias(
			function ( $key, $group = '' ) {
				$this->writes[] = 'wp_cache_delete:' . $group . ':' . $key;
				unset( $this->cache[ $key ] );
				return true;
			}
		);
	}

	/**
	 * Simulate one admin request's version check.
	 *
	 * @return bool Whether the request ran the upgrade.
	 */
	private function admin_request(): bool {
		if ( version_compare( get_option( 'gtmkit_version' ), GTMKIT_VERSION, '<' ) ) {
			new Upgrade( new Options() );
			return true;
		}

		return false;
	}

	/**
	 * The cached version is cleared after the new version is written.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::__construct
	 */
	public function test_the_version_cache_entry_is_deleted_after_the_write(): void {
		$this->db['gtmkit_version'] = GTMKIT_VERSION;

		new Upgrade( new Options() );

		$write  = array_search( 'update_option:gtmkit_version', $this->writes, true );
		$delete = array_search( 'wp_cache_delete:options:gtmkit_version', $this->writes, true );

		$this->assertNotFalse( $write, 'The new version has to be written.' );
		$this->assertNotFalse( $delete, 'The cached version has to be cleared.' );
		$this->assertGreaterThan( $write, $delete, 'The cache has to be cleared after the write, or it can be repopulated with the old value.' );
	}

	/**
	 * The cache is cleared even when `update_option()` reports no change.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::__construct
	 */
	public function test_the_version_cache_entry_is_deleted_when_the_write_changes_nothing(): void {
		$this->db['gtmkit_version']    = GTMKIT_VERSION;
		$this->cache['gtmkit_version'] = '2.18.0';

		$this->assertFalse( update_option( 'gtmkit_version', GTMKIT_VERSION, false ), 'Precondition: a stale cache makes the write report false.' );
		$this->writes = [];

		new Upgrade( new Options() );

		$this->assertContains( 'wp_cache_delete:options:gtmkit_version', $this->writes );
		$this->assertArrayNotHasKey( 'gtmkit_version', $this->cache );
	}

	/**
	 * A stale cached version triggers one upgrade, not one per request.
	 *
	 * @covers \TLA_Media\GTM_Kit\Installation\Upgrade::__construct
	 */
	public function test_a_stale_cached_version_upgrades_only_once(): void {
		$this->db['gtmkit_version']    = GTMKIT_VERSION;
		$this->cache['gtmkit_version'] = '2.18.0';

		$this->assertTrue( $this->admin_request(), 'The first request sees the stale version and upgrades.' );
		$this->assertFalse( $this->admin_request(), 'The next request must read the current version.' );
		$this->assertSame( GTMKIT_VERSION, get_option( 'gtmkit_version' ) );
	}
}
