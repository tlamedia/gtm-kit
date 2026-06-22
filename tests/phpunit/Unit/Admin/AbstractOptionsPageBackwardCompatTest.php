<?php
/**
 * Backward-compatibility guard for {@see AbstractOptionsPage}.
 *
 * Add-ons subclass this base against whatever core is active at runtime, and
 * core auto-updates ahead of them. A published add-on page implements only the
 * abstract members and inherits add_admin_page(); if that method becomes
 * abstract again, the add-on class can no longer be declared and the admin
 * screen fatals on the next page load. These tests lock add_admin_page() (and
 * its get_position() dependency) as concrete so such a subclass keeps loading.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use ReflectionMethod;
use TLA_Media\GTM_Kit\Admin\AbstractOptionsPage;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

require_once __DIR__ . '/fixtures/BackwardCompatFixtureOptionsPage.php';

/**
 * Guards that AbstractOptionsPage stays subclassable by add-on pages that do
 * not override add_admin_page().
 */
final class AbstractOptionsPageBackwardCompatTest extends TestCase {

	/**
	 * Keeps add_admin_page() concrete so add-on subclasses can inherit it.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\AbstractOptionsPage::add_admin_page
	 */
	public function test_add_admin_page_stays_concrete(): void {
		$method = new ReflectionMethod( AbstractOptionsPage::class, 'add_admin_page' );

		$this->assertFalse(
			$method->isAbstract(),
			'add_admin_page() must stay concrete; making it abstract fatals add-on pages built against an older core.'
		);
	}

	/**
	 * Keeps get_position() as a concrete method add_admin_page() can call.
	 *
	 * @covers \TLA_Media\GTM_Kit\Admin\AbstractOptionsPage::get_position
	 */
	public function test_get_position_stays_concrete(): void {
		$this->assertTrue(
			method_exists( AbstractOptionsPage::class, 'get_position' ),
			'get_position() must exist; add_admin_page() passes it to add_submenu_page().'
		);
		$this->assertFalse(
			( new ReflectionMethod( AbstractOptionsPage::class, 'get_position' ) )->isAbstract()
		);
	}

	/**
	 * Confirms a subclass implementing only the abstract members is declarable.
	 */
	public function test_subclass_without_add_admin_page_is_declarable(): void {
		$this->assertTrue( class_exists( BackwardCompatFixtureOptionsPage::class ) );
	}
}
