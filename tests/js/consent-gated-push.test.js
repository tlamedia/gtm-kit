// @vitest-environment jsdom
/**
 * Vitest coverage for the script that holds a purchase's contact details until
 * advertising consent is known.
 *
 * Loads the production source (`src/js/consent-gated-push.js`) into the JSDOM
 * window and drives it through the data layer the way Consent Mode does, with
 * `gtag()`-style arguments objects.
 *
 * @module tests/js/consent-gated-push.test.js
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { loadReceiver } from './consent-bridge-receiver';

const SOURCE = fs.readFileSync(
	path.resolve( __dirname, '../../src/js/consent-gated-push.js' ),
	'utf-8'
);

/**
 * Run the script in the current window.
 */
function load() {
	new vm.Script( SOURCE ).runInThisContext();
}

/**
 * Push a Consent Mode command the way `gtag()` does: an arguments object.
 *
 * @param {string} command `default` or `update`.
 * @param {Object} values  The consent values.
 */
function consent( command, values ) {
	window.dataLayer.push(
		( function () {
			return arguments;
		} )( 'consent', command, values )
	);
}

const both = ( value ) => ( { ad_storage: value, ad_user_data: value } );

/**
 * A purchase event without its contact details.
 *
 * @return {Object} The event.
 */
function purchase() {
	return {
		event: 'purchase',
		ecommerce: {
			transaction_id: '837',
			customer: { id: 1, order_count: 2, total_spent: 50 },
		},
	};
}

const held = {
	user_data: { sha256_email_address: 'abc' },
	ecommerce: { customer: { billing_email: 'buyer@example.test' } },
};

/**
 * The purchase events currently in the data layer.
 *
 * @return {Array<Object>} The purchase events.
 */
const purchases = () =>
	window.dataLayer.filter( ( entry ) => entry && entry.event === 'purchase' );

/**
 * Report a visibility state and tell the page it changed.
 *
 * @param {string} state `hidden` or `visible`.
 */
function setVisibility( state ) {
	Object.defineProperty( document, 'visibilityState', {
		configurable: true,
		get: () => state,
	} );
	document.dispatchEvent( new Event( 'visibilitychange' ) );
	delete document.visibilityState;
}

