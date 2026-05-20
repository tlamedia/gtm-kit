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

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ensureDataLayer, pushDataLayer } from '../../src/js/dataLayer.js';

describe( 'dataLayer helpers', () => {
	beforeEach( () => {
		delete window.dataLayer;
		delete window.customDL;
		delete window.gtmkit;
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

describe( 'pushDataLayer deferral seam', () => {
	beforeEach( () => {
		delete window.dataLayer;
		delete window.customDL;
		delete window.gtmkit;
	} );

	it( 'pushes immediately when no gate is defined (free-user behaviour)', () => {
		const before = JSON.stringify( window.gtmkit );

		pushDataLayer( { event: 'add_to_cart' } );

		expect( window.dataLayer ).toEqual( [ { event: 'add_to_cart' } ] );
		// The helper must not invent a namespace when none was present.
		expect( JSON.stringify( window.gtmkit ) ).toBe( before );
	} );

	it( 'pushes immediately when shouldDefer returns false', () => {
		window.gtmkit = {
			events: {
				shouldDefer: vi.fn().mockReturnValue( false ),
				deferralSink: vi.fn(),
			},
		};

		pushDataLayer( { event: 'page_view' } );

		expect( window.dataLayer ).toEqual( [ { event: 'page_view' } ] );
		expect( window.gtmkit.events.deferralSink ).not.toHaveBeenCalled();
		expect( window.gtmkit.events.shouldDefer ).toHaveBeenCalledWith(
			'page_view',
			{ event: 'page_view' },
			undefined
		);
	} );

	it( 'hands the event to the registered sink when shouldDefer returns true', () => {
		const sink = vi.fn();
		window.gtmkit = {
			events: {
				shouldDefer: vi.fn().mockReturnValue( true ),
				deferralSink: sink,
			},
			consent: { state: { analytics_storage: 'denied' } },
		};

		const event = { event: 'add_to_cart', ecommerce: { value: 9.99 } };
		pushDataLayer( event, 'customDL' );

		// The helper still ensures the array exists, but no event lands on it.
		expect( window.customDL ).toEqual( [] );
		expect( sink ).toHaveBeenCalledTimes( 1 );
		expect( sink ).toHaveBeenCalledWith( event, 'customDL' );
		expect( window.gtmkit.events.shouldDefer ).toHaveBeenCalledWith(
			'add_to_cart',
			event,
			{ analytics_storage: 'denied' }
		);
	} );

	it( 'falls back to pushing when shouldDefer returns true but no sink is registered', () => {
		window.gtmkit = {
			events: {
				shouldDefer: vi.fn().mockReturnValue( true ),
			},
		};

		pushDataLayer( { event: 'add_to_cart' } );

		expect( window.dataLayer ).toEqual( [ { event: 'add_to_cart' } ] );
	} );

	it( 'passes the current consent state from window.gtmkit.consent.state', () => {
		const state = { analytics_storage: 'granted', ad_storage: 'denied' };
		const shouldDefer = vi.fn().mockReturnValue( false );
		window.gtmkit = {
			events: { shouldDefer },
			consent: { state },
		};

		pushDataLayer( { event: 'view_item' } );

		expect( shouldDefer ).toHaveBeenCalledWith(
			'view_item',
			{ event: 'view_item' },
			state
		);
	} );

	it( 'tolerates payloads that are not objects or lack an event name', () => {
		const shouldDefer = vi.fn().mockReturnValue( false );
		window.gtmkit = { events: { shouldDefer } };

		pushDataLayer( [ 'consent', 'default', {} ] );

		expect( shouldDefer ).toHaveBeenCalledWith(
			'',
			[ 'consent', 'default', {} ],
			undefined
		);
		expect( window.dataLayer ).toHaveLength( 1 );
	} );
} );
