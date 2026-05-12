<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Domain;

/**
 * Contract for a registrable introduction (one announcement modal).
 *
 * Implementations live in `gtm-kit` core for bundled intros and in sibling plugins
 * (`gtm-kit-premium`, `gtm-kit-woo`) for feature-launch intros they own. Remote intros are
 * wrapped in an adapter that also implements this interface.
 */
interface Introduction_Interface {

	/**
	 * Stable, kebab-case identifier. Must be unique across every registered introduction. The id
	 * is the React component lookup key for bundled intros and the seen-state key for every intro.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Priority used to order eligible intros. Lower wins. Suggested ranges: bundled welcome around
	 * 100, version-bump intros around 200, marketing intros around 500.
	 *
	 * @return int
	 */
	public function get_priority(): int;

	/**
	 * Eligibility check. Returning false drops the intro before the seen filter runs.
	 *
	 * @return bool
	 */
	public function should_show(): bool;

	/**
	 * 'component' selects an id-matched React component bundled with a plugin. 'blocks' selects
	 * the generic-blocks renderer driven by `get_blocks()`.
	 *
	 * @return string Either 'component' or 'blocks'.
	 */
	public function get_render_mode(): string;

	/**
	 * Structured content blocks for the generic-blocks renderer. Returns an empty array for
	 * component-render intros.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_blocks(): array;
}
