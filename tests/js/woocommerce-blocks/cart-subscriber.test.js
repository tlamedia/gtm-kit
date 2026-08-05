// @vitest-environment jsdom
/**
 * Cart subscriber: add_to_cart / remove_from_cart from store diffs.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import { createCartSubscriber } from '../../../src/js/frontend/woocommerce-blocks/stores/cart-subscriber.js';
import {
	recordClickAdd,
	clearClickAdds,
} from '../../../src/js/frontend/woocommerce-blocks/click-add-registry.js';
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
		clearClickAdds();
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

	it( 'suppresses the diff add a click handler already reported', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		recordClickAdd( 'a', 1 );

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		expect(
			seam.events().filter( ( e ) => e.event === 'add_to_cart' )
		).toHaveLength( 0 );

		// A later add of the same item from another surface still emits:
		// the registry entry was claimed by the first diff.
		setItems( [ item( 'a', 2 ) ] );
		data.notify();
		await flushMicrotasks();

		const later = seam.events().filter( ( e ) => e.event === 'add_to_cart' );
		expect( later ).toHaveLength( 1 );
		expect( later[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'emits only the remainder when the diff carries more units than the click reported', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		recordClickAdd( 'a', 1 );

		// The diff arrives as +3 (e.g. a quantity stepper on top of the
		// click): the click's one unit is subtracted, the rest emits.
		setItems( [ item( 'a', 3 ) ] );
		data.notify();
		await flushMicrotasks();

		const adds = seam.events().filter( ( e ) => e.event === 'add_to_cart' );
		expect( adds ).toHaveLength( 1 );
		expect( adds[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 2 );
		expect( adds[ 0 ].ecommerce.value ).toBe( 20 );
	} );

	it( 'an add without a registry entry emits unchanged', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		const adds = seam.events().filter( ( e ) => e.event === 'add_to_cart' );
		expect( adds ).toHaveLength( 1 );
		expect( adds[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'remove_from_cart is unaffected by the registry', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		recordClickAdd( 'a', 1 );

		setItems( [] );
		data.notify();
		await flushMicrotasks();

		const removes = seam
			.events()
			.filter( ( e ) => e.event === 'remove_from_cart' );
		expect( removes ).toHaveLength( 1 );
	} );

	it( 'suppresses a click-reported add across cart diffs that confirm it one unit at a time', async () => {
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		// The click handler already emitted both adds itself.
		recordClickAdd( 'a', 1 );
		recordClickAdd( 'a', 1 );

		setItems( [ item( 'a', 1 ) ] );
		data.notify();
		await flushMicrotasks();

		setItems( [ item( 'a', 2 ) ] );
		data.notify();
		await flushMicrotasks();

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
