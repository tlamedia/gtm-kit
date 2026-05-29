// @vitest-environment jsdom
/**
 * Product Search block: search event on submit.
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createProductSearchSubscriber } from '../../../src/js/frontend/woocommerce-blocks/blocks/product-search.js';
import { installSeam } from './helpers.js';

const clearBody = () => {
	while ( document.body.firstChild ) {
		document.body.removeChild( document.body.firstChild );
	}
};

const buildSearchForm = ( value ) => {
	clearBody();
	const form = document.createElement( 'form' );
	form.className = 'wc-block-product-search';
	const input = document.createElement( 'input' );
	input.className = 'wc-block-product-search__field';
	input.type = 'search';
	input.value = value;
	form.appendChild( input );
	document.body.appendChild( form );
	return form;
};

const submit = ( form ) =>
	form.dispatchEvent( new window.Event( 'submit', { bubbles: true, cancelable: true } ) );

describe( 'product search', () => {
	let seam;
	let detach;

	beforeEach( () => {
		seam = installSeam();
		detach = createProductSearchSubscriber();
	} );

	afterEach( () => {
		if ( detach ) detach();
	} );

	it( 'emits search with the entered term', () => {
		const form = buildSearchForm( 'red shoes' );
		submit( form );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'search' );
		expect( events[ 0 ].search_term ).toBe( 'red shoes' );
	} );

	it( 'does not emit for an empty term', () => {
		const form = buildSearchForm( '   ' );
		submit( form );

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );
