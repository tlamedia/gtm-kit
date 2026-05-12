<?php
/**
 * Integration tests for the Introductions collector.
 *
 * Covers:
 *
 *  - The collector returns bundled intros sorted by priority ascending.
 *  - Seen filtering removes intros the user has already dismissed.
 *  - The `gtmkit_introductions` filter silently drops non-conforming entries.
 *
 * Targets:
 *
 *  - {@see \TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector}
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Introductions;

use TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector;
use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Interface;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;
use WP_UnitTestCase;

/**
 * Covers the collector merge, sort, and seen-filter behaviour.
 */
final class IntroductionsCollectorTest extends WP_UnitTestCase {

	/**
	 * Reset the introductions filter between tests so registrations from one test do not leak
	 * into another.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'gtmkit_introductions' );
	}

	/**
	 * Two bundled intros come back ordered by priority ascending.
	 *
	 * @return void
	 */
	public function test_returns_bundled_intros_sorted_by_priority(): void {
		$user_id = self::factory()->user->create();
		$seen    = new Introductions_Seen_Repository();

		$low  = $this->make_intro( 'low-priority', 50 );
		$mid  = $this->make_intro( 'mid-priority', 200 );
		$high = $this->make_intro( 'high-priority', 100 );

		$collector = new Introductions_Collector( $seen, [ $mid, $high, $low ] );

		$items = $collector->get_for( $user_id );

		$this->assertCount( 3, $items );
		$this->assertSame( 'low-priority', $items[0]->get_id() );
		$this->assertSame( 'high-priority', $items[1]->get_id() );
		$this->assertSame( 'mid-priority', $items[2]->get_id() );
	}

	/**
	 * An intro the user has already seen is excluded.
	 *
	 * @return void
	 */
	public function test_seen_filtering_excludes_already_seen_intros(): void {
		$user_id = self::factory()->user->create();
		$seen    = new Introductions_Seen_Repository();
		$seen->mark_seen( $user_id, 'already-seen' );

		$collector = new Introductions_Collector(
			$seen,
			[ $this->make_intro( 'already-seen', 100 ), $this->make_intro( 'fresh', 200 ) ]
		);

		$items = $collector->get_for( $user_id );

		$this->assertCount( 1, $items );
		$this->assertSame( 'fresh', $items[0]->get_id() );
	}

	/**
	 * The `gtmkit_introductions` filter silently drops anything that is not an
	 * `Introduction_Interface` instance.
	 *
	 * @return void
	 */
	public function test_filter_drops_non_conforming_entries(): void {
		$user_id = self::factory()->user->create();
		$seen    = new Introductions_Seen_Repository();
		$intro   = $this->make_intro( 'from-filter', 300 );

		add_filter(
			'gtmkit_introductions',
			static function ( $intros ) use ( $intro ): array {
				return array_merge(
					(array) $intros,
					[
						$intro,
						'not-an-intro',
						new \stdClass(),
						[ 'array' => 'also-rejected' ],
					]
				);
			}
		);

		$collector = new Introductions_Collector( $seen );

		$items = $collector->get_for( $user_id );

		$this->assertCount( 1, $items );
		$this->assertSame( 'from-filter', $items[0]->get_id() );
	}

	/**
	 * Intros whose `should_show()` returns false are dropped.
	 *
	 * @return void
	 */
	public function test_should_show_false_drops_intro(): void {
		$user_id = self::factory()->user->create();
		$seen    = new Introductions_Seen_Repository();

		$visible = $this->make_intro( 'visible', 100 );
		$hidden  = $this->make_intro( 'hidden', 150, false );

		$collector = new Introductions_Collector( $seen, [ $visible, $hidden ] );

		$items = $collector->get_for( $user_id );

		$this->assertCount( 1, $items );
		$this->assertSame( 'visible', $items[0]->get_id() );
	}

	/**
	 * `get_registered_ids()` lists every registered intro id, even if they have been seen or are
	 * not currently eligible.
	 *
	 * @return void
	 */
	public function test_get_registered_ids_lists_all_registrations(): void {
		$seen      = new Introductions_Seen_Repository();
		$collector = new Introductions_Collector(
			$seen,
			[ $this->make_intro( 'bundled-a', 100 ), $this->make_intro( 'bundled-b', 200, false ) ]
		);

		add_filter(
			'gtmkit_introductions',
			function ( $intros ): array {
				return array_merge( (array) $intros, [ $this->make_intro( 'from-filter', 300 ) ] );
			}
		);

		$ids = $collector->get_registered_ids();
		sort( $ids );

		$this->assertSame( [ 'bundled-a', 'bundled-b', 'from-filter' ], $ids );
	}

	/**
	 * Build a small anonymous Introduction_Interface for tests.
	 *
	 * @param string $id The intro id.
	 * @param int    $priority The priority.
	 * @param bool   $should_show The should_show return value.
	 *
	 * @return Introduction_Interface
	 */
	private function make_intro( string $id, int $priority, bool $should_show = true ): Introduction_Interface {
		return new class( $id, $priority, $should_show ) implements Introduction_Interface {
			/**
			 * Intro id.
			 *
			 * @var string
			 */
			private string $id;

			/**
			 * Intro priority.
			 *
			 * @var int
			 */
			private int $priority;

			/**
			 * Should-show flag.
			 *
			 * @var bool
			 */
			private bool $should_show;

			/**
			 * Constructor.
			 *
			 * @param string $id Intro id.
			 * @param int    $priority Priority.
			 * @param bool   $should_show Should-show.
			 */
			public function __construct( string $id, int $priority, bool $should_show ) {
				$this->id          = $id;
				$this->priority    = $priority;
				$this->should_show = $should_show;
			}

			/**
			 * Stable id.
			 *
			 * @return string
			 */
			public function get_id(): string {
				return $this->id;
			}

			/**
			 * Sort priority.
			 *
			 * @return int
			 */
			public function get_priority(): int {
				return $this->priority;
			}

			/**
			 * Eligibility flag.
			 *
			 * @return bool
			 */
			public function should_show(): bool {
				return $this->should_show;
			}

			/**
			 * Render mode.
			 *
			 * @return string
			 */
			public function get_render_mode(): string {
				return 'component';
			}

			/**
			 * Blocks payload.
			 *
			 * @return array<int, array<string, mixed>>
			 */
			public function get_blocks(): array {
				return [];
			}
		};
	}
}
