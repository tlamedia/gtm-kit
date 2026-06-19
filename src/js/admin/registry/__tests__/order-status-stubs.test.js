/**
 * Asserts the order status webhook toggles ship core-side upsell stubs, so a
 * site without the add-on still renders the locked rows in the Webhooks
 * section instead of the fields silently disappearing.
 */

import STUB_FIELDS from '../fields/stubs';

const ORDER_STATUS_KEYS = [
	'premium.woocommerce_order_processing_webhook',
	'premium.woocommerce_order_completed_webhook',
	'premium.woocommerce_order_refunded_webhook',
];

describe( 'stubs: order status webhook toggles', () => {
	it.each( ORDER_STATUS_KEYS )( '%s has a locked stub row', ( key ) => {
		const field = STUB_FIELDS.find( ( f ) => f.key === key );

		expect( field ).toBeDefined();
		expect( field.stub ).toBe( true );
		expect( field.section ).toBe( 'woo-webhooks' );
		expect( field.control ).toBe( 'toggle' );
	} );

	it( 'orders the stubs between the refund and subscription rows', () => {
		const orderOf = ( key ) =>
			STUB_FIELDS.find( ( f ) => f.key === `premium.${ key }` ).order;

		const refund = orderOf( 'woocommerce_refund_webhook' );
		const started = orderOf( 'woocommerce_subscription_started_webhook' );

		ORDER_STATUS_KEYS.forEach( ( key ) => {
			const order = STUB_FIELDS.find( ( f ) => f.key === key ).order;
			expect( order ).toBeGreaterThan( refund );
			expect( order ).toBeLessThan( started );
		} );
	} );
} );
