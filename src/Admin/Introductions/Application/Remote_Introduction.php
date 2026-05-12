<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Application;

use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Interface;

/**
 * Adapter that wraps a validated remote intro payload so the rest of the framework can treat it
 * like a regular Introduction_Interface.
 *
 * Targeting is decided server-side: the GTM Kit app filters intros by audience predicate before
 * sending them, so this adapter trusts the response and always reports as eligible. The seen
 * filter still applies at the collector layer.
 */
final class Remote_Introduction implements Introduction_Interface {

	/**
	 * Stable id.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Sort priority.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Validated content blocks.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $blocks;

	/**
	 * Constructor.
	 *
	 * @param string                           $id Stable id.
	 * @param int                              $priority Sort priority.
	 * @param array<int, array<string, mixed>> $blocks Validated blocks.
	 */
	public function __construct( string $id, int $priority, array $blocks ) {
		$this->id       = $id;
		$this->priority = $priority;
		$this->blocks   = $blocks;
	}

	/**
	 * The intro id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * The intro priority.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Eligibility check. Always true: the server already filtered by audience predicate before
	 * sending this intro.
	 *
	 * @return bool
	 */
	public function should_show(): bool {
		return true;
	}

	/**
	 * Render mode is the generic-blocks renderer.
	 *
	 * @return string
	 */
	public function get_render_mode(): string {
		return 'blocks';
	}

	/**
	 * The validated content blocks.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}
}
