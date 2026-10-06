<?php
/**
 * Integration tests for which Google tag gateway notice a site is shown.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Admin\GoogleTagGatewayNotice} and the
 * states it reads from {@see \TLA_Media\GTM_Kit\Common\GoogleTagGateway}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Integration\Admin;

use TLA_Media\GTM_Kit\Admin\GoogleTagGatewayNotice;
use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Common\GoogleTagGateway;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\OptionsFactory;
use WP_UnitTestCase;

/**
 * A failing gateway and a gateway ruled out by a custom sGTM domain get different notices.
 */
final class GoogleTagGatewayNoticeStatesTest extends WP_UnitTestCase {

	/**
	 * The notifications handler the notice writes to.
	 *
	 * @var NotificationsHandler
	 */
	private NotificationsHandler $handler;

	/**
	 * Start from a site with the gateway switched off and no dismissals.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'gtm_id', 'GTM-TW5FD4G7' );
		$options->set_option( 'general', 'sgtm_domain', '' );
		$options->set_option( 'general', 'google_tag_gateway', false );

		delete_option( GoogleTagGatewayHealth::OPTION );
		delete_option( GoogleTagGatewayHealth::NOTICE_DISMISSED_OPTION );
		delete_option( GoogleTagGatewayHealth::BLOCKED_NOTICE_DISMISSED_OPTION );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->handler = NotificationsHandler::get();
		$this->handler->setup_current_notifications();
		$this->handler->remove_notification_by_id( GoogleTagGatewayNotice::NOTIFICATION_ID );
		$this->handler->remove_notification_by_id( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID );
	}

	/**
	 * Store a health result.
	 *
	 * @param bool $passing Whether both checks passed.
	 *
	 * @return void
	 */
	private function store_health( bool $passing ): void {
		update_option(
			GoogleTagGatewayHealth::OPTION,
			[
				'schema'  => GoogleTagGatewayHealth::SCHEMA_VERSION,
				'service' => $passing,
				'script'  => $passing,
				'checked' => time(),
			],
			false
		);
	}

	/**
	 * Switch the gateway on, with or without a custom sGTM domain.
	 *
	 * The stored value is written directly: the enable gate's network check is
	 * not what these tests are about.
	 *
	 * @param string $sgtm_domain The custom sGTM domain, or an empty string.
	 *
	 * @return void
	 */
	private function switch_on( string $sgtm_domain = '' ): void {
		$options = OptionsFactory::get_instance();
		$options->set_option( 'general', 'sgtm_domain', $sgtm_domain );
		$options->set_option( 'general', 'google_tag_gateway', true );
	}

	/**
	 * Evaluate the notices against the current state.
	 *
	 * @return void
	 */
	private function evaluate(): void {
		( new GoogleTagGatewayNotice( new GoogleTagGateway( OptionsFactory::get_instance() ), $this->handler ) )->evaluate();
	}

	/**
	 * Whether a notice is currently in the set.
	 *
	 * @param string $id The notification id.
	 *
	 * @return bool
	 */
	private function is_shown( string $id ): bool {
		return null !== $this->handler->get_notification_by_id( $id );
	}

	/**
	 * A custom domain with passing checks is a conflict, not a failure.
	 *
	 * @return void
	 */
	public function test_a_custom_domain_gets_the_conflict_notice_and_not_the_fallback_one(): void {
		$this->store_health( true );
		$this->switch_on( 'data.example.com' );

		$gateway = new GoogleTagGateway( OptionsFactory::get_instance() );
		$this->assertFalse( $gateway->is_falling_back() );
		$this->assertTrue( $gateway->is_blocked_by_configuration() );

		$this->evaluate();

		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );
		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::NOTIFICATION_ID ) );
	}

	/**
	 * A custom domain with failing checks is still reported as the conflict.
	 *
	 * @return void
	 */
	public function test_a_custom_domain_is_reported_as_the_conflict_even_when_the_checks_fail(): void {
		$this->store_health( false );
		$this->switch_on( 'data.example.com' );

		$this->evaluate();

		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );
		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::NOTIFICATION_ID ) );
	}

	/**
	 * Failing checks without a custom domain keep the fallback notice.
	 *
	 * @return void
	 */
	public function test_failing_checks_without_a_custom_domain_get_the_fallback_notice(): void {
		$this->store_health( false );
		$this->switch_on();

		$this->evaluate();

		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::NOTIFICATION_ID ) );
		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );
	}

	/**
	 * A working gateway raises neither notice.
	 *
	 * @return void
	 */
	public function test_a_working_gateway_raises_neither_notice(): void {
		$this->store_health( true );
		$this->switch_on();

		$this->evaluate();

		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::NOTIFICATION_ID ) );
		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );
	}

	/**
	 * Dismissing the fallback notice does not silence the conflict notice, and the other way round.
	 *
	 * @return void
	 */
	public function test_each_dismissal_silences_only_its_own_notice(): void {
		GoogleTagGatewayHealth::dismiss_notice( GoogleTagGatewayHealth::NOTICE_FALLBACK );
		$this->store_health( true );
		$this->switch_on( 'data.example.com' );

		$this->evaluate();

		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );

		GoogleTagGatewayHealth::dismiss_notice( GoogleTagGatewayHealth::NOTICE_BLOCKED );
		delete_option( GoogleTagGatewayHealth::NOTICE_DISMISSED_OPTION );
		$this->store_health( false );
		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', '' );

		$this->evaluate();

		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::NOTIFICATION_ID ) );
	}

	/**
	 * The conflict dismissal lasts only while the conflict does.
	 *
	 * @return void
	 */
	public function test_the_conflict_notice_returns_after_the_conflict_was_resolved_and_came_back(): void {
		$this->store_health( true );
		$this->switch_on( 'data.example.com' );
		GoogleTagGatewayHealth::dismiss_notice( GoogleTagGatewayHealth::NOTICE_BLOCKED );

		$this->evaluate();
		$this->assertFalse( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', '' );
		$this->evaluate();
		$this->assertFalse( GoogleTagGatewayHealth::is_notice_dismissed( GoogleTagGatewayHealth::NOTICE_BLOCKED ) );

		OptionsFactory::get_instance()->set_option( 'general', 'sgtm_domain', 'data.example.com' );
		$this->evaluate();
		$this->assertTrue( $this->is_shown( GoogleTagGatewayNotice::BLOCKED_NOTIFICATION_ID ) );
	}
}
