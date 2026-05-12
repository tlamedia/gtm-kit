<?php
/**
 * Integration tests for the Introductions admin integration.
 *
 * Covers:
 *
 *  - The setup-wizard page early-bails so no bundle is enqueued there.
 *  - Non-GTM Kit admin pages early-bail so the bundle stays off unrelated dashboards.
 *  - A registered eligible intro on a GTM Kit admin page is marked seen server-side as soon as
 *    it renders.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_Integration}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Introductions;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector;
use TLA_Media\GTM_Kit\Admin\Introductions\Application\Welcome_Introduction;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;
use TLA_Media\GTM_Kit\Admin\Introductions\UI\Introductions_Integration;
use TLA_Media\GTM_Kit\Admin\SetupWizard;
use WP_UnitTestCase;

/**
 * Covers wizard-page suppression and eligible-intro enqueueing.
 */
final class IntroductionsIntegrationTest extends WP_UnitTestCase {

	/**
	 * Editor user id used for the eligibility test.
	 *
	 * @var int
	 */
	private int $editor_id = 0;

	/**
	 * Reset state and create the per-test user.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'gtmkit_introductions' );
		wp_scripts()->remove( 'gtmkit-introductions-script' );
		unset( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// Treat the test as a fresh install at the introductions-framework release so the welcome
		// intro is eligible.
		update_option( 'gtmkit_initial_version', '2.12.0' );
		$this->editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $this->editor_id );
	}

	/**
	 * Clean up the bundled-intro seen flag we set in tests so it does not leak between cases.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_user_meta( $this->editor_id, Introductions_Seen_Repository::META_KEY );
		unset( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		parent::tear_down();
	}

	/**
	 * On the setup wizard page, no introductions bundle is enqueued.
	 *
	 * @return void
	 */
	public function test_setup_wizard_page_bails(): void {
		$_GET['page'] = SetupWizard::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$integration = $this->build_integration();
		$integration->enqueue( 'toplevel_page_' . SetupWizard::SLUG );

		$this->assertFalse( wp_script_is( 'gtmkit-introductions-script', 'enqueued' ) );
	}

	/**
	 * On a non-GTM Kit admin page, no introductions bundle is enqueued.
	 *
	 * @return void
	 */
	public function test_non_gtmkit_admin_page_bails(): void {
		$integration = $this->build_integration();
		$integration->enqueue( 'index.php' );

		$this->assertFalse( wp_script_is( 'gtmkit-introductions-script', 'enqueued' ) );
	}

	/**
	 * When an eligible intro exists on a GTM Kit admin page, the integration marks the
	 * highest-priority intro as seen server-side.
	 *
	 * The script is only enqueued when the built asset bundle exists on disk, so we assert the
	 * seen-state side effect (which runs unconditionally once an eligible intro is picked) to
	 * keep the test independent of build output.
	 *
	 * @return void
	 */
	public function test_eligible_intro_is_marked_seen_on_render(): void {
		$integration = $this->build_integration();
		$integration->enqueue( 'gtmkit_page_gtmkit_general' );

		$seen = new Introductions_Seen_Repository();
		$this->assertTrue( $seen->is_seen( $this->editor_id, Welcome_Introduction::ID ) );
	}

	/**
	 * Build a single integration instance wired up for the test.
	 *
	 * @return Introductions_Integration
	 */
	private function build_integration(): Introductions_Integration {
		$seen      = new Introductions_Seen_Repository();
		$collector = new Introductions_Collector( $seen, [ new Welcome_Introduction() ] );

		return new Introductions_Integration( $collector, $seen );
	}
}
