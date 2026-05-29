// @vitest-environment jsdom
/**
 * Single Product block: view_item fallback that does not duplicate the
 * PHP-emitted view_item.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import { initSingleProductBlock } from '../../../src/js/frontend/woocommerce-blocks/blocks/single-product-block.js';
import { installSeam } from './helpers.js';

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

const buildSingleProduct = () => {
	clearBody();
	const block = document.createElement( 'div' );
	block.className = 'wp-block-woocommerce-single-product';
	const span = document.createElement( 'span' );
	span.className = 'gtmkit_block_product_data';
	span.setAttribute(
		'data-gtmkit_product_data',
		JSON.stringify( { item_id: '42', item_name: 'Thing', price: 12.5 } )
	);
	block.appendChild( span );
	document.body.appendChild( block );
};

describe( 'single product block', () => {
	let seam;

	beforeEach( () => {
		seam = installSeam();
		buildSingleProduct();
	} );

	it( 'emits view_item from the block when none has fired', () => {
		initSingleProductBlock();

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'view_item' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( '42' );
		expect( events[ 0 ].ecommerce.value ).toBe( 12.5 );
	} );

	it( 'does not duplicate when a view_item is already on the dataLayer', () => {
		window.dataLayer.push( { event: 'view_item', ecommerce: {} } );

		initSingleProductBlock();

		expect(
			seam.events().filter( ( e ) => e.event === 'view_item' )
		).toHaveLength( 1 );
	} );

	it( 'no-ops when the Single Product block is absent', () => {
		clearBody();
		initSingleProductBlock();

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
