// @vitest-environment jsdom
/**
 * Cart cross-sells: view_item_list from the cart store's crossSells,
 * select_item on a click matching a cross-sell permalink.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createCrossSellsSubscriber } from '../../../src/js/frontend/woocommerce-blocks/blocks/cart-cross-sells.js';
import { installSeam, fakeData } from './helpers.js';

const crossSell = ( id, permalink ) => ( {
	id,
	permalink,
	name: `Product ${ id }`,
	extensions: {
		gtmkit: { item: { item_id: String( id ), item_name: `Product ${ id }`, price: 16 } },
	},
} );

const cartStore = ( crossSells ) => ( {
	getCartData: () => ( { items: [], crossSells } ),
} );

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

describe( 'cart cross-sells', () => {
	let seam;
	let data;
	let detach;

	beforeEach( () => {
		seam = installSeam();
		clearBody();
		data = fakeData( {
			'wc/store/cart': cartStore( [
				crossSell( 11, 'https://example.test/product/cap/' ),
			] ),
		} );
		detach = createCrossSellsSubscriber( {
			select: data.select,
			subscribe: data.subscribe,
		} );
	} );

	afterEach( () => {
		if ( detach ) detach();
	} );

	it( 'emits view_item_list for cross-sells with the Cross-sells list name', () => {
		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'view_item_list' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '11' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'Cross-sells'
		);
		expect( events[ 0 ].ecommerce.items[ 0 ].index ).toBe( 1 );
	} );

	it( 'does not re-emit for an unchanged cross-sell set', () => {
		data.notify();
		expect( seam.events() ).toHaveLength( 1 );
	} );

	it( 'emits select_item when a link matching a cross-sell permalink is clicked', () => {
		const anchor = document.createElement( 'a' );
		anchor.href = 'https://example.test/product/cap/';
		anchor.textContent = 'Cap';
		document.body.appendChild( anchor );

		anchor.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		const selects = seam.events().filter( ( e ) => e.event === 'select_item' );
		expect( selects ).toHaveLength( 1 );
		expect( selects[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '11' );
		expect( selects[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'Cross-sells'
		);
	} );

	it( 'ignores clicks on links that are not cross-sells', () => {
		const anchor = document.createElement( 'a' );
		anchor.href = 'https://example.test/product/something-else/';
		document.body.appendChild( anchor );

		anchor.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect(
			seam.events().filter( ( e ) => e.event === 'select_item' )
		).toHaveLength( 0 );
	} );
} );
