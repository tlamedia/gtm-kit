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
import {
	installCartSnapshot,
	resetCartSnapshot,
} from '../../../src/js/frontend/woocommerce-blocks/cart-snapshot.js';
import { installSeam, fakeData, flushMicrotasks } from './helpers.js';

const item = ( key, quantity ) => ( {
	key,
	quantity,
	prices: { sale_price: '1000' },
	extensions: {
		gtmkit: { item: JSON.stringify( { item_id: key, price: 10 } ) },
	},
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
		resetCartSnapshot();
		store = cartStore( [] );
		data = fakeData( { 'wc/store/cart': store } );
		createCartSubscriber( {
			select: data.select,
			subscribe: data.subscribe,
		} );
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

		const later = seam
			.events()
			.filter( ( e ) => e.event === 'add_to_cart' );
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

	it( 'records the baseline and every reported cart on the shared snapshot', async () => {
		const snapshot = installCartSnapshot();
		expect( snapshot.get() ).toBeNull();

		setItems( [ item( 'a', 1 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();
		expect( snapshot.get().map( ( i ) => [ i.key, i.quantity ] ) ).toEqual(
			[ [ 'a', 1 ] ]
		);

		setItems( [ item( 'a', 3 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( snapshot.get().map( ( i ) => [ i.key, i.quantity ] ) ).toEqual(
			[ [ 'a', 3 ] ]
		);
	} );

	it( 'stays silent when another path already reported the change through the shared snapshot', async () => {
		const snapshot = installCartSnapshot();
		data.notify(); // baseline (empty)
		await flushMicrotasks();

		// Another reporter saw the add first and recorded the new cart.
		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 10,
				item: { item_id: 'a', price: 10 },
			},
		] );

		setItems( [ item( 'a', 2 ) ] );
		data.notify();
		await flushMicrotasks();

		expect( seam.events() ).toHaveLength( 0 );

		// A later change still reports, against the shared baseline.
		setItems( [ item( 'a', 3 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'reports nothing while the store lags behind a change another path reported', async () => {
		const snapshot = installCartSnapshot();
		setItems( [ item( 'a', 1 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		// Another reporter saw an add the store has not received yet.
		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 10,
				item: { item_id: 'a', price: 10 },
			},
		] );

		// An unrelated store change notifies while the cart store still
		// holds the old cart.
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );
		expect( snapshot.get()[ 0 ].quantity ).toBe( 2 );

		// The store catches up: the change was already reported.
		setItems( [ item( 'a', 2 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );

		// A change made through the store still reports, against the shared
		// snapshot.
		setItems( [ item( 'a', 3 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].event ).toBe( 'add_to_cart' );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'reports a store change against the shared snapshot while the store lags behind it', async () => {
		const snapshot = installCartSnapshot();
		setItems( [ item( 'a', 1 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 10,
				item: { item_id: 'a', price: 10 },
			},
		] );

		// The visitor raises the quantity in the block to 3 before the
		// store received the other path's change: one unit is new.
		setItems( [ item( 'a', 3 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'reports a removal at the price of the last store read after a price-only change', async () => {
		const priced = ( key, quantity, price ) => ( {
			key,
			quantity,
			prices: { sale_price: String( price * 100 ) },
			extensions: {
				gtmkit: { item: JSON.stringify( { item_id: key, price } ) },
			},
		} );

		setItems( [ priced( 'a', 1, 10 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		setItems( [ priced( 'a', 1, 12 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );

		setItems( [] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].event ).toBe( 'remove_from_cart' );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].price ).toBe( 12 );
		expect( seam.events()[ 0 ].ecommerce.value ).toBe( 12 );
	} );

	it( 'refreshes the item data without touching a quantity another path recorded', async () => {
		const snapshot = installCartSnapshot();
		const priced = ( key, quantity, price ) => ( {
			key,
			quantity,
			prices: { sale_price: String( price * 100 ) },
			extensions: {
				gtmkit: { item: JSON.stringify( { item_id: key, price } ) },
			},
		} );

		setItems( [ priced( 'a', 1, 10 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		// Another reporter recorded an add the store has not received yet.
		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 10,
				item: { item_id: 'a', price: 10 },
			},
		] );

		// The stale store refreshes its prices only.
		setItems( [ priced( 'a', 1, 12 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );
		expect( snapshot.get()[ 0 ].quantity ).toBe( 2 );
		expect( snapshot.get()[ 0 ].item.price ).toBe( 12 );

		// The store catches up: nothing new to report.
		setItems( [ priced( 'a', 2, 12 ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'keeps the item data another path recorded while the store lags behind it', async () => {
		const snapshot = installCartSnapshot();
		const priced = ( key, quantity, price ) => ( {
			key,
			quantity,
			prices: { sale_price: String( price * 100 ) },
			extensions: {
				gtmkit: { item: JSON.stringify( { item_id: key, price } ) },
			},
		} );

		setItems( [ priced( 'a', 1, 10 ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		// Another reporter recorded a quantity change that also moved the
		// unit price, which the store has not received yet.
		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 12,
				item: { item_id: 'a', price: 12 },
			},
		] );

		// An unrelated notification while the store still holds the old cart.
		data.notify();
		await flushMicrotasks();
		expect( snapshot.get()[ 0 ].item.price ).toBe( 12 );
		expect( snapshot.get()[ 0 ].unitPrice ).toBe( 12 );

		setItems( [] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].event ).toBe( 'remove_from_cart' );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].price ).toBe( 12 );
		expect( seam.events()[ 0 ].ecommerce.value ).toBe( 24 );
	} );

	it( 'keeps a newer price another path recorded when the lagging store changes another field', async () => {
		const snapshot = installCartSnapshot();
		const line = ( quantity, price, name ) => ( {
			key: 'a',
			quantity,
			prices: { sale_price: String( price * 100 ) },
			extensions: {
				gtmkit: {
					item: JSON.stringify( {
						item_id: 'a',
						item_name: name,
						price,
					} ),
				},
			},
		} );

		setItems( [ line( 1, 10, 'Old name' ) ] );
		data.notify(); // baseline
		await flushMicrotasks();

		snapshot.set( [
			{
				key: 'a',
				quantity: 2,
				unitPrice: 12,
				item: { item_id: 'a', item_name: 'Old name', price: 12 },
			},
		] );

		// The lagging store changes the name only.
		setItems( [ line( 1, 10, 'New name' ) ] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );
		expect( snapshot.get()[ 0 ] ).toMatchObject( {
			quantity: 2,
			unitPrice: 12,
			item: { item_name: 'New name', price: 12 },
		} );

		setItems( [] );
		data.notify();
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 1 );
		expect( seam.events()[ 0 ].event ).toBe( 'remove_from_cart' );
		expect( seam.events()[ 0 ].ecommerce.items[ 0 ] ).toMatchObject( {
			item_name: 'New name',
			price: 12,
			quantity: 2,
		} );
		expect( seam.events()[ 0 ].ecommerce.value ).toBe( 24 );
	} );

	it( 'treats a snapshot set before the first resolution as the baseline', async () => {
		const snapshot = installCartSnapshot();
		snapshot.set( [
			{
				key: 'a',
				quantity: 1,
				unitPrice: 10,
				item: { item_id: 'a', price: 10 },
			},
		] );

		setItems( [ item( 'a', 1 ) ] );
		data.notify(); // first resolution matches the snapshot: nothing to report
		await flushMicrotasks();
		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
