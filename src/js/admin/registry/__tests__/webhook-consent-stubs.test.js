/**
 * Asserts the server-side consent gating settings ship core-side upsell
 * stubs, so a site without GTM Kit Premium still renders the locked rows in
 * the Webhooks section instead of the fields silently disappearing.
 */

import STUB_FIELDS from '../fields/stubs';

const CONSENT_GATE_KEYS = [
	'premium.woocommerce_webhook_consent_mode',
	'premium.woocommerce_webhook_consent_unknown',
];

describe( 'stubs: server-side consent gating settings', () => {
	it.each( CONSENT_GATE_KEYS )( '%s has a locked stub row', ( key ) => {
		const field = STUB_FIELDS.find( ( f ) => f.key === key );

		expect( field ).toBeDefined();
		expect( field.stub ).toBe( true );
		expect( field.section ).toBe( 'woo-webhooks' );
		expect( field.control ).toBe( 'radio' );
		expect( field.valueType ).toBe( 'string' );
		expect( field.tier ).toBe( 'premium' );
	} );

	it( 'offers the three gating modes with string values', () => {
		const mode = STUB_FIELDS.find(
			( f ) => f.key === 'premium.woocommerce_webhook_consent_mode'
		);

		expect( mode.options.map( ( o ) => o.value ) ).toEqual( [
			'off',
			'suppress',
			'anonymize',
		] );
	} );

	it( 'orders the stubs after the subscription webhook rows', () => {
		const reactivated = STUB_FIELDS.find(
			( f ) =>
				f.key === 'premium.woocommerce_subscription_reactivated_webhook'
		).order;

		CONSENT_GATE_KEYS.forEach( ( key ) => {
			const order = STUB_FIELDS.find( ( f ) => f.key === key ).order;
			expect( order ).toBeGreaterThan( reactivated );
		} );
	} );
} );
