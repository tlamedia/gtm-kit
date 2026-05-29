// @vitest-environment jsdom
/**
 * Checkout subscriber: add_shipping_info / add_payment_info parity with
 * the 0/1/2 admin toggles.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import { createCheckoutSubscriber } from '../../../src/js/frontend/woocommerce-blocks/stores/checkout-subscriber.js';
import { installSeam, fakeData, flushMicrotasks } from './helpers.js';

const cartStore = ( rateId = '' ) => ( {
	getCartData: () => ( {
		shippingRates: [
			{
				shipping_rates: [
					{ rate_id: 'flat_rate:1', selected: rateId === 'flat_rate:1' },
					{ rate_id: 'free_shipping:2', selected: rateId === 'free_shipping:2' },
				],
			},
		],
	} ),
} );

const paymentStore = ( method = '' ) => ( {
	getActivePaymentMethod: () => method,
} );

const checkoutStore = ( status = 'idle' ) => ( {
	getCheckoutStatus: () => status,
} );

const seed = ( shippingConfig, paymentConfig ) =>
	installSeam( {
		settings: {
			wc: {
				add_shipping_info: { config: shippingConfig },
				add_payment_info: { config: paymentConfig },
			},
		},
		data: {
			wc: {
				currency: 'USD',
				is_checkout: true,
				cart_value: 20,
				cart_items: [ { item_id: 'a' } ],
				chosen_shipping_method: '',
				chosen_payment_method: '',
				add_shipping_info: { fired: false },
				add_payment_info: { fired: false },
			},
		},
	} );

describe( 'checkout subscriber', () => {
	let seam;
	let data;

	const mount = () => {
		data = fakeData( {
			'wc/store/cart': cartStore(),
			'wc/store/payment': paymentStore(),
			'wc/store/checkout': checkoutStore(),
		} );
		createCheckoutSubscriber( {
			select: data.select,
			subscribe: data.subscribe,
		} );
	};

	beforeEach( () => {
		// Reset between tests is handled by seeding in each test.
	} );

	it( 'config 2: emits add_shipping_info when the rate is selected', async () => {
		seam = seed( 2, 0 );
		mount();

		data.setStore( 'wc/store/cart', cartStore( 'flat_rate:1' ) );
		data.notify();
		await flushMicrotasks();

		const events = seam.events();
		expect( events.map( ( e ) => e.event ) ).toContain( 'add_shipping_info' );
		const ship = events.find( ( e ) => e.event === 'add_shipping_info' );
		expect( ship.ecommerce.shipping_tier ).toBe( 'flat_rate:1' );
	} );

	it( 'config 1: does not emit on rate set, emits on submit', async () => {
		seam = seed( 1, 0 );
		mount();

		data.setStore( 'wc/store/cart', cartStore( 'flat_rate:1' ) );
		data.notify();
		await flushMicrotasks();
		expect(
			seam.events().map( ( e ) => e.event )
		).not.toContain( 'add_shipping_info' );

		data.setStore( 'wc/store/checkout', checkoutStore( 'processing' ) );
		data.notify();
		await flushMicrotasks();
		expect(
			seam.events().map( ( e ) => e.event )
		).toContain( 'add_shipping_info' );
	} );

	it( 'config 0: never emits add_shipping_info', async () => {
		seam = seed( 0, 0 );
		mount();

		data.setStore( 'wc/store/cart', cartStore( 'flat_rate:1' ) );
		data.notify();
		await flushMicrotasks();
		data.setStore( 'wc/store/checkout', checkoutStore( 'processing' ) );
		data.notify();
		await flushMicrotasks();

		expect(
			seam.events().map( ( e ) => e.event )
		).not.toContain( 'add_shipping_info' );
	} );

	it( 'config 2: emits add_payment_info when the method is selected', async () => {
		seam = seed( 0, 2 );
		mount();

		data.setStore( 'wc/store/payment', paymentStore( 'cod' ) );
		data.notify();
		await flushMicrotasks();

		const pay = seam.events().find( ( e ) => e.event === 'add_payment_info' );
		expect( pay ).toBeTruthy();
		expect( pay.ecommerce.payment_type ).toBe( 'cod' );
	} );

	it( 'submit emits each event at most once (fired guard)', async () => {
		seam = seed( 2, 2 );
		mount();

		data.setStore( 'wc/store/cart', cartStore( 'flat_rate:1' ) );
		data.setStore( 'wc/store/payment', paymentStore( 'cod' ) );
		data.notify();
		await flushMicrotasks();

		data.setStore( 'wc/store/checkout', checkoutStore( 'processing' ) );
		data.notify();
		await flushMicrotasks();

		const shipping = seam
			.events()
			.filter( ( e ) => e.event === 'add_shipping_info' );
		const payment = seam
			.events()
			.filter( ( e ) => e.event === 'add_payment_info' );
		expect( shipping ).toHaveLength( 1 );
		expect( payment ).toHaveLength( 1 );
	} );
} );
