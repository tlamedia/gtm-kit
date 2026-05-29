// @vitest-environment jsdom
/**
 * Shared block utility helpers.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import {
	pushEvent,
	parseItem,
	getCurrency,
	getProductImpressionObject,
	normalizeCartItems,
	cartSignature,
	microtaskQueue,
	shippingInfo,
	paymentInfo,
} from '../../../src/js/frontend/woocommerce-blocks/utils.js';
import { installSeam, flushMicrotasks } from './helpers.js';

describe( 'parseItem', () => {
	it( 'parses a JSON string payload', () => {
		expect( parseItem( '{"item_id":"a"}' ) ).toEqual( { item_id: 'a' } );
	} );

	it( 'clones an object payload', () => {
		const source = { item_id: 'a' };
		const parsed = parseItem( source );
		parsed.quantity = 2;
		expect( source.quantity ).toBeUndefined();
	} );

	it( 'returns an empty object for unparseable or missing input', () => {
		expect( parseItem( 'not json' ) ).toEqual( {} );
		expect( parseItem( undefined ) ).toEqual( {} );
	} );
} );

describe( 'getProductImpressionObject', () => {
	it( 'stamps the list name on the item', () => {
		const product = {
			extensions: { gtmkit: { item: { item_id: 'a' } } },
		};
		expect(
			getProductImpressionObject( product, 'Related products' )
		).toEqual( { item_id: 'a', item_list_name: 'Related products' } );
	} );
} );

describe( 'normalizeCartItems', () => {
	it( 'normalizes items with parsed payloads and minor-unit prices', () => {
		const result = normalizeCartItems( {
			items: [
				{
					key: 'k1',
					quantity: 2,
					prices: { sale_price: '1500' },
					extensions: { gtmkit: { item: JSON.stringify( { item_id: 'a' } ) } },
				},
			],
		} );

		expect( result ).toHaveLength( 1 );
		expect( result[ 0 ].key ).toBe( 'k1' );
		expect( result[ 0 ].quantity ).toBe( 2 );
		expect( result[ 0 ].unitPrice ).toBe( 15 );
		expect( result[ 0 ].item.item_id ).toBe( 'a' );
		expect( result[ 0 ].item.quantity ).toBe( 2 );
	} );

	it( 'returns an empty array for missing cart data', () => {
		expect( normalizeCartItems( null ) ).toEqual( [] );
		expect( normalizeCartItems( {} ) ).toEqual( [] );
	} );
} );

describe( 'cartSignature', () => {
	it( 'is stable regardless of item order', () => {
		const a = cartSignature( [ { key: 'x', quantity: 1 }, { key: 'y', quantity: 2 } ] );
		const b = cartSignature( [ { key: 'y', quantity: 2 }, { key: 'x', quantity: 1 } ] );
		expect( a ).toBe( b );
	} );

	it( 'changes when a quantity changes', () => {
		const a = cartSignature( [ { key: 'x', quantity: 1 } ] );
		const b = cartSignature( [ { key: 'x', quantity: 2 } ] );
		expect( a ).not.toBe( b );
	} );
} );

describe( 'microtaskQueue', () => {
	it( 'collapses multiple synchronous calls into one run', async () => {
		let runs = 0;
		const scheduled = microtaskQueue( () => runs++ );
		scheduled();
		scheduled();
		scheduled();
		expect( runs ).toBe( 0 );
		await flushMicrotasks();
		expect( runs ).toBe( 1 );
	} );
} );

describe( 'pushEvent + getCurrency', () => {
	let seam;
	beforeEach( () => {
		seam = installSeam();
	} );

	it( 'clears ecommerce then pushes the event through the seam', () => {
		pushEvent( 'add_to_cart', { ecommerce: { value: 5 } } );

		expect( window.dataLayer[ 0 ] ).toEqual( { ecommerce: null } );
		expect( window.dataLayer[ 1 ].event ).toBe( 'add_to_cart' );
		expect( seam.events() ).toHaveLength( 1 );
	} );

	it( 'reads the configured currency', () => {
		expect( getCurrency() ).toBe( 'USD' );
	} );
} );

describe( 'shippingInfo / paymentInfo guards', () => {
	beforeEach( () => {
		installSeam( {
			data: {
				wc: {
					currency: 'USD',
					cart_value: 20,
					cart_items: [ { item_id: 'a' } ],
					chosen_shipping_method: 'flat_rate:1',
					chosen_payment_method: 'cod',
					add_shipping_info: { fired: false },
					add_payment_info: { fired: false },
				},
			},
		} );
	} );

	it( 'emits add_shipping_info once and then guards', () => {
		shippingInfo();
		shippingInfo();

		const fired = window.dataLayer.filter(
			( e ) => e && e.event === 'add_shipping_info'
		);
		expect( fired ).toHaveLength( 1 );
		expect( fired[ 0 ].ecommerce.shipping_tier ).toBe( 'flat_rate:1' );
	} );

	it( 'emits add_payment_info once and then guards', () => {
		paymentInfo();
		paymentInfo();

		const fired = window.dataLayer.filter(
			( e ) => e && e.event === 'add_payment_info'
		);
		expect( fired ).toHaveLength( 1 );
		expect( fired[ 0 ].ecommerce.payment_type ).toBe( 'cod' );
	} );
} );
