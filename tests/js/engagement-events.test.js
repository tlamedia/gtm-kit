// @vitest-environment jsdom
/**
 * Vitest coverage for the GA4 engagement-events frontend module.
 *
 * Loads the actual production source file
 * (`src/js/engagement-events.js`) inside the JSDOM context so the IIFE
 * runs against the real DOM the module sees in the browser. Each test
 * resets `window`, `document`, and `document.cookie` to a known shape,
 * then asserts on observable side effects: pushes into the shared
 * client-push seam, deletion of the cookie, and the absence of any
 * push when the cookie is missing or malformed.
 *
 * @module tests/js/engagement-events.test.js
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const MODULE_SOURCE = fs.readFileSync(
	path.resolve( __dirname, '../../src/js/engagement-events.js' ),
	'utf-8'
);

/**
 * Run the module in the current JSDOM window. The module is an IIFE so
 * a fresh `eval` re-installs the listeners and re-runs the cookie
 * drain.
 */
function loadModule() {
	const fn = new vm.Script( MODULE_SOURCE );
	fn.runInThisContext();
}

/**
 * Stub `window.gtmkit.events.push` so tests can observe what the
 * module would have pushed onto the dataLayer.
 *
 * @return {import('vitest').Mock} A vitest spy that records every push call.
 */
function installPushSpy() {
	const spy = vi.fn();
	window.gtmkit = { events: { push: spy } };
	return spy;
}

/**
 * Set the engagement-event cookie with a URL-encoded JSON payload.
 *
 * @param {Object} payload
 */
function setEngagementCookie( payload ) {
	document.cookie = `gtmkit_engagement_event=${ encodeURIComponent(
		JSON.stringify( payload )
	) }`;
}

/**
 * Remove every child element from a parent. Avoids innerHTML so the
 * security hook stays happy.
 *
 * @param {Node} parent
 */
function clearChildren( parent ) {
	while ( parent.firstChild ) {
		parent.removeChild( parent.firstChild );
	}
}

describe( 'engagement-events module', () => {
	beforeEach( () => {
		clearChildren( document.head );
		clearChildren( document.body );
		document.body.className = '';
		delete document.body.dataset.gtmkitSearchTerm;
		delete window.gtmkitEngagementEvents;
		delete window.gtmkit;
		delete window.gtmkit_settings;
		delete window.dataLayer;
		// Reset cookies. JSDOM does not honour Max-Age=0 without a
		// matching attribute set, so explicitly assign empty strings
		// to known names.
		document.cookie = 'gtmkit_engagement_event=; Path=/; Max-Age=0';
	} );

	afterEach( () => {
		document.cookie = 'gtmkit_engagement_event=; Path=/; Max-Age=0';
	} );

	it( 'reads the cookie, pushes the decoded payload, and deletes the cookie', () => {
		const push = installPushSpy();
		window.gtmkit_settings = { datalayer_name: 'dataLayer' };
		setEngagementCookie( { event: 'login', method: 'wordpress' } );

		loadModule();

		expect( push ).toHaveBeenCalledTimes( 1 );
		expect( push ).toHaveBeenCalledWith(
			{ event: 'login', method: 'wordpress' },
			'dataLayer'
		);
		expect( document.cookie ).not.toContain(
			'gtmkit_engagement_event=eyJ'
		);
	} );

	it( 'deletes the cookie and pushes nothing when JSON.parse fails', () => {
		const push = installPushSpy();
		// Raw value that fails JSON.parse after decodeURIComponent.
		document.cookie = 'gtmkit_engagement_event=not-json';

		loadModule();

		expect( push ).not.toHaveBeenCalled();
		expect( document.cookie ).not.toContain(
			'gtmkit_engagement_event=not-json'
		);
	} );

	it( 'pushes nothing when no cookie is present', () => {
		const push = installPushSpy();

		loadModule();

		expect( push ).not.toHaveBeenCalled();
	} );

	it( 'pushes a search event when the body class + data attribute are set', () => {
		const push = installPushSpy();
		document.body.classList.add( 'gtmkit-search-results' );
		document.body.dataset.gtmkitSearchTerm = 'widget';

		loadModule();

		expect( push ).toHaveBeenCalledWith(
			{ event: 'search', search_term: 'widget' },
			'dataLayer'
		);
	} );

	it( 'does not push a search event when the body class is missing', () => {
		const push = installPushSpy();
		document.body.dataset.gtmkitSearchTerm = 'widget';

		loadModule();

		expect( push ).not.toHaveBeenCalled();
	} );

	it( 'does not push a search event when the search term is empty', () => {
		const push = installPushSpy();
		document.body.classList.add( 'gtmkit-search-results' );
		document.body.dataset.gtmkitSearchTerm = '   ';

		loadModule();

		expect( push ).not.toHaveBeenCalled();
	} );

	it( 'respects a custom cookie name from the localized config', () => {
		const push = installPushSpy();
		window.gtmkitEngagementEvents = { cookieName: 'gtmkit_alt' };
		document.cookie = `gtmkit_alt=${ encodeURIComponent(
			JSON.stringify( { event: 'sign_up', method: 'woocommerce' } )
		) }`;

		loadModule();

		expect( push ).toHaveBeenCalledWith(
			{ event: 'sign_up', method: 'woocommerce' },
			'dataLayer'
		);
	} );

	it( 'routes through the configured dataLayer name', () => {
		const push = installPushSpy();
		window.gtmkit_settings = { datalayer_name: 'customDL' };
		setEngagementCookie( { event: 'login', method: 'wordpress' } );

		loadModule();

		expect( push ).toHaveBeenCalledWith(
			{ event: 'login', method: 'wordpress' },
			'customDL'
		);
	} );

	it( 'falls back to a direct dataLayer push when the gtmkit runtime is absent', () => {
		// No window.gtmkit at all (URL-excluded request or container off).
		setEngagementCookie( { event: 'login', method: 'wordpress' } );

		loadModule();

		expect( window.dataLayer ).toEqual( [
			{ event: 'login', method: 'wordpress' },
		] );
	} );

	it( 'ignores cookies whose payload is not an object with an event string', () => {
		const push = installPushSpy();
		setEngagementCookie( { method: 'wordpress' } );

		loadModule();

		expect( push ).not.toHaveBeenCalled();
	} );
} );
