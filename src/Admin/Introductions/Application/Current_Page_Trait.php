<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Application;

use TLA_Media\GTM_Kit\Admin\SetupWizard;

/**
 * Page-detection helpers used to suppress introductions during onboarding surfaces. The
 * integration class calls these before enqueueing the bundle so users are not interrupted while
 * completing the setup wizard.
 */
trait Current_Page_Trait {

	/**
	 * Whether the current admin request is the setup wizard page.
	 *
	 * @return bool
	 */
	protected function is_on_setup_wizard(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page detection; no state changes.
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = \sanitize_text_field( \wp_unslash( (string) $_GET['page'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $page === SetupWizard::SLUG;
	}

	/**
	 * Whether the current admin request is an installation-success screen.
	 *
	 * The GTM Kit wizard handles its own terminal "Getting started" state inside the wizard page,
	 * so this returns false for the current codebase. Kept as a distinct surface so a future
	 * dedicated success page can be added without touching every caller.
	 *
	 * @return bool
	 */
	protected function is_on_installation_success(): bool {
		return false;
	}

	/**
	 * Whether the given admin hook belongs to a GTM Kit settings page.
	 *
	 * @param mixed $hook The current admin page hook suffix.
	 *
	 * @return bool
	 */
	protected function is_on_gtmkit_admin_page( $hook ): bool {
		if ( ! is_string( $hook ) || $hook === '' ) {
			return false;
		}
		return strpos( $hook, 'gtmkit' ) !== false;
	}
}
