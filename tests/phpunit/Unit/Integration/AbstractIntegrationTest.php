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

/**
 * Geography unit test for src/Integration/ via AbstractIntegration DI.
 */
final class AbstractIntegrationTest extends TestCase {

	/**
	 * Injected Options + Util end up on the protected properties.
	 *
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
			// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn -- stub for an abstract contract we intentionally do not exercise.
			/**
			 * Required by AbstractIntegration; not exercised here.
			 *
			 * @return self
			 * @throws \RuntimeException Always — this starter test does not cover instance().
			 */
			public static function instance(): self {
				throw new \RuntimeException( 'instance() not exercised in this starter test.' );
			}
			// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn

			/**
			 * Required by AbstractIntegration; no-op for this starter test.
			 *
			 * @param Options $options An instance of Options.
			 * @param Util    $util    An instance of Util.
			 */
			public static function register( Options $options, Util $util ): void {
				// No-op: registration is a downstream concern.
			}

			/**
			 * Expose the injected Options for assertion.
			 *
			 * @return Options
			 */
			public function get_injected_options(): Options {
				return $this->options;
			}

			/**
			 * Expose the injected Util for assertion.
			 *
			 * @return Util
			 */
			public function get_injected_util(): Util {
				return $this->util;
			}
		};

		$this->assertSame( $options, $integration->get_injected_options() );
		$this->assertSame( $util, $integration->get_injected_util() );
	}
}
