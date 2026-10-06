<?php
/**
 * Unit tests for separating a purchase's contact details from the event.
 *
 * Target: {@see \TLA_Media\GTM_Kit\Frontend\ConsentGatedData}.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Frontend;

use TLA_Media\GTM_Kit\Frontend\ConsentGatedData;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Contact details leave the purchase event; everything else stays.
 */
final class ConsentGatedDataTest extends TestCase {

	/**
	 * A purchase carrying every contact field GTM Kit writes.
	 *
	 * @return array<string, mixed>
	 */
	private function purchase(): array {
		return [
			'event'     => 'purchase',
			'ecommerce' => [
				'transaction_id' => '837',
				'value'          => 25,
				'customer'       => [
					'id'                 => 1,
					'order_count'        => 2,
					'total_spent'        => 50.0,
					'customer_type'      => 'returning',
					'first_name'         => 'Ada',
					'billing_email'      => 'ada@example.test',
					'billing_email_hash' => 'hash',
					'billing_phone'      => '12345678',
					'billing_address_1'  => 'Street 1',
					'billing_country'    => 'DK',
					'shipping_firstName' => 'Ada',
					'shipping_city'      => 'Aarhus',
				],
			],
			'user_data' => [
				'sha256_email_address' => 'hashed',
				'address'              => [
					'sha256_first_name' => 'hashed',
					'city'              => 'Aarhus',
				],
			],
		];
	}

	/**
	 * Hashed and plain contact details are held; the id, order count, total spent and anything else stay.
	 *
	 * @return void
	 */
	public function test_contact_details_are_held_and_the_rest_stays(): void {
		list( $event, $held ) = ConsentGatedData::split( $this->purchase() );

		$this->assertArrayNotHasKey( 'user_data', $event );
		$this->assertSame(
			[
				'id'            => 1,
				'order_count'   => 2,
				'total_spent'   => 50.0,
				'customer_type' => 'returning',
			],
			$event['ecommerce']['customer']
		);
		$this->assertSame( '837', $event['ecommerce']['transaction_id'] );

		$this->assertSame( $this->purchase()['user_data'], $held['user_data'] );
		$this->assertSame( 'ada@example.test', $held['ecommerce']['customer']['billing_email'] );
		$this->assertSame( 'hash', $held['ecommerce']['customer']['billing_email_hash'] );
		$this->assertSame( 'Aarhus', $held['ecommerce']['customer']['shipping_city'] );
		$this->assertArrayNotHasKey( 'id', $held['ecommerce']['customer'] );
	}

	/**
	 * The event and the held details together are the original purchase.
	 *
	 * @return void
	 */
	public function test_nothing_is_lost_in_the_split(): void {
		list( $event, $held ) = ConsentGatedData::split( $this->purchase() );

		$this->assertEquals( $this->purchase(), array_replace_recursive( $event, $held ) );
	}

	/**
	 * A purchase without customer details holds nothing.
	 *
	 * @return void
	 */
	public function test_a_purchase_without_contact_details_holds_nothing(): void {
		$purchase = [
			'event'     => 'purchase',
			'ecommerce' => [ 'transaction_id' => '837' ],
		];

		$this->assertSame( [ $purchase, [] ], ConsentGatedData::split( $purchase ) );
	}

	/**
	 * Any other event is left alone, even when it carries user data.
	 *
	 * @return void
	 */
	public function test_other_events_are_left_alone(): void {
		$page = [
			'event'     => 'view_item',
			'user_data' => [ 'sha256_email_address' => 'hashed' ],
		];

		$this->assertSame( [ $page, [] ], ConsentGatedData::split( $page ) );
	}
}
