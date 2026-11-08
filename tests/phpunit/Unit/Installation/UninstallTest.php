<?php
/**
 * Unit tests for the uninstall routine.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Installation;

use TLA_Media\GTM_Kit\Admin\PremiumTriggerCooldown;
use TLA_Media\GTM_Kit\Admin\StapeLoaderNotice;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Common\SupportSync;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Checks that uninstalling removes every option GTM Kit stores.
 */
final class UninstallTest extends TestCase {

	/**
	 * Options GTM Kit stores outside the main settings.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function stored_options(): array {
		return [
			'stape loader'              => [ StapeLoader::OPTION ],
			'stape loader notice'       => [ StapeLoaderNotice::DISMISSED_OPTION ],
			'gateway health'            => [ GoogleTagGatewayHealth::OPTION ],
			'gateway notice'            => [ GoogleTagGatewayHealth::NOTICE_DISMISSED_OPTION ],
			'gateway blocked notice'    => [ GoogleTagGatewayHealth::BLOCKED_NOTICE_DISMISSED_OPTION ],
			'snippet scan'              => [ SnippetScan::OPTION ],
			'support sync'              => [ SupportSync::OPTION ],
			'upgrade notice dismissals' => [ PremiumTriggerCooldown::OPTION ],
		];
	}

	/**
	 * Uninstalling deletes the option.
	 *
	 * @dataProvider stored_options
	 *
	 * @param string $option The option name.
	 */
	public function test_uninstall_deletes_the_option( string $option ): void {
		$source = (string) file_get_contents( dirname( __DIR__, 4 ) . '/uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local plugin file.

		$this->assertStringContainsString( "delete_option( '" . $option . "' );", $source );
	}
}
