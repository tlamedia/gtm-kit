<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Domain;

/**
 * Immutable value object the collector returns. Built from an Introduction_Interface and the data
 * the JS renderer needs.
 */
final class Introduction_Item {

	/**
	 * Stable id.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Sort priority. Lower wins.
	 *
	 * @var int
	 */
	private int $priority;

	/**
	 * Render mode ('component' or 'blocks').
	 *
	 * @var string
	 */
	private string $render_mode;

	/**
	 * Blocks payload for the generic-blocks renderer (empty for component render mode).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $blocks;

	/**
	 * Constructor.
	 *
	 * @param string                           $id Stable id.
	 * @param int                              $priority Sort priority.
	 * @param string                           $render_mode 'component' or 'blocks'.
	 * @param array<int, array<string, mixed>> $blocks Blocks payload.
	 */
	public function __construct( string $id, int $priority, string $render_mode, array $blocks = [] ) {
		$this->id          = $id;
		$this->priority    = $priority;
		$this->render_mode = $render_mode;
		$this->blocks      = $blocks;
	}

	/**
	 * Build an Introduction_Item from a source Introduction_Interface.
	 *
	 * @param Introduction_Interface $intro The source intro.
	 *
	 * @return self
	 */
	public static function from_interface( Introduction_Interface $intro ): self {
		return new self(
			$intro->get_id(),
			$intro->get_priority(),
			$intro->get_render_mode(),
			$intro->get_blocks()
		);
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
	 * The render mode.
	 *
	 * @return string
	 */
	public function get_render_mode(): string {
		return $this->render_mode;
	}

	/**
	 * The blocks payload.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_blocks(): array {
		return $this->blocks;
	}

	/**
	 * Serialize for the JS-localised payload.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'          => $this->id,
			'priority'    => $this->priority,
			'render_mode' => $this->render_mode,
			'blocks'      => $this->blocks,
		];
	}
}
