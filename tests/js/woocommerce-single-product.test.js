// @vitest-environment jsdom
/**
 * Vitest coverage for the single-product readers in the classic
 * WooCommerce integration script.
 *
 * The item payload moved from a hidden form input to a hidden span, because
 * a form element printed into the add-to-cart hooks drops WooCommerce's
 * block add to cart into full-page-reload mode. Pages already in a full-page
 * cache still carry the old input, so both readers must keep reading it
 * until those caches expire. These tests drive the click path and the
 * variation path against span-only and input-only markup.
 *
 * @module tests/js/woocommerce-single-product.test.js
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const SOURCE = fs.readFileSync(
	path.resolve( __dirname, '../../src/js/woocommerce.js' ),
	'utf-8'
);

const ITEM = {
	item_id: 'SKU-1',
	item_name: 'Beanie',
	price: 12.5,
};

/**
 * The handlers the script registers through jQuery, keyed by event name.
 *
 * @type {Object<string, Function[]>}
 */
let jqueryHandlers = {};

/**
 * The events pushed to the data layer during a test.
 *
 * @type {Array<Object>}
 */
let pushed = [];

/**
 * Seed the window globals the script assumes a real WordPress page has
 * already emitted, then load the production source into this JSDOM window.
 */
function loadScript() {
	jqueryHandlers = {};
	pushed = [];

	window.gtmkit_settings = {
		datalayer_name: 'dataLayer',
		console_log: false,
		wc: {
			wishlist: false,
			use_sku: false,
			pid_prefix: '',
			view_item: { config: 1 },
			text: {},
			css_selectors: {
				product_list_add_to_cart: '.add_to_cart_button',
				product_list_wishlist: '.wishlist',
				product_list_element: 'li.product',
				product_list_select_item: 'a',
				product_list_exclude: '',
				single_product_wishlist: '.wishlist',
			},
		},
	};

	window.gtmkit_data = { wc: { currency: 'EUR' } };
	window.gtmkit = {
		events: {
			push: ( payload ) => {
				pushed.push( payload );
			},
		},
	};

	window.jQuery = () => ( {
		on: ( name, handler ) => {
			jqueryHandlers[ name ] = jqueryHandlers[ name ] || [];
			jqueryHandlers[ name ].push( handler );
		},
	} );

	new vm.Script( SOURCE ).runInThisContext();
}

/**
 * Render a single-product add-to-cart form carrying the payload in the
 * given form.
 *
 * @param {string} carrier   Either 'span', 'input' or 'none'.
 * @param {string} extraHtml Extra markup to place inside the form.
 * @returns {HTMLFormElement}
 */
function renderProductForm( carrier, extraHtml = '' ) {
	const form = document.createElement( 'form' );
	form.className = 'cart';

	const payload = JSON.stringify( ITEM );

	if ( 'span' === carrier ) {
		const span = document.createElement( 'span' );
		span.className = 'gtmkit_single_product_data';
		span.setAttribute( 'data-gtmkit_product_id', '29' );
		span.setAttribute( 'data-gtmkit_product_data', payload );
		form.appendChild( span );
	}

	if ( 'input' === carrier ) {
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = 'gtmkit_product_data';
		input.value = payload;
		form.appendChild( input );
	}

	const quantity = document.createElement( 'input' );
	quantity.name = 'quantity';
	quantity.value = '2';
	form.appendChild( quantity );

	if ( extraHtml ) {
		form.insertAdjacentHTML( 'beforeend', extraHtml );
	}

	const button = document.createElement( 'button' );
	// A plain button: JSDOM has no form submission, and the click handler
	// under test does not care which button type fired the event.
	button.type = 'button';
	button.className = 'single_add_to_cart_button';
	form.appendChild( button );

	document.body.appendChild( form );

	return form;
}

/**
 * The last ecommerce event pushed, ignoring the `{ecommerce: null}` resets.
 *
 * @returns {Object|undefined}
 */
function lastEvent() {
	return pushed.filter( ( entry ) => entry && entry.event ).pop();
}

describe( 'single-product item payload readers', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'add_to_cart on click', () => {
		it( 'reads the payload from the carrier span', () => {
			loadScript();
			const form = renderProductForm( 'span' );

			form.querySelector( '.single_add_to_cart_button' ).click();

			const event = lastEvent();
			expect( event.event ).toBe( 'add_to_cart' );
			expect( event.ecommerce.items[ 0 ].item_id ).toBe( 'SKU-1' );
			expect( event.ecommerce.items[ 0 ].quantity ).toBe( '2' );
			expect( event.ecommerce.value ).toBe( 25 );
		} );

		it( 'falls back to the hidden input on cached pages', () => {
			loadScript();
			const form = renderProductForm( 'input' );

			form.querySelector( '.single_add_to_cart_button' ).click();

			const event = lastEvent();
			expect( event.event ).toBe( 'add_to_cart' );
			expect( event.ecommerce.items[ 0 ].item_id ).toBe( 'SKU-1' );
		} );

		it( 'stays silent when the page carries no payload', () => {
			loadScript();
			const form = renderProductForm( 'none' );

			expect( () =>
				form.querySelector( '.single_add_to_cart_button' ).click()
			).not.toThrow();
			expect( lastEvent() ).toBeUndefined();
		} );
	} );

	describe( 'view_item on variation change', () => {
		const variation = {
			variation_id: 44,
			sku: '',
			display_price: 30,
			gtmkit_price: 28,
			attributes: { attribute_pa_color: 'Blue' },
		};

		/**
		 * Fire the handler the script registered on jQuery's
		 * `found_variation` event.
		 *
		 * @param {HTMLFormElement} form The variations form.
		 */
		function fireFoundVariation( form ) {
			jqueryHandlers.found_variation.forEach( ( handler ) =>
				handler( { target: form }, variation )
			);
		}

		it( 'reads the payload from the carrier span', () => {
			loadScript();
			const form = renderProductForm(
				'span',
				'<input name="variation_id" value="44" />'
			);

			fireFoundVariation( form );

			const event = lastEvent();
			expect( event.event ).toBe( 'view_item' );
			expect( event.ecommerce.items[ 0 ].item_id ).toBe( '44' );
			expect( event.ecommerce.items[ 0 ].price ).toBe( 28 );
			expect( event.ecommerce.items[ 0 ].item_variant ).toBe( 'Blue' );
		} );

		it( 'falls back to the hidden input on cached pages', () => {
			loadScript();
			const form = renderProductForm(
				'input',
				'<input name="variation_id" value="44" />'
			);

			fireFoundVariation( form );

			const event = lastEvent();
			expect( event.event ).toBe( 'view_item' );
			expect( event.ecommerce.items[ 0 ].item_id ).toBe( '44' );
		} );

		it( 'stays silent when the page carries no payload', () => {
			loadScript();
			const form = renderProductForm(
				'none',
				'<input name="variation_id" value="44" />'
			);

			expect( () => fireFoundVariation( form ) ).not.toThrow();
			expect( lastEvent() ).toBeUndefined();
		} );
	} );
} );
