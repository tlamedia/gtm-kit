<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Domain;

/**
 * Sortable collection of Introduction_Interface instances. Kept minimal on purpose: add,
 * drop-by-id, sort by priority, iterate.
 */
final class Introductions_Bucket {

	/**
	 * Items in the bucket.
	 *
	 * @var Introduction_Interface[]
	 */
	private array $items = [];

	/**
	 * Add an introduction to the bucket.
	 *
	 * @param Introduction_Interface $intro The introduction.
	 *
	 * @return void
	 */
	public function add( Introduction_Interface $intro ): void {
		$this->items[] = $intro;
	}

	/**
	 * Remove introductions whose id matches the given list.
	 *
	 * @param string[] $ids The ids to drop.
	 *
	 * @return void
	 */
	public function drop( array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}
		$index       = array_flip( $ids );
		$this->items = array_values(
			array_filter(
				$this->items,
				static function ( Introduction_Interface $intro ) use ( $index ): bool {
					return ! isset( $index[ $intro->get_id() ] );
				}
			)
		);
	}

	/**
	 * Return all items, sorted by priority ascending. Ties keep insertion order.
	 *
	 * @return Introduction_Interface[]
	 */
	public function sorted(): array {
		$items = $this->items;
		usort(
			$items,
			static function ( Introduction_Interface $a, Introduction_Interface $b ): int {
				return $a->get_priority() <=> $b->get_priority();
			}
		);
		return $items;
	}

	/**
	 * Return all ids currently in the bucket.
	 *
	 * @return string[]
	 */
	public function ids(): array {
		return array_map(
			static function ( Introduction_Interface $intro ): string {
				return $intro->get_id();
			},
			$this->items
		);
	}
}
