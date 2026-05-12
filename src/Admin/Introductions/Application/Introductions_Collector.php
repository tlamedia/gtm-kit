<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\Application;

use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Interface;
use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Item;
use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introductions_Bucket;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;

/**
 * Builds the ordered list of introductions a given user is eligible to see right now.
 *
 * Merge order: bundled intros, then anything plugged in via the `gtmkit_introductions` filter.
 * Non-conforming filter entries are silently dropped. `should_show()` runs first, then the seen
 * filter, then sort by priority ascending.
 */
final class Introductions_Collector {

	/**
	 * Seen-state repository.
	 *
	 * @var Introductions_Seen_Repository
	 */
	private Introductions_Seen_Repository $seen;

	/**
	 * Bundled intros registered in core. Sibling plugins use the filter.
	 *
	 * @var Introduction_Interface[]
	 */
	private array $bundled;

	/**
	 * Constructor.
	 *
	 * @param Introductions_Seen_Repository $seen The seen-state repository.
	 * @param Introduction_Interface[]      $bundled The bundled introductions.
	 */
	public function __construct( Introductions_Seen_Repository $seen, array $bundled = [] ) {
		$this->seen    = $seen;
		$this->bundled = array_values( $bundled );
	}

	/**
	 * Return the ordered list of Introduction_Item DTOs the user is currently eligible to see.
	 *
	 * @param int $user_id The user id.
	 *
	 * @return Introduction_Item[]
	 */
	public function get_for( int $user_id ): array {
		$intros = $this->collect_intros();

		$eligible = array_filter(
			$intros,
			function ( Introduction_Interface $intro ) use ( $user_id ): bool {
				if ( ! $intro->should_show() ) {
					return false;
				}
				return ! $this->seen->is_seen( $user_id, $intro->get_id() );
			}
		);

		usort(
			$eligible,
			static function ( Introduction_Interface $a, Introduction_Interface $b ): int {
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		return array_map(
			static function ( Introduction_Interface $intro ): Introduction_Item {
				return Introduction_Item::from_interface( $intro );
			},
			$eligible
		);
	}

	/**
	 * Return the ids of every registered introduction, regardless of eligibility or seen state.
	 * Used by the REST seen route to validate incoming ids.
	 *
	 * @return string[]
	 */
	public function get_registered_ids(): array {
		$ids = [];
		foreach ( $this->collect_intros() as $intro ) {
			$ids[] = $intro->get_id();
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Merge bundled intros with anything plugged in via the filter.
	 *
	 * @return Introduction_Interface[]
	 */
	private function collect_intros(): array {
		$bucket = new Introductions_Bucket();
		foreach ( $this->bundled as $intro ) {
			$bucket->add( $intro );
		}

		/**
		 * Filters the list of registrable introductions.
		 *
		 * Sibling plugins add to this list to register their own intros. Entries that are not
		 * Introduction_Interface instances are silently dropped.
		 *
		 * @param mixed $introductions The current list. Should be an array of Introduction_Interface
		 *                             instances; any other shape is silently dropped.
		 */
		$filtered = \apply_filters( 'gtmkit_introductions', $bucket->sorted() );

		if ( ! is_array( $filtered ) ) {
			return $bucket->sorted();
		}

		$valid = [];
		foreach ( $filtered as $intro ) {
			if ( $intro instanceof Introduction_Interface ) {
				$valid[] = $intro;
			}
		}
		return $valid;
	}
}
