// @vitest-environment jsdom
/**
 * Mini Cart: view_cart on open, sourced from the Store API (the WC 10.x
 * Mini Cart is an Interactivity API block with no wp.data cart store).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMiniCartSubscriber } from '../../../src/js/frontend/woocommerce-blocks/blocks/mini-cart.js';
import { installSeam } from './helpers.js';

const cartResponse = ( items, total = '2000' ) => ( {
	items,
	totals: { total_price: total },
} );

const cartItem = ( key, quantity ) => ( {
	key,
	quantity,
	extensions: { gtmkit: { item: { item_id: key, price: 10 } } },
} );

const buildMiniCartDom = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'wc-block-mini-cart';
	const button = document.createElement( 'button' );
	button.className = 'wc-block-mini-cart__button';
	button.textContent = 'Cart';
	wrapper.appendChild( button );
	document.body.appendChild( wrapper );
};

const clickMiniCart = () => {
	document
		.querySelector( '.wc-block-mini-cart__button' )
		.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
};

// The subscriber's open handler awaits fetch().json(); let those microtasks settle.
const settle = async () => {
	await Promise.resolve();
	await Promise.resolve();
	await Promise.resolve();
};

const mockCart = ( cart ) =>
	vi.fn().mockResolvedValue( {
		ok: true,
		json: () => Promise.resolve( cart ),
	} );

describe( 'mini cart', () => {
	let seam;
	let detach;

	beforeEach( () => {
		seam = installSeam();
		window.gtmkitWooCommerceBlocksBuild = {
			root: 'https://example.test/wp-json/',
			nonce: 'abc123',
		};
		buildMiniCartDom();
		detach = createMiniCartSubscriber();
	} );

	afterEach( () => {
		if ( detach ) detach();
		delete window.gtmkitWooCommerceBlocksBuild;
		vi.restoreAllMocks();
	} );

	it( 'emits view_cart with items and value on open', async () => {
		global.fetch = mockCart( cartResponse( [ cartItem( 'a', 1 ) ] ) );

		clickMiniCart();
		await settle();

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'view_cart' );
		expect( events[ 0 ].ecommerce.value ).toBe( 20 );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( 'a' );
		expect( events[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'does not re-emit when re-opened with an unchanged cart', async () => {
		global.fetch = mockCart( cartResponse( [ cartItem( 'a', 1 ) ] ) );

		clickMiniCart();
		await settle();
		clickMiniCart();
		await settle();

		expect( seam.events() ).toHaveLength( 1 );
	} );

	it( 're-emits after the cart contents change', async () => {
		global.fetch = mockCart( cartResponse( [ cartItem( 'a', 1 ) ] ) );
		clickMiniCart();
		await settle();

		global.fetch = mockCart(
			cartResponse( [ cartItem( 'a', 1 ), cartItem( 'b', 1 ) ] )
		);
		clickMiniCart();
		await settle();

		expect( seam.events() ).toHaveLength( 2 );
	} );

	it( 'does not emit when the cart is empty', async () => {
		global.fetch = mockCart( cartResponse( [] ) );

		clickMiniCart();
		await settle();

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
