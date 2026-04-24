<?php
/**
 * Per-module geography test for src/Integration/.
 *
 * Exercises the constructor of {@see \TLA_Media\GTM_Kit\Integration\AbstractIntegration}
 * via an anonymous concrete subclass. Verifies that the Options and Util
 * dependencies are assigned to the protected properties so integrations
 * that extend this base get DI for free.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Integration;

use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\AbstractIntegration;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

final class AbstractIntegrationTest extends TestCase {

	/**
	 * @covers \TLA_Media\GTM_Kit\Integration\AbstractIntegration::__construct
	 */
	public function test_constructor_assigns_injected_dependencies(): void {
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

		$options = Options::create();
		$util    = new Util( $options, new RestAPIServer() );

		$integration = new class( $options, $util ) extends AbstractIntegration {
			public static function instance(): self {
				throw new \RuntimeException( 'instance() not exercised in this starter test.' );
			}

			public static function register( Options $options, Util $util ): void {
				// No-op: registration is a downstream concern.
			}

			public function get_injected_options(): Options {
				return $this->options;
			}

			public function get_injected_util(): Util {
				return $this->util;
			}
		};

		$this->assertSame( $options, $integration->get_injected_options() );
		$this->assertSame( $util, $integration->get_injected_util() );
	}
}
