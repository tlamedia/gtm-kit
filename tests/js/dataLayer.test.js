// @vitest-environment jsdom
/**
 * JS smoke test template for gtm-kit.
 *
 * Pattern: Vitest + JSDOM (scoped via the `@vitest-environment` pragma
 * above so the harness does not force JSDOM onto future Node-env tests).
 *
 * Target: the canonical helpers at src/js/dataLayer.js — `ensureDataLayer`
 * and `pushDataLayer` — which mirror the inline initializer emitted by
 * Frontend::enqueue_settings_and_data_script() at src/Frontend/Frontend.php.
 *
 * Future JS tests should copy the `beforeEach` cleanup shape so window
 * state from one test never leaks into another.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import { ensureDataLayer, pushDataLayer } from '../../src/js/dataLayer.js';

describe( 'dataLayer helpers', () => {
	beforeEach( () => {
		delete window.dataLayer;
		delete window.customDL;
	} );

	it( 'respects a custom dataLayer name', () => {
		pushDataLayer( { event: 'test' }, 'customDL' );

		expect( window.customDL ).toEqual( [ { event: 'test' } ] );
		expect( window.dataLayer ).toBeUndefined();
	} );

	it( 'pushes a deep object onto the dataLayer without mutation', () => {
		const event = {
			event: 'purchase',
			ecommerce: {
				currency: 'USD',
				items: [ { id: 'SKU-1', price: 9.99 } ],
			},
		};

		pushDataLayer( event );

		expect( window.dataLayer ).toHaveLength( 1 );
		expect( window.dataLayer[ 0 ] ).toBe( event );
		expect( window.dataLayer[ 0 ].ecommerce.items[ 0 ].id ).toBe( 'SKU-1' );
	} );

	it( 'does not replace an existing dataLayer on repeated initialization', () => {
		const first = ensureDataLayer();
		first.push( { event: 'priorVisit' } );

		const second = ensureDataLayer();

		expect( second ).toBe( first );
		expect( window.dataLayer ).toHaveLength( 1 );
		expect( window.dataLayer[ 0 ] ).toEqual( { event: 'priorVisit' } );
	} );
} );
