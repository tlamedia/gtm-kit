// @vitest-environment jsdom
/**
 * Product Search block: search event on submit.
 */

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
	createProductSearchSubscriber,
	hasProductSearch,
} from '../../../src/js/frontend/woocommerce-blocks/blocks/product-search.js';
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

/**
 * Render a WordPress Search block form the way WordPress prints it, scoped
 * to products when it is the Product Search variation.
 *
 * @param {string}  value    The entered term.
 * @param {boolean} products Whether the block is scoped to products.
 * @return {HTMLFormElement} The form.
 */
const buildSearchBlockForm = ( value, products ) => {
	clearBody();
	const form = document.createElement( 'form' );
	form.className = 'wp-block-search__button-outside wp-block-search';
	const input = document.createElement( 'input' );
	input.className = 'wp-block-search__input';
	input.type = 'search';
	input.name = 's';
	input.value = value;
	form.appendChild( input );
	if ( products ) {
		const scope = document.createElement( 'input' );
		scope.type = 'hidden';
		scope.name = 'post_type';
		scope.value = 'product';
		form.appendChild( scope );
	}
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
		window.gtmkitWooCommerceBlocksBuild = { searchOnResultsPage: false };
		detach = createProductSearchSubscriber();
	} );

	afterEach( () => {
		if ( detach ) detach();
		delete window.gtmkitWooCommerceBlocksBuild;
	} );

	it( 'emits search with the entered term', () => {
		const form = buildSearchForm( 'red shoes' );
		submit( form );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].event ).toBe( 'search' );
		expect( events[ 0 ].search_term ).toBe( 'red shoes' );
	} );

	it( 'emits search for the Search block scoped to products', () => {
		submit( buildSearchBlockForm( 'red shoes', true ) );

		const events = seam.events();
		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ].search_term ).toBe( 'red shoes' );
	} );

	it( 'leaves a site-wide Search block to engagement search', () => {
		submit( buildSearchBlockForm( 'red shoes', false ) );

		expect( seam.events() ).toHaveLength( 0 );
	} );

	it( 'finds either Product Search on the page, and not a site search', () => {
		buildSearchBlockForm( 'x', false );
		expect( hasProductSearch() ).toBe( false );
		buildSearchBlockForm( 'x', true );
		expect( hasProductSearch() ).toBe( true );
		buildSearchForm( 'x' );
		expect( hasProductSearch() ).toBe( true );
	} );

	it( 'does not emit for an empty term', () => {
		const form = buildSearchForm( '   ' );
		submit( form );

		expect( seam.events() ).toHaveLength( 0 );
	} );
} );

const ENGAGEMENT_SOURCE = new vm.Script(
	fs.readFileSync(
		path.resolve( __dirname, '../../../src/js/engagement-events.js' ),
		'utf-8'
	)
);

/**
 * Render the search results page the block submits to. The PHP side only
 * marks the body while the engagement search event is on.
 *
 * @param {string}  term     The submitted term.
 * @param {boolean} enabled  Whether the engagement search event is on.
 */
const loadResultsPage = ( term, enabled ) => {
	clearBody();
	document.body.className = enabled ? 'gtmkit-search-results' : '';
	if ( enabled ) {
		document.body.dataset.gtmkitSearchTerm = term;
	} else {
		delete document.body.dataset.gtmkitSearchTerm;
	}
	if ( enabled ) {
		ENGAGEMENT_SOURCE.runInThisContext();
	}
};

/*
 * The block bundle loads wherever the block renders, on block and classic
 * themes alike, so the theme type does not change what runs here. Each
 * case submits a search and then lands on the results page.
 */
describe.each( [ true, false ] )(
	'one search event per search (engagement search on: %s)',
	( enabled ) => {
		let seam;
		let detach;

		beforeEach( () => {
			seam = installSeam();
			window.gtmkitWooCommerceBlocksBuild = {
				searchOnResultsPage: enabled,
			};
			detach = createProductSearchSubscriber();
		} );

		afterEach( () => {
			if ( detach ) detach();
			delete window.gtmkitWooCommerceBlocksBuild;
			document.body.className = '';
			delete document.body.dataset.gtmkitSearchTerm;
		} );

		it.each( [
			[ 'legacy block', () => buildSearchForm( 'red shoes' ) ],
			[
				'Search block variation',
				() => buildSearchBlockForm( 'red shoes', true ),
			],
		] )( 'pushes exactly one search with the term (%s)', ( _, build ) => {
			submit( build() );
			loadResultsPage( 'red shoes', enabled );

			const searches = seam
				.events()
				.filter( ( entry ) => entry.event === 'search' );
			expect( searches ).toHaveLength( 1 );
			expect( searches[ 0 ].search_term ).toBe( 'red shoes' );
		} );
	}
);
