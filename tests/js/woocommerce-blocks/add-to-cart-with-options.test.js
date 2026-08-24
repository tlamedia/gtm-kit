// @vitest-environment jsdom
/**
 * Add to Cart + Options block: add_to_cart on the in-place add.
 *
 * The block only renders the legacy `form.cart` when a third party forces
 * it to; left alone it keeps the Interactivity API and renders a form with
 * no `cart` class at all, which the classic single-product reader cannot
 * match on. These tests pin the block form to this subscriber, the legacy
 * form to the classic script, and the registry hand-off that stops the
 * cart-store diff reporting the same add a second time.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createAddToCartWithOptionsSubscriber } from '../../../src/js/frontend/woocommerce-blocks/blocks/add-to-cart-with-options.js';
import {
	claimClickAdd,
	clearClickAdds,
} from '../../../src/js/frontend/woocommerce-blocks/click-add-registry.js';
import { installSeam } from './helpers.js';

const ITEM = { item_id: 'SKU-1', item_name: 'Beanie', price: 12.5 };

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

/**
 * Render the block's form.
 *
 * @param {Object}  [options]             Overrides.
 * @param {boolean} [options.interactive] Interactivity path (the default) or the legacy fallback.
 * @param {string}  [options.carrier]     'span', 'input' or 'none'.
 * @param {string}  [options.quantity]    Value of the quantity field.
 * @return {HTMLFormElement} The rendered form.
 */
const renderBlockForm = ( {
	interactive = true,
	carrier = 'span',
	quantity = '1',
} = {} ) => {
	clearBody();

	const form = document.createElement( 'form' );
	form.className =
		'wp-block-woocommerce-add-to-cart-with-options wp-block-add-to-cart-with-options wc-block-add-to-cart-with-options';

	if ( interactive ) {
		form.setAttribute( 'data-wp-on--submit', 'actions.addToCart' );
	} else {
		// The fallback WooCommerce renders when a form element reaches the
		// add-to-cart hooks: a posting form carrying the classic class.
		form.classList.add( 'cart' );
		form.setAttribute( 'method', 'post' );
	}

	const payload = JSON.stringify( ITEM );

	if ( carrier === 'span' ) {
		const span = document.createElement( 'span' );
		span.className = 'gtmkit_single_product_data';
		span.setAttribute( 'data-gtmkit_product_id', '29' );
		span.setAttribute( 'data-gtmkit_product_data', payload );
		form.appendChild( span );
	}

	if ( carrier === 'input' ) {
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = 'gtmkit_product_data';
		input.value = payload;
		form.appendChild( input );
	}

	const qty = document.createElement( 'input' );
	qty.name = 'quantity';
	qty.value = quantity;
	form.appendChild( qty );

	document.body.appendChild( form );

	return form;
};

// JSDOM does not submit forms, so the event the block binds its add action
// to is dispatched directly.
const submit = ( form ) =>
	form.dispatchEvent(
		new window.Event( 'submit', { bubbles: true, cancelable: true } )
	);

describe( 'add to cart with options block', () => {
	let seam;
	let detach;

	beforeEach( () => {
		seam = installSeam();
		clearClickAdds();
	} );

	afterEach( () => {
		if ( detach ) {
			detach();
			detach = undefined;
		}
		clearClickAdds();
		clearBody();
	} );

	it( 'reports the in-place add the classic reader cannot see', () => {
		const form = renderBlockForm( { quantity: '2' } );
		detach = createAddToCartWithOptionsSubscriber();

		submit( form );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'add_to_cart' );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( 'SKU-1' );
		expect( events[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 2 );
		expect( events[ 0 ].ecommerce.value ).toBe( 25 );
		expect( events[ 0 ].ecommerce.currency ).toBe( 'USD' );
	} );

	it( 'reads the payload from a cached page carrying the old input', () => {
		const form = renderBlockForm( { carrier: 'input' } );
		detach = createAddToCartWithOptionsSubscriber();

		submit( form );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].ecommerce.items[ 0 ].item_id ).toBe( 'SKU-1' );
	} );

	it( 'leaves the legacy fallback to the classic script', () => {
		const form = renderBlockForm( { interactive: false } );
		detach = createAddToCartWithOptionsSubscriber();

		submit( form );

		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'hands the units to the registry so the cart diff subtracts them', () => {
		const form = renderBlockForm( { quantity: '3' } );
		detach = createAddToCartWithOptionsSubscriber();

		submit( form );

		// What the cart subscriber will subtract when the same add reaches
		// it through the cart diff. Without this the add is reported twice.
		expect( claimClickAdd( 'SKU-1', 3 ) ).toBe( 3 );
	} );

	it( 'defaults to a single unit when the form has no quantity field', () => {
		const form = renderBlockForm();
		form.querySelector( '[name="quantity"]' ).remove();
		detach = createAddToCartWithOptionsSubscriber();

		submit( form );

		expect( seam.events()[ 0 ].ecommerce.items[ 0 ].quantity ).toBe( 1 );
	} );

	it( 'stays silent and does not throw when the page carries no payload', () => {
		const form = renderBlockForm( { carrier: 'none' } );
		detach = createAddToCartWithOptionsSubscriber();

		expect( () => submit( form ) ).not.toThrow();
		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'ignores a submit from any other form on the page', () => {
		renderBlockForm();
		const other = document.createElement( 'form' );
		document.body.appendChild( other );
		detach = createAddToCartWithOptionsSubscriber();

		submit( other );

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
