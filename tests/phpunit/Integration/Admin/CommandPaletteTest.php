<?php
/**
 * Integration tests for the GTM Kit command palette entries.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\CommandPalette}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\CommandPalette;
use WP_UnitTestCase;

/**
 * The commands reach only users who can open the GTM Kit screens.
 */
final class CommandPaletteTest extends WP_UnitTestCase {

	/**
	 * Start every test without the commands script.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_scripts()->remove( CommandPalette::HANDLE );
	}

	/**
	 * Leave no capability filter and no commands script behind.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'gtmkit_admin_capability' );
		wp_scripts()->remove( CommandPalette::HANDLE );
		parent::tear_down();
	}

	/**
	 * Log in a user with the given role.
	 *
	 * @param string $role The role.
	 *
	 * @return void
	 */
	private function log_in( string $role ): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
	}

	/**
	 * The command names the current user gets.
	 *
	 * @return array<int, string>
	 */
	private function command_names(): array {
		return array_column( ( new CommandPalette() )->get_commands(), 'name' );
	}

	/**
	 * An administrator gets every section, the wizard and Google Tag Manager.
	 *
	 * @return void
	 */
	public function test_administrator_gets_every_command(): void {
		$this->log_in( 'administrator' );

		$commands = ( new CommandPalette() )->get_commands();
		$by_name  = array_column( $commands, null, 'name' );

		$this->assertContains( 'gtmkit/dashboard', array_keys( $by_name ) );
		$this->assertContains( 'gtmkit/setup-wizard', array_keys( $by_name ) );
		$this->assertSame( admin_url( 'admin.php?page=gtmkit_general' ) . '#/consent', $by_name['gtmkit/consent']['url'] );
		$this->assertSame( 'https://tagmanager.google.com/', $by_name['gtmkit/google-tag-manager']['url'] );
		$this->assertTrue( $by_name['gtmkit/google-tag-manager']['external'] );

		foreach ( $commands as $command ) {
			$this->assertStringStartsWith( 'GTM Kit: ', $command['label'] );
		}
	}

	/**
	 * The script is enqueued on top of the command palette, with the commands inline.
	 *
	 * @return void
	 */
	public function test_script_depends_on_the_command_palette(): void {
		$this->log_in( 'administrator' );

		( new CommandPalette() )->enqueue();

		$this->assertTrue( wp_script_is( CommandPalette::HANDLE, 'enqueued' ) );
		$this->assertSame( [ 'wp-commands', 'wp-data' ], wp_scripts()->registered[ CommandPalette::HANDLE ]->deps );
		$this->assertStringContainsString( '"gtmkit/dashboard"', implode( '', wp_scripts()->get_data( CommandPalette::HANDLE, 'after' ) ) );
	}

	/**
	 * An editor gets no commands and no script.
	 *
	 * @return void
	 */
	public function test_editor_gets_nothing(): void {
		$this->log_in( 'editor' );

		( new CommandPalette() )->enqueue();

		$this->assertSame( [], $this->command_names() );
		$this->assertFalse( wp_script_is( CommandPalette::HANDLE, 'registered' ) );
	}

	/**
	 * A capability granted through the filter opens the settings, but not the
	 * wizard, which WordPress registers for administrators only.
	 *
	 * @return void
	 */
	public function test_filtered_capability_gets_settings_but_not_the_wizard(): void {
		$this->log_in( 'editor' );
		add_filter(
			'gtmkit_admin_capability',
			static function () {
				return 'edit_others_posts';
			}
		);

		$names = $this->command_names();

		$this->assertContains( 'gtmkit/dashboard', $names );
		$this->assertNotContains( 'gtmkit/setup-wizard', $names );
	}
}