describe( 'consent-gated push', () => {
	beforeEach( () => {
		vi.useFakeTimers();
		delete window.gtmkit;
		delete window.google_tag_data;
		delete window.shopLayer;
		window.dataLayer = [];
		load();
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'pushes with the details at once when both categories are granted', () => {
		consent( 'default', both( 'granted' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
		expect( purchases()[ 0 ].ecommerce.customer ).toEqual( {
			id: 1,
			order_count: 2,
			total_spent: 50,
			billing_email: 'buyer@example.test',
		} );
	} );

	it( 'waits on a denying default and adds the details once the visitor grants', () => {
		consent( 'default', both( 'denied' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		expect( purchases() ).toHaveLength( 0 );

		consent( 'update', both( 'granted' ) );
		vi.advanceTimersByTime( 100 );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'reacts to the GTM Kit consent event without waiting for the next check', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'granted' ) );
		window.dispatchEvent( new CustomEvent( 'gtmkit:consent:updated' ) );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeDefined();
	} );

	it( 'pushes without the details once the visitor denies either category', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', { ad_storage: 'granted', ad_user_data: 'denied' } );
		vi.advanceTimersByTime( 100 );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
		expect( purchases()[ 0 ].ecommerce.customer ).toEqual( {
			id: 1,
			order_count: 2,
			total_spent: 50,
		} );
	} );

	it( 'waits the full time, then sends the details when the site sets no consent state', () => {
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		vi.advanceTimersByTime( 2900 );
		expect( purchases() ).toHaveLength( 0 );

		vi.advanceTimersByTime( 100 );
		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
		expect( purchases()[ 0 ].ecommerce.customer.billing_email ).toBe(
			'buyer@example.test'
		);
	} );

	it( 'sends the details when the page is left on a site with no consent state', () => {
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'counts a default for other categories only as no consent state', () => {
		consent( 'default', { analytics_storage: 'denied' } );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'pushes without the details when a denying default is never lifted', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		vi.advanceTimersByTime( 2900 );
		expect( purchases() ).toHaveLength( 0 );

		vi.advanceTimersByTime( 100 );
		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'pushes without the details when only a regional default exists', () => {
		consent( 'default', { ...both( 'granted' ), region: [ 'US' ] } );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'withholds the details for unknown consent when the site asks it to', () => {
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held, {
			unknownConsent: 'deny',
		} );

		vi.advanceTimersByTime( 3000 );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'ignores the unknown-consent setting when a consent state exists', () => {
		consent( 'default', both( 'granted' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held, {
			unknownConsent: 'deny',
		} );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );

		window.dataLayer = [];
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held, {
			unknownConsent: 'allow',
		} );
		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'ends the wait on the clock even when the browser slows its timers', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		// A background tab may run the check once a second or less, so
		// three seconds can pass with only a single tick.
		vi.setSystemTime( Date.now() + 3000 );
		vi.advanceTimersByTime( 100 );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'treats a default scoped to regions as no answer, whatever the order', () => {
		consent( 'default', { ...both( 'denied' ), region: [ 'DK' ] } );
		consent( 'default', both( 'granted' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		expect( purchases() ).toHaveLength( 0 );

		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it.each( [
		[ 'ad_storage', 'update' ],
		[ 'ad_user_data', 'update' ],
		[ 'ad_storage', 'default' ],
		[ 'ad_user_data', 'default' ],
	] )(
		'does not take only %s granted in a %s as consent for both',
		( category, command ) => {
			consent( command, { [ category ]: 'granted' } );
			window.gtmkit.events.pushWhenConsented(
				purchase(),
				'dataLayer',
				held
			);
			expect( purchases() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 3000 );
			expect( purchases() ).toHaveLength( 1 );
			expect( purchases()[ 0 ].user_data ).toBeUndefined();
		}
	);

	it( 'does not let a grant stand in for a category only a regional default sets', () => {
		consent( 'default', { ad_storage: 'granted' } );
		consent( 'default', { ad_user_data: 'denied', region: [ 'DK' ] } );

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		expect( purchases() ).toHaveLength( 0 );

		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'pushes without the details when the page is left before an answer', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'pushes the event once only', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'granted' ) );
		window.dispatchEvent( new CustomEvent( 'gtmkit:consent:updated' ) );
		window.dispatchEvent( new Event( 'pagehide' ) );
		vi.advanceTimersByTime( 5000 );

		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'pushes without the details when the page is hidden before an answer', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		setVisibility( 'hidden' );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();

		vi.advanceTimersByTime( 5000 );
		window.dispatchEvent( new Event( 'pagehide' ) );
		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'keeps waiting when the page becomes visible', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		setVisibility( 'visible' );
		expect( purchases() ).toHaveLength( 0 );

		consent( 'update', both( 'granted' ) );
		vi.advanceTimersByTime( 100 );
		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'adds the details when the visitor granted just before leaving', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'granted' ) );
		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'reads consent from the data layer the event goes to', () => {
		window.shopLayer = [];
		window.shopLayer.push(
			( function () {
				return arguments;
			} )( 'consent', 'default', both( 'granted' ) )
		);

		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );

		expect( window.shopLayer.filter( ( e ) => e && e.event ) ).toHaveLength(
			1
		);
		expect( purchases() ).toHaveLength( 0 );
	} );

	it( 'withholds the details when consent is denied in dataLayer and the event goes elsewhere', () => {
		window.shopLayer = [];
		consent( 'default', both( 'denied' ) );
		consent( 'update', both( 'denied' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );

		const sent = window.shopLayer.filter( ( e ) => e && e.event );
		expect( sent ).toHaveLength( 1 );
		expect( sent[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'withholds the details at the end of the wait when only dataLayer holds a denying default', () => {
		window.shopLayer = [];
		consent( 'default', both( 'denied' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );
		vi.advanceTimersByTime( 3000 );

		const sent = window.shopLayer.filter( ( e ) => e && e.event );
		expect( sent ).toHaveLength( 1 );
		expect( sent[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'lets a denial in one data layer beat a grant in the other', () => {
		window.shopLayer = [];
		window.shopLayer.push(
			( function () {
				return arguments;
			} )( 'consent', 'update', both( 'granted' ) )
		);
		consent( 'update', { ad_storage: 'granted', ad_user_data: 'denied' } );

		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );

		const sent = window.shopLayer.filter( ( e ) => e && e.event );
		expect( sent[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'withholds the details when Google Tag Manager holds a denial set by a template', () => {
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { default: false, update: false },
					ad_user_data: { default: false, update: false },
				},
			},
		};

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'withholds the details at the end of the wait on a denying Google Tag Manager default', () => {
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { default: false },
					ad_user_data: { default: false },
				},
			},
		};

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		expect( purchases() ).toHaveLength( 0 );

		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'adds the details once Google Tag Manager records a grant', () => {
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { default: false },
					ad_user_data: { default: false },
				},
			},
		};
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		window.google_tag_data.ics.entries.ad_storage.update = true;
		window.google_tag_data.ics.entries.ad_user_data.update = true;
		vi.advanceTimersByTime( 100 );

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'takes the Google Tag Manager default over a default scoped to regions', () => {
		consent( 'default', { ...both( 'denied' ), region: [ 'DK' ] } );
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { default: true },
					ad_user_data: { default: true },
				},
			},
		};

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'does not count a default Google Tag Manager set on its own', () => {
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { implicit: true, default: false },
					ad_user_data: { implicit: true, default: false },
				},
			},
		};

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'treats unexpected Google Tag Manager state as no consent state', () => {
		window.google_tag_data = { ics: { entries: 'unexpected' } };
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );

		window.dataLayer = [];
		window.google_tag_data = {
			ics: {
				entries: {
					ad_storage: { default: 'denied' },
					ad_user_data: null,
				},
			},
		};
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );

		window.dataLayer = [];
		Object.defineProperty( window, 'google_tag_data', {
			configurable: true,
			get() {
				throw new Error( 'blocked' );
			},
		} );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );
		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
		delete window.google_tag_data;
	} );

	it( 'withholds the details at the end of the wait when only the GTM Kit consent state denies', () => {
		window.gtmkit.consent = { state: both( 'denied' ) };

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'does not take a grant in the GTM Kit consent state as an answer', () => {
		window.gtmkit.consent = { state: both( 'granted' ) };

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( purchases() ).toHaveLength( 0 );

		// End the wait so the held event does not answer to later tests.
		vi.advanceTimersByTime( 3000 );
	} );

	it( 'takes the answer carried by the GTM Kit consent event', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		window.dispatchEvent(
			new CustomEvent( 'gtmkit:consent:updated', {
				detail: { ad_storage: 'granted', ad_user_data: 'denied' },
			} )
		);

		expect( purchases() ).toHaveLength( 1 );
		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'hands the event to the GTM Kit push helper so deferral still applies', () => {
		const push = vi.fn();
		window.gtmkit.events.push = push;
		consent( 'default', both( 'granted' ) );

		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( push ).toHaveBeenCalledTimes( 1 );
		expect( push.mock.calls[ 0 ][ 1 ] ).toBe( 'dataLayer' );
		expect( push.mock.calls[ 0 ][ 0 ].user_data ).toEqual( held.user_data );
	} );
} );

describe( 'the hold other scripts can read', () => {
	const hold = () => window.gtmkit.events.consentHold;

	beforeEach( () => {
		vi.useFakeTimers();
		vi.setSystemTime( 1_700_000_000_000 );
		delete window.gtmkit;
		window.dataLayer = [];
		load();
	} );

	afterEach( () => {
		// Release any event still waiting, so its listeners do not reach
		// the next test.
		window.dispatchEvent( new Event( 'pagehide' ) );
		vi.useRealTimers();
	} );

	it( 'is pending until the end of the wait while the event is held', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( hold() ).toEqual( {
			pending: true,
			until: 1_700_000_000_000 + 3000,
		} );
		expect( purchases() ).toHaveLength( 0 );
	} );

	it( 'is released when the visitor grants', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'granted' ) );
		vi.advanceTimersByTime( 100 );

		expect( hold().pending ).toBe( false );
		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'is released when the visitor denies', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'denied' ) );
		window.dispatchEvent( new CustomEvent( 'gtmkit:consent:updated' ) );

		expect( hold().pending ).toBe( false );
		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'is released at the end of the wait', () => {
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		vi.advanceTimersByTime( 2900 );
		expect( hold().pending ).toBe( true );

		vi.advanceTimersByTime( 100 );
		expect( hold().pending ).toBe( false );
		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'is released when the page is left', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( hold().pending ).toBe( false );
		expect( purchases() ).toHaveLength( 1 );
	} );

	it( 'is released before the event reaches the push helper', () => {
		let pendingAtPush;
		window.gtmkit.events.push = () => {
			pendingAtPush = hold().pending;
		};
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		consent( 'update', both( 'granted' ) );
		vi.advanceTimersByTime( 100 );

		expect( pendingAtPush ).toBe( false );
	} );

	it( 'is never set when the event is pushed at once', () => {
		consent( 'default', both( 'granted' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		expect( purchases() ).toHaveLength( 1 );
		expect( hold() ).toBeUndefined();
	} );

	it( 'is never set when the gate is not used', () => {
		expect( hold() ).toBeUndefined();
	} );
} );

