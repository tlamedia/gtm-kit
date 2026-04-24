<?php
/**
 * Per-module geography test for src/Common/ (additional, beyond UtilTest.php).
 *
 * Exercises {@see \TLA_Media\GTM_Kit\Common\Util::anonymize_options()} —
 * pure array manipulation. Demonstrates testing a Util method that
 * needs the full Util instance but no WP functions beyond the stubs
 * already required by the Options constructor.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Common;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see Util::anonymize_options()}.
 */
final class UtilAnonymizeTest extends TestCase {

	/**
	 * System under test.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
	 * Wire up real Options + RestAPIServer with stubbed WP functions.
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
				'get_option' => [],
				'add_filter' => null,
			]
		);

		$this->util = new Util( Options::create(), new RestAPIServer() );
	}

	/**
	 * Drops gtm_id and masks identifying string values.
	 *
	 * @covers \TLA_Media\GTM_Kit\Common\Util::anonymize_options
	 */
	public function test_anonymize_options_strips_gtm_id_and_replaces_identifying_values(): void {
		$input = [
			'general' => [
				'gtm_id'                    => 'GTM-ABC123',
				'datalayer_name'            => 'customDataLayer',
				'sgtm_domain'               => 'gtm.example.com',
				'sgtm_container_identifier' => 'prod-loader',
				'container_active'          => 1,
			],
		];

		$anonymized = $this->util->anonymize_options( $input );

		$this->assertArrayNotHasKey( 'gtm_id', $anonymized['general'] );
		$this->assertSame( 'datalayer_name', $anonymized['general']['datalayer_name'] );
		$this->assertSame( 'sgtm_domain', $anonymized['general']['sgtm_domain'] );
		$this->assertSame( 'sgtm_container_identifier', $anonymized['general']['sgtm_container_identifier'] );
		$this->assertSame( 1, $anonymized['general']['container_active'], 'Non-identifying fields pass through unchanged.' );
	}
}
