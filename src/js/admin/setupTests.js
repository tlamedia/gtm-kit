/**
 * Vitest setup for the settings app.
 *
 * Runs before each test file in the `settings` project.
 */

import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, expect, vi } from 'vitest';

// Test globals are off, so Testing Library cannot register its own cleanup.
afterEach( cleanup );

// jsdom has no matchMedia, and @wordpress/components reads it for its
// responsive layout values.
window.matchMedia = ( query ) => ( {
	matches: false,
	media: query,
	onchange: null,
	addListener: () => {},
	removeListener: () => {},
	addEventListener: () => {},
	removeEventListener: () => {},
	dispatchEvent: () => false,
} );

/**
 * Fail any test that writes to the console without asserting it.
 *
 * A React warning or a logged error is usually a real bug, so console output
 * has to be acknowledged with the matching matcher, for example
 * `expect( console ).toHaveWarned()`. The calls are silenced either way.
 */
const CONSOLE_MATCHERS = {
	error: 'toHaveErrored',
	info: 'toHaveInformed',
	log: 'toHaveLogged',
	warn: 'toHaveWarned',
};
// Report jsdom's own errors, such as an unimplemented navigation, on the
// console the test sees. Vitest replaces `console` after jsdom starts, so its
// built-in forwarding would write to a console no spy is watching.
globalThis.jsdom.virtualConsole.on( 'jsdomError', ( error ) =>
	// eslint-disable-next-line no-console
	console.error( error.message )
);

const consoleSpies = Object.fromEntries(
	Object.keys( CONSOLE_MATCHERS ).map( ( method ) => [
		method,
		vi.spyOn( console, method ).mockImplementation( () => {} ),
	] )
);
const acknowledged = new Set();

afterEach( () => {
	const unexpected = [];
	for ( const [ method, matcher ] of Object.entries( CONSOLE_MATCHERS ) ) {
		const { calls } = consoleSpies[ method ].mock;
		if ( calls.length && ! acknowledged.has( method ) ) {
			unexpected.push(
				`console.${ method }() was called without expect( console ).${ matcher }():\n` +
					calls.map( ( args ) => args.join( ' ' ) ).join( '\n' )
			);
		}
		consoleSpies[ method ].mockClear();
	}
	acknowledged.clear();
	if ( unexpected.length ) {
		throw new Error( unexpected.join( '\n\n' ) );
	}
} );

expect.extend(
	Object.fromEntries(
		Object.entries( CONSOLE_MATCHERS ).map( ( [ method, matcher ] ) => [
			matcher,
			() => {
				acknowledged.add( method );
				const pass = consoleSpies[ method ].mock.calls.length > 0;
				return {
					pass,
					message: () =>
						`expected console.${ method }() ${
							pass ? 'not ' : ''
						}to have been called`,
				};
			},
		] )
	)
);

/**
 * Mock window.gtmkitSettings
 *
 * This global object is normally provided by PHP via wp_localize_script.
 * We mock it here so tests can run without WordPress.
 */
window.gtmkitSettings = {
	settings: {
		general: {},
		integrations: {},
		premium: {},
	},
	site_data: {
		site_url: 'http://localhost',
		admin_url: 'http://localhost/wp-admin',
	},
	notifications: {
		metrics: {
			total: 0,
			problem: 0,
		},
	},
	isPremium: false,
	hasValidLicense: false,
	plugins: {},
	user_roles: [],
	taxonomyOptions: [],
	templates: {},
	nonce: 'test-nonce',
	root: 'http://localhost/wp-json/',
	rootId: 'gtmkit-settings',
	adminPageUrl: 'http://localhost/wp-admin/admin.php?page=gtmkit_general',
	currentPage: 'general',
};
