<?php
/**
 * Unit test template for pure utility methods in the gtm-kit plugin.
 *
 * Pattern: BrainMonkey (via `yoast/wp-test-utils`). No WordPress boot,
 * no database. WordPress functions that the system-under-test or its
 * dependencies call are stubbed with `Brain\Monkey\Functions\stubs()`.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Common\Util::shorten_version()} at
 * src/Common/Util.php. This is the canonical unit-test template — future
 * unit tests should copy its set_up/tear_down shape and the WP-function
 * stubbing approach below.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

final class UtilTest extends TestCase {

	/**
	 * System under test.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
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

		// Stub WP functions touched by the Options constructor so we can instantiate
		// real Options (it is a `final` class, so Mockery cannot proxy it).
		Functions\stubs(
			[
				'get_option' => [],
				'add_filter' => null,
			]
		);

		$this->util = new Util( Options::create(), new RestAPIServer() );
	}

	/**
	 * @covers \TLA_Media\GTM_Kit\Common\Util::shorten_version
	 */
	public function test_shorten_version_keeps_major_minor(): void {
		$this->assertSame( '6.9', $this->util->shorten_version( '6.9.3' ) );
	}

	/**
	 * @covers \TLA_Media\GTM_Kit\Common\Util::shorten_version
	 */
	public function test_shorten_version_handles_prerelease_suffix(): void {
		$this->assertSame( '8.5', $this->util->shorten_version( '8.5.4-rc1' ) );
	}

	/**
	 * @covers \TLA_Media\GTM_Kit\Common\Util::shorten_version
	 */
	public function test_shorten_version_leaves_two_part_versions_unchanged(): void {
		$this->assertSame( '7.4', $this->util->shorten_version( '7.4' ) );
	}
}
