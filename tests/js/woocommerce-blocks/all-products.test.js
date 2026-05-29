// @vitest-environment jsdom
/**
 * All Products block: view_item_list / select_item from the block's
 * render action.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { doAction, removeAllActions } from '@wordpress/hooks';
import { initAllProducts } from '../../../src/js/frontend/woocommerce-blocks/blocks/all-products.js';
import { installSeam } from './helpers.js';

const LIST_RENDER = 'experimental__woocommerce_blocks-product-list-render';
const VIEW_LINK = 'experimental__woocommerce_blocks-product-view-link';

const product = ( id ) => ( {
	extensions: { gtmkit: { item: { item_id: id, item_name: `P${ id }`, price: 10 } } },
} );

describe( 'all products', () => {
	let seam;

	beforeEach( () => {
		seam = installSeam();
		initAllProducts();
	} );

	afterEach( () => {
		removeAllActions( LIST_RENDER );
		removeAllActions( VIEW_LINK );
	} );

	it( 'emits view_item_list with indexed items and the list name', () => {
		doAction( LIST_RENDER, {
			products: [ product( '1' ), product( '2' ) ],
			listName: 'All products',
		} );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'view_item_list' );
		expect( events[ 0 ].ecommerce.items ).toHaveLength( 2 );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'All products'
		);
		expect( events[ 0 ].ecommerce.items[ 0 ].index ).toBe( 1 );
	} );

	it( 'does not re-fire for an identical product set', () => {
		const products = [ product( '1' ), product( '2' ) ];
		doAction( LIST_RENDER, { products, listName: 'All products' } );
		doAction( LIST_RENDER, { products, listName: 'All products' } );

		expect( seam.events() ).toHaveLength( 1 );
	} );

	it( 're-fires when the product set changes (pagination / filter)', () => {
		doAction( LIST_RENDER, {
			products: [ product( '1' ) ],
			listName: 'All products',
		} );
		doAction( LIST_RENDER, {
			products: [ product( '3' ), product( '4' ) ],
			listName: 'All products',
		} );

		expect( seam.events() ).toHaveLength( 2 );
		expect( seam.events()[ 1 ].ecommerce.items[ 0 ].item_id ).toBe( '3' );
	} );

	it( 'ignores an empty product set', () => {
		doAction( LIST_RENDER, { products: [], listName: 'All products' } );
		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'emits select_item on a product view link', () => {
		doAction( VIEW_LINK, { product: product( '7' ), listName: 'All products' } );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'select_item' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '7' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_list_name ).toBe(
			'All products'
		);
	} );
} );
