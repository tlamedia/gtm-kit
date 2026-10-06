<?php
/**
 * Integration tests for the notice raised when no Stape-issued loader is stored.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\StapeLoaderNotice}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Admin\StapeLoaderNotice;
use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * The notice follows the stored loader, and its dismissal lasts only while no loader is stored.
 */
final class StapeLoaderNoticeTest extends WP_UnitTestCase {

	/**
	 * The notifications handler the notice writes to.
	 *
	 * @var NotificationsHandler
	 */
	private NotificationsHandler $handler;

	/**
	 * Seed a site on a Stape container with the issued loader switched on and nothing stored.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->set_general( [ 'sgtm_stape_issued_loader' => true ] );

		delete_option( StapeLoader::OPTION );
		delete_option( StapeLoaderNotice::DISMISSED_OPTION );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->handler = NotificationsHandler::get();
		$this->handler->setup_current_notifications();
		$this->handler->remove_notification_by_id( StapeLoaderNotice::NOTIFICATION_ID );
	}

	/**
	 * Store the general settings the loader reads, over a complete Stape setup.
	 *
	 * @param array<string, mixed> $overrides Settings to change from the complete setup.
	 *
	 * @return void
	 */
	private function set_general( array $overrides ): void {
		OptionsFactory::get_instance()->set(
			[
				'general' => array_merge(
					[
						'gtm_id'                    => 'GTM-TW5FD4G7',
						'datalayer_name'            => '',
						'sgtm_domain'               => 'collect.gtmkit.com',
						'sgtm_container_identifier' => '38i0hixjpkyq',
						'sgtm_cookie_keeper'        => false,
						'sgtm_stape_issued_loader'  => true,
						'google_tag_gateway'        => false,
					],
					$overrides
				),
			]
		);
	}

	/**
	 * Store a checked loader for the current settings.
	 *
	 * @return void
	 */
	private function store_loader(): void {
		( new StapeLoader( OptionsFactory::get_instance() ) )->store(
			[
				'path'  => 'abcdef',
				'param' => 'id',
				'value' => 'aW9uLXRlc3Q',
			],
			StapeLoader::SOURCE_API,
			'eu'
		);
	}

	/**
	 * Evaluate the notice against the current state.
	 *
	 * @return void
	 */
	private function evaluate(): void {
		( new StapeLoaderNotice( new StapeLoader( OptionsFactory::get_instance() ), $this->handler ) )->evaluate();
	}

	/**
	 * Whether the notice is currently in the set.
	 *
	 * @return bool
	 */
	private function is_shown(): bool {
		return null !== $this->handler->get_notification_by_id( StapeLoaderNotice::NOTIFICATION_ID );
	}

	/**
	 * Switched on and set up with nothing stored raises the notice.
	 *
	 * @return void
	 */
	public function test_raised_when_no_loader_is_stored(): void {
		$this->evaluate();

		$this->assertTrue( $this->is_shown() );
	}

	/**
	 * A loader stored without the data layer check counts as none.
	 *
	 * @return void
	 */
	public function test_raised_when_the_stored_loader_was_never_checked(): void {
		update_option(
			StapeLoader::OPTION,
			[
				'path'   => 'abcdef',
				'param'  => 'id',
				'value'  => 'GTM-TW5FD4G7',
				'inputs' => ( new StapeLoader( OptionsFactory::get_instance() ) )->fingerprint(),
				'source' => StapeLoader::SOURCE_API,
			]
		);

		$this->evaluate();

		$this->assertTrue( $this->is_shown() );
	}

	/**
	 * Storing a loader withdraws the notice.
	 *
	 * @return void
	 */
	public function test_withdrawn_once_a_loader_is_stored(): void {
		$this->evaluate();
		$this->store_loader();
		$this->evaluate();

		$this->assertFalse( $this->is_shown() );
	}

	/**
	 * Switching the issued loader off withdraws the notice.
	 *
	 * @return void
	 */
	public function test_withdrawn_when_switched_off(): void {
		$this->evaluate();
		$this->set_general( [ 'sgtm_stape_issued_loader' => false ] );
		$this->evaluate();

		$this->assertFalse( $this->is_shown() );
	}

	/**
	 * Settings Stape needs that are missing mean the loader is not in use, so there is nothing to report.
	 *
	 * @return void
	 */
	public function test_not_raised_while_the_setup_is_incomplete(): void {
		$this->set_general( [ 'sgtm_container_identifier' => '' ] );
		$this->evaluate();

		$this->assertFalse( $this->is_shown() );
	}

	/**
	 * A dismissed notice stays away while nothing is stored.
	 *
	 * @return void
	 */
	public function test_dismissal_keeps_it_away_while_the_state_lasts(): void {
		StapeLoaderNotice::dismiss();

		$this->evaluate();
		$this->evaluate();

		$this->assertFalse( $this->is_shown() );
		$this->assertTrue( StapeLoaderNotice::is_dismissed() );
	}

	/**
	 * Once a loader is stored the dismissal is spent, so losing the loader again is reported.
	 *
	 * @return void
	 */
	public function test_returns_when_the_loader_is_lost_again_after_a_dismissal(): void {
		StapeLoaderNotice::dismiss();
		$this->evaluate();

		$this->store_loader();
		$this->evaluate();

		$this->assertFalse( StapeLoaderNotice::is_dismissed() );

		delete_option( StapeLoader::OPTION );
		$this->evaluate();

		$this->assertTrue( $this->is_shown() );
	}
}
