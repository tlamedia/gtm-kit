// @vitest-environment jsdom
/**
 * Cart subscriber: add_to_cart / remove_from_cart from store diffs.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import { createCartSubscriber } from '../../../src/js/frontend/woocommerce-blocks/stores/cart-subscriber.js';
import { installSeam, fakeData, flushMicrotasks } from './helpers.js';

const item = ( key, quantity ) => ( {
	key,
	quantity,
	prices: { sale_price: '1000' },
	extensions: { gtmkit: { item: JSON.stringify( { item_id: key, price: 10 } ) } },
} );

const cartStore = ( items ) => ( {
	getCartData: () => ( { items } ),
	hasFinishedResolution: () => true,
} );

describe( 'cart subscriber', () => {
	let seam;
	let data;
	let store;

	beforeEach( () => {
		seam = installSeam();
		store = cartStore( [] );
		data = fakeData( { 'wc/store/cart': store } );
		createCartSubscriber( { select: data.select, subscribe: data.subscribe } );
	} );

	const setItems = ( items ) => {
		data.setStore( 'wc/store/cart', cartStore( items ) );
	};

	it( 'establishes a baseline without emitting for items present on load', async () => {
		data.setStore( 'wc/store/cart', cartStore( [ item( 'a', 1 ) ] ) );
		data.notify();
		await flushMicrotasks();

		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'emits add_to_cart with the delta quantity on an increase', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		setItems( [ item( 'a', 2 ) ] );
		data.notify();
		await flushMicrotasks();

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'add_to_cart' );
		expect( events[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 2 );
		expect( events[ 0 ].ecommerce.value ).toBe( 20 );
		expect( events[ 0 ].ecommerce.currency ).toBe( 'USD' );
	} );

	it( 'emits remove_from_cart on a decrease', async () => {
		setItems( [ item( 'a', 3 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'remove_from_cart' );
		expect( events[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 2 );
	} );

	it( 'does not emit a view_cart when the first item is added', async () => {
		data.notify(); // baseline empty
		await flushMicrotasks();

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		expect( seam.events().map( ( e ) => e.event ) ).toEqual( [
			'add_to_cart',
		] );
	} );

	it( 'collapses a burst of synchronous notifications into one diff', async () => {
		data.notify(); // baseline empty
		await flushMicrotasks();

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		data.notify();
		data.notify();
		await flushMicrotasks();

		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].event ).toBe( 'add_to_cart' );
	} );
} );
