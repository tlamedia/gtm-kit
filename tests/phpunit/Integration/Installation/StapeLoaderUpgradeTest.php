<?php
/**
 * Integration tests for the upgrade that removes loaders stored without the data layer check.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Installation\Upgrade}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Installation;

use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Installation\Upgrade;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * An unchecked stored loader is deleted on upgrade, and a checked one is kept.
 */
final class StapeLoaderUpgradeTest extends WP_UnitTestCase {

	/**
	 * A loader as it was stored before loaders were checked against the data layer name.
	 *
	 * @var array<string, mixed>
	 */
	private const UNCHECKED = [
		'path'       => '38i0hixjpkyq',
		'param'      => '3bsw',
		'value'      => 'GB1WNz41RDwmTC00Xj9eTgdEWV5bXg0GTB4fHQERHUYSFgY%3D',
		'inputs'     => 'd41d8cd98f00b204e9800998ecf8427e',
		'source'     => 'pasted',
		'region'     => '',
		'fetched_at' => 1758000000,
	];

	/**
	 * Start every test with no stored loader.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		delete_option( StapeLoader::OPTION );
	}

	/**
	 * Run the upgrade from a given installed version.
	 *
	 * @param string $version The installed version.
	 *
	 * @return void
	 */
	private function upgrade_from( string $version ): void {
		update_option( 'gtmkit_version', $version, false );

		new Upgrade( OptionsFactory::get_instance() );
	}

	/**
	 * Upgrading from the release before the check deletes an unchecked loader.
	 *
	 * @return void
	 */
	public function test_an_unchecked_loader_is_deleted(): void {
		update_option( StapeLoader::OPTION, self::UNCHECKED, true );

		$this->upgrade_from( '2.20.1' );

		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * A loader stored with the check is left alone.
	 *
	 * @return void
	 */
	public function test_a_checked_loader_is_kept(): void {
		$checked = self::UNCHECKED + [ 'datalayer_checked' => true ];
		update_option( StapeLoader::OPTION, $checked, true );

		$this->upgrade_from( '2.20.1' );

		$this->assertSame( $checked, get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Nothing is stored after the upgrade when nothing was stored before it.
	 *
	 * @return void
	 */
	public function test_nothing_stored_stays_empty(): void {
		$this->upgrade_from( '2.20.1' );

		$this->assertFalse( get_option( StapeLoader::OPTION ) );
	}

	/**
	 * Sites already on the release with the check do not run the routine again.
	 *
	 * @return void
	 */
	public function test_the_routine_does_not_run_from_the_current_release(): void {
		update_option( StapeLoader::OPTION, self::UNCHECKED, true );

		$this->upgrade_from( '2.20.2' );

		$this->assertSame( self::UNCHECKED, get_option( StapeLoader::OPTION ) );
	}
}