describe.each( [ 'dataLayer', 'shopLayer' ] )(
	'consent reported through the bridge, event layer %s',
	( layerName ) => {
		/**
		 * The purchase events in the layer the event goes to.
		 *
		 * @return {Array<Object>} The purchase events.
		 */
		const sent = () =>
			( window[ layerName ] || [] ).filter(
				( entry ) => entry && entry.event === 'purchase'
			);

		/**
		 * Report both required categories to the bridge.
		 *
		 * @param {boolean} granted Whether they are granted.
		 * @param {string}  basis   `initial` or `change`.
		 */
		const reportBoth = ( granted, basis ) => {
			window.gtmkit.consentBridge.report( 'ad_storage', granted, basis );
			window.gtmkit.consentBridge.report( 'ad_user_data', granted, basis );
		};

		beforeEach( () => {
			vi.useFakeTimers();
			delete window.gtmkit;
			delete window.google_tag_data;
			delete window.shopLayer;
			window.dataLayer = [];
			load();
			loadReceiver();
		} );

		afterEach( () => {
			// Release any event still waiting, so its listeners do not reach
			// the next test.
			window.dispatchEvent( new Event( 'pagehide' ) );
			vi.useRealTimers();
		} );

		it( 'pushes with the details at once on an answer granting both', () => {
			reportBoth( true, 'change' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
			expect( sent()[ 0 ].ecommerce.customer.billing_email ).toBe(
				'buyer@example.test'
			);
			expect( window.gtmkit.events.consentHold ).toBeUndefined();
		} );

		it.each( [ 'ad_storage', 'ad_user_data' ] )(
			'pushes without the details at once on an answer denying %s',
			( denied ) => {
				reportBoth( true, 'change' );
				window.gtmkit.consentBridge.report( denied, false, 'change' );

				window.gtmkit.events.pushWhenConsented(
					purchase(),
					layerName,
					held
				);

				expect( sent() ).toHaveLength( 1 );
				expect( sent()[ 0 ].user_data ).toBeUndefined();
				expect( sent()[ 0 ].ecommerce.customer ).toEqual( {
					id: 1,
					order_count: 2,
					total_spent: 50,
				} );
			}
		);

		it( 'waits on a denying initial state and adds the details when the answer grants', () => {
			reportBoth( false, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			// The report alone releases the event, before the next check.
			reportBoth( true, 'change' );
			vi.advanceTimersByTime( 0 );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'pushes without the details at the end of the wait when a denying initial state is never answered', () => {
			reportBoth( false, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 100 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'withholds the details when a grant is revoked before the push', () => {
			reportBoth( true, 'change' );
			window.gtmkit.consentBridge.report( 'ad_storage', false, 'change' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'waits on a granting initial state when Google Tag Manager holds no consent state, then adds the details at the end of the wait', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );
			expect( window.gtmkit.events.consentHold.pending ).toBe( true );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 100 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
			expect( sent()[ 0 ].ecommerce.customer.billing_email ).toBe(
				'buyer@example.test'
			);
		} );

		it( 'adds the details when the page is left before the end of the wait on a granting initial state alone', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 500 );
			window.dispatchEvent( new Event( 'pagehide' ) );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'withholds the details at the end of the wait on a granting initial state alone when the site withholds them on unknown consent', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held, {
				unknownConsent: 'deny',
			} );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 100 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
			expect( sent()[ 0 ].ecommerce.customer.billing_email ).toBeUndefined();

			vi.advanceTimersByTime( 3000 );
			expect( sent() ).toHaveLength( 1 );
		} );

		it( 'withholds the details when the page is left on a granting initial state alone and the site withholds them on unknown consent', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held, {
				unknownConsent: 'deny',
			} );

			vi.advanceTimersByTime( 500 );
			window.dispatchEvent( new Event( 'pagehide' ) );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'lets a denying default beat a granting initial state at the end of the wait', () => {
			consent( 'default', both( 'denied' ) );
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 100 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'lets a global denying default beat a granting initial state when a regional default also exists', () => {
			consent( 'default', both( 'denied' ) );
			consent( 'default', { ...both( 'denied' ), region: [ 'DE' ] } );
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			vi.advanceTimersByTime( 3000 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
			expect( sent()[ 0 ].ecommerce.customer.billing_email ).toBeUndefined();
		} );

		it( 'adds the details at the end of the wait for a visitor outside the region of GTM Kit\'s own denying default', () => {
			consent( 'default', { ...both( 'denied' ), region: [ 'DE', 'DK' ] } );
			window.gtmkit.consent = { state: both( 'denied' ) };
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			vi.advanceTimersByTime( 3000 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'lets a denial in GTM Kit\'s own state beat a granting initial state when no default is scoped to regions', () => {
			window.gtmkit.consent = { state: both( 'denied' ) };
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			vi.advanceTimersByTime( 3000 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'pushes with the details at once on a granting initial state that Google Tag Manager confirms', () => {
			window.google_tag_data = {
				ics: {
					entries: {
						ad_storage: { default: true },
						ad_user_data: { default: true },
					},
				},
			};
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
			expect( window.gtmkit.events.consentHold ).toBeUndefined();
		} );

		it( 'waits for the end of the wait when Google Tag Manager holds only defaults it set on its own', () => {
			window.google_tag_data = {
				ics: {
					entries: {
						ad_storage: { default: true, implicit: true },
						ad_user_data: { default: true, implicit: true },
					},
				},
			};
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 2900 );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 100 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'pushes without the details at once when a late default denies after a granting initial state', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			reportBoth( false, 'change' );
			vi.advanceTimersByTime( 0 );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
			expect( sent()[ 0 ].ecommerce.customer ).toEqual( {
				id: 1,
				order_count: 2,
				total_spent: 50,
			} );
		} );

		it( 'pushes with the details at once when the answer grants after a granting initial state', () => {
			reportBoth( true, 'initial' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			reportBoth( true, 'change' );
			vi.advanceTimersByTime( 0 );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'lets a denial from the bridge beat a grant in the data layer', () => {
			consent( 'update', both( 'granted' ) );
			window.gtmkit.consentBridge.report( 'ad_user_data', false, 'change' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'does not take a report for one category as consent for both', () => {
			window.gtmkit.consentBridge.report( 'ad_storage', true, 'change' );

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 0 );

			vi.advanceTimersByTime( 3000 );
			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );

		it( 'treats a bridge with unexpected contents as no consent state', () => {
			window.gtmkit.consentBridge = { state: 'unexpected', basis: null };

			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			vi.advanceTimersByTime( 3000 );

			expect( sent()[ 0 ].user_data ).toEqual( held.user_data );
		} );

		it( 'stops listening to the bridge once the event is pushed', () => {
			reportBoth( false, 'change' );
			window.gtmkit.events.pushWhenConsented( purchase(), layerName, held );
			expect( sent() ).toHaveLength( 1 );

			reportBoth( true, 'change' );
			vi.advanceTimersByTime( 5000 );

			expect( sent() ).toHaveLength( 1 );
			expect( sent()[ 0 ].user_data ).toBeUndefined();
		} );
	}
);

describe( 'the built script printed on the page', () => {
	const BUILT = fs.readFileSync(
		path.resolve(
			__dirname,
			'../../assets/frontend/consent-gated-push.js'
		),
		'utf-8'
	);

	beforeEach( () => {
		vi.useFakeTimers();
		delete window.gtmkit;
		delete window.google_tag_data;
		delete window.shopLayer;
		window.dataLayer = [];
		new vm.Script( BUILT ).runInThisContext();
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'adds the details once granted and holds them back otherwise', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		consent( 'update', both( 'granted' ) );
		vi.advanceTimersByTime( 100 );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );

		window.dataLayer = [];
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toBeUndefined();
	} );

	it( 'sends the details at the end of the wait when there is no consent state', () => {
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );
		vi.advanceTimersByTime( 3000 );

		expect( purchases()[ 0 ].user_data ).toEqual( held.user_data );
	} );

	it( 'reads consent outside the data layer the event goes to', () => {
		window.shopLayer = [];
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );
		vi.advanceTimersByTime( 3000 );

		expect(
			window.shopLayer.filter( ( e ) => e && e.event )[ 0 ].user_data
		).toBeUndefined();

		window.shopLayer = [];
		window.dataLayer = [];
		window.google_tag_data = {
			ics: { entries: { ad_storage: { update: false } } },
		};
		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );

		expect(
			window.shopLayer.filter( ( e ) => e && e.event )[ 0 ].user_data
		).toBeUndefined();
	} );

	it( 'publishes the hold while waiting and releases it afterwards', () => {
		consent( 'default', both( 'denied' ) );
		window.gtmkit.events.pushWhenConsented( purchase(), 'dataLayer', held );

		const hold = window.gtmkit.events.consentHold;
		expect( hold.pending ).toBe( true );
		expect( hold.until ).toBe( Date.now() + 3000 );

		vi.advanceTimersByTime( 3000 );
		expect( window.gtmkit.events.consentHold.pending ).toBe( false );
	} );

	it( 'reads consent reported through the bridge', () => {
		loadReceiver();
		window.gtmkit.consentBridge.report( 'ad_storage', false, 'initial' );
		window.gtmkit.consentBridge.report( 'ad_user_data', false, 'initial' );

		window.gtmkit.events.pushWhenConsented( purchase(), 'shopLayer', held );
		expect( window.shopLayer.filter( ( e ) => e && e.event ) ).toHaveLength(
			0
		);

		window.gtmkit.consentBridge.report( 'ad_storage', true, 'change' );
		window.gtmkit.consentBridge.report( 'ad_user_data', true, 'change' );
		vi.advanceTimersByTime( 0 );

		const sent = window.shopLayer.filter( ( e ) => e && e.event );
		expect( sent ).toHaveLength( 1 );
		expect( sent[ 0 ].user_data ).toEqual( held.user_data );
	} );
} );
