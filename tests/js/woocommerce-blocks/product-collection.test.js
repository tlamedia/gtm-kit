// @vitest-environment jsdom
/**
 * Product Collection: view_item_list on render + re-fire + select_item.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createProductCollectionSubscriber } from '../../../src/js/frontend/woocommerce-blocks/blocks/product-collection.js';
import { installSeam } from './helpers.js';

let observerCallback = null;

const fakeObserverFactory = ( cb ) => {
	observerCallback = cb;
	return { observe: () => {}, disconnect: () => {} };
};

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

const buildCollection = ( ids ) => {
	clearBody();
	const root = document.createElement( 'div' );
	root.className = 'wp-block-woocommerce-product-collection';
	for ( const id of ids ) {
		const li = document.createElement( 'li' );
		li.className = `post-${ id } product`;
		const span = document.createElement( 'span' );
		span.className = 'gtmkit_block_product_data';
		span.setAttribute(
			'data-gtmkit_product_data',
			JSON.stringify( { item_id: String( id ), price: 10 } )
		);
		const anchor = document.createElement( 'a' );
		anchor.href = `#product-${ id }`;
		anchor.textContent = `Product ${ id }`;
		const addButton = document.createElement( 'button' );
		addButton.className = 'add_to_cart_button';
		addButton.textContent = 'Add to cart';
		li.appendChild( span );
		li.appendChild( anchor );
		li.appendChild( addButton );
		root.appendChild( li );
	}
	document.body.appendChild( root );
	return root;
};

describe( 'product collection', () => {
	let seam;
	let root;
	let detach;

	beforeEach( () => {
		seam = installSeam();
		observerCallback = null;
		root = buildCollection( [ 1, 2 ] );
		detach = createProductCollectionSubscriber( {
			observerFactory: fakeObserverFactory,
		} );
	} );

	afterEach( () => {
		if ( detach ) detach();
	} );

	it( 'emits one view_item_list for the rendered set with list name and index', () => {
		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'view_item_list' );
		expect( events[ 0 ].ecommerce.items ).toHaveLength( 2 );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'Product Collection'
		);
		expect( events[ 0 ].ecommerce.items[ 0 ].index ).toBe( 1 );
	} );

	it( 're-fires view_item_list when the product set changes', () => {
		// Simulate a filter re-render swapping the products.
		const li = document.createElement( 'li' );
		li.className = 'post-3 product';
		const span = document.createElement( 'span' );
		span.className = 'gtmkit_block_product_data';
		span.setAttribute(
			'data-gtmkit_product_data',
			JSON.stringify( { item_id: '3', price: 10 } )
		);
		li.appendChild( span );
		root.appendChild( li );

		observerCallback();

		expect( seam.events() ).toHaveLength( 2 );
		expect( seam.events()[ 1 ].ecommerce.items ).toHaveLength( 3 );
	} );

	it( 'does not re-fire for an unchanged product set', () => {
		observerCallback();

		expect( seam.events() ).toHaveLength( 1 );
	} );

	it( 'emits select_item on a product click with the list name', () => {
		const anchor = document.querySelector(
			'.wp-block-woocommerce-product-collection a'
		);
		anchor.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		const selects = seam.events().filter( ( e ) => e.event === 'select_item' );
		expect( selects ).toHaveLength( 1 );
		expect( selects[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '1' );
		expect( selects[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'Product Collection'
		);
	} );

	it( 'emits add_to_cart with the list name when a collection add button is clicked', () => {
		const addButton = document.querySelector(
			'.wp-block-woocommerce-product-collection .add_to_cart_button'
		);
		addButton.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);

		const adds = seam.events().filter( ( e ) => e.event === 'add_to_cart' );
		expect( adds ).toHaveLength( 1 );
		expect( adds[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '1' );
		expect( adds[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
		expect( adds[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'Product Collection'
		);
		expect( adds[ 0 ].ecommerce.value ).toBe( 10 );
	} );

	it( 'does not emit add_to_cart for a block component button (cart subscriber owns it)', () => {
		// The block product button routes the add through the Store API, so
		// the cart-store subscriber emits add_to_cart for it. This module must
		// stay silent for that button or the event would be double-counted.
		const li = document.querySelector(
			'.wp-block-woocommerce-product-collection li'
		);
		const blockButton = document.createElement( 'button' );
		blockButton.className = 'wc-block-components-product-button__button';
		blockButton.textContent = 'Add to cart';
		li.appendChild( blockButton );

		blockButton.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);

		const adds = seam.events().filter( ( e ) => e.event === 'add_to_cart' );
		expect( adds ).toHaveLength( 0 );
	} );
} );
