<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Application;

use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Interface;

/**
 * First-run welcome introduction shown once to users who freshly installed GTM Kit at the version
 * the introductions framework first shipped in or later.
 *
 * The wizard is the first-run welcome surface; this modal greets the user on the first non-wizard
 * GTM Kit admin page they visit. The integration class handles the wizard-page suppression. Users
 * who installed before the threshold are upgraders and never see this modal, even if they later
 * upgrade past the threshold.
 */
final class Welcome_Introduction implements Introduction_Interface {

	use Version_Trait;

	/**
	 * Stable id used for component lookup and seen-state.
	 *
	 * @var string
	 */
	public const ID = 'welcome';

	/**
	 * Minimum initial-install version that makes a user eligible. Anyone who first installed below
	 * this version is considered an upgrader and never sees the welcome.
	 *
	 * @var string
	 */
	private const MIN_INITIAL_VERSION = '2.12.0';

	/**
	 * The intro id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Sort priority. Bundled welcome ranks ahead of remote launch intros so a brand-new user sees
	 * the welcome first.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return 100;
	}

	/**
	 * Eligibility check: the user first installed at or after the introductions-framework release.
	 * Existing users who installed earlier are upgraders and do not see this modal.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		return $this->version_installed_at_or_above( self::MIN_INITIAL_VERSION );
	}

	/**
	 * Render mode is the bundled React component keyed by id.
	 *
	 * @return string
	 */
	public function get_render_mode(): string {
		return 'component';
	}

	/**
	 * No content blocks: the matching React component owns its content.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_blocks(): array {
		return [];
	}
}
