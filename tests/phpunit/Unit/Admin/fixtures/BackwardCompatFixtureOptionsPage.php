<?php
/**
 * Test fixture: a minimal options page for the AbstractOptionsPage
 * backward-compatibility guard.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Admin;

use TLA_Media\GTM_Kit\Admin\AbstractOptionsPage;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * A minimal options page implementing only AbstractOptionsPage's abstract
 * members, mirroring an add-on page (e.g. TemplatesOptionsPagePro) built against
 * the base class. Declaring this class is itself a guard: were add_admin_page()
 * abstract again, PHP would fatal loading this file.
 */
final class BackwardCompatFixtureOptionsPage extends AbstractOptionsPage {

	/**
	 * Build the page instance.
	 *
	 * @param Options $options Options.
	 * @param Util    $util Utilities.
	 */
	protected static function create_instance( Options $options, Util $util ): AbstractOptionsPage {
		return new self( $options, $util );
	}

	/**
	 * Configure the page.
	 */
	public function configure(): void {}

	/**
	 * Enqueue assets.
	 *
	 * @param mixed $hook Current hook.
	 */
	public function enqueue_page_assets( $hook ): void {}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug Page slug.
	 * @param string $script_handle Script handle.
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {}

	/**
	 * Menu slug.
	 */
	protected function get_menu_slug(): string {
		return 'gtmkit_fixture';
	}

	/**
	 * Page title.
	 */
	protected function get_page_title(): string {
		return 'Fixture';
	}

	/**
	 * Parent slug.
	 */
	protected function get_parent_slug(): string {
		return 'gtmkit_general';
	}
}
