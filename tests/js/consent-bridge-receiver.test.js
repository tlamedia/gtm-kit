// @vitest-environment jsdom
/**
 * Vitest coverage for the consent bridge receiver in the head inline script.
 *
 * GTM Kit's consent bridge template, a Google Tag Manager template, reports
 * Google's consent state into `window.gtmkit.consentBridge.report()`. The
 * receiver records what it is told at once and announces it with
 * `gtmkit:consent:bridged` on a fresh task, and nothing more: it is not a
 * Consent Mode writer.
 *
 * @module tests/js/consent-bridge-receiver.test.js
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadReceiver } from './consent-bridge-receiver';

const TYPES = [
	'ad_storage',
	'analytics_storage',
	'ad_user_data',
	'ad_personalization',
	'functionality_storage',
	'personalization_storage',
	'security_storage',
];

describe( 'the consent bridge receiver', () => {
	let bridged;
	let updated;

	const bridge = () => window.gtmkit.consentBridge;

	beforeEach( () => {
		vi.useFakeTimers();
		delete window.gtmkit;
		delete window.gtag;
		window.gtmkit = {};
		loadReceiver();
		bridged = vi.fn();
		updated = vi.fn();
		window.addEventListener( 'gtmkit:consent:bridged', bridged );
		window.addEventListener( 'gtmkit:consent:updated', updated );
	} );

	afterEach( () => {
		window.removeEventListener( 'gtmkit:consent:bridged', bridged );
		window.removeEventListener( 'gtmkit:consent:updated', updated );
		vi.useRealTimers();
	} );

	it( 'is defined with an empty state and basis', () => {
		expect( typeof bridge().report ).toBe( 'function' );
		expect( bridge().state ).toEqual( {} );
		expect( bridge().basis ).toEqual( {} );
	} );

	it( 'is defined beside GTM Kit consent state as well as without it', () => {
		delete window.gtmkit;
		window.gtmkit = {
			consent: { state: { ad_storage: 'denied' }, update() {} },
		};
		loadReceiver();

		expect( typeof bridge().report ).toBe( 'function' );
		expect( window.gtmkit.consent.state ).toEqual( { ad_storage: 'denied' } );
	} );

	it( 'keeps a receiver that is already defined', () => {
		const existing = bridge();
		loadReceiver();
		expect( bridge() ).toBe( existing );
	} );

	it( 'records a report at once and announces it on a fresh task', () => {
		bridge().report( 'ad_storage', true, 'initial' );

		expect( bridge().state ).toEqual( { ad_storage: 'granted' } );
		expect( bridge().basis ).toEqual( { ad_storage: 'initial' } );
		expect( bridged ).not.toHaveBeenCalled();

		vi.runAllTimers();

		expect( bridged ).toHaveBeenCalledTimes( 1 );
		expect( bridged.mock.calls[ 0 ][ 0 ].detail ).toEqual( {
			type: 'ad_storage',
			value: 'granted',
			basis: 'initial',
		} );

		bridge().report( 'ad_user_data', false, 'change' );

		expect( bridge().state ).toEqual( {
			ad_storage: 'granted',
			ad_user_data: 'denied',
		} );
		expect( bridge().basis ).toEqual( {
			ad_storage: 'initial',
			ad_user_data: 'change',
		} );
		expect( bridged ).toHaveBeenCalledTimes( 1 );

		vi.runAllTimers();

		expect( bridged ).toHaveBeenCalledTimes( 2 );
		expect( bridged.mock.calls[ 1 ][ 0 ].detail ).toEqual( {
			type: 'ad_user_data',
			value: 'denied',
			basis: 'change',
		} );
	} );

	it( 'announces reports made in a row in order, each with its own value', () => {
		bridge().report( 'ad_storage', false, 'initial' );
		bridge().report( 'ad_storage', true, 'change' );

		expect( bridge().state.ad_storage ).toBe( 'granted' );
		expect( bridged ).not.toHaveBeenCalled();

		vi.runAllTimers();

		expect( bridged.mock.calls.map( ( call ) => call[ 0 ].detail ) ).toEqual(
			[
				{ type: 'ad_storage', value: 'denied', basis: 'initial' },
				{ type: 'ad_storage', value: 'granted', basis: 'change' },
			]
		);
	} );

	it.each( TYPES )( 'accepts a report for %s', ( type ) => {
		bridge().report( type, true, 'change' );
		expect( bridge().state[ type ] ).toBe( 'granted' );
	} );

	it.each( [
		[ 'an unknown type', [ 'cookie_storage', true, 'change' ] ],
		[ 'a type that is not a string', [ undefined, true, 'change' ] ],
		[ 'a value that is not a boolean', [ 'ad_storage', 'granted', 'change' ] ],
		[ 'a missing value', [ 'ad_storage', undefined, 'change' ] ],
		[ 'an unknown basis', [ 'ad_storage', true, 'default' ] ],
		[ 'a missing basis', [ 'ad_storage', true ] ],
		[ 'no arguments', [] ],
	] )( 'ignores %s without throwing', ( _, args ) => {
		expect( () => bridge().report( ...args ) ).not.toThrow();
		vi.runAllTimers();

		expect( bridge().state ).toEqual( {} );
		expect( bridge().basis ).toEqual( {} );
		expect( bridged ).not.toHaveBeenCalled();
	} );

	it( 'keeps an answer when the initial state is reported afterwards', () => {
		bridge().report( 'ad_storage', true, 'change' );
		bridge().report( 'ad_storage', false, 'initial' );
		vi.runAllTimers();

		expect( bridge().state.ad_storage ).toBe( 'granted' );
		expect( bridge().basis.ad_storage ).toBe( 'change' );
		expect( bridged ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'lets an answer replace the initial state, and a later answer the earlier one', () => {
		bridge().report( 'ad_storage', false, 'initial' );
		bridge().report( 'ad_storage', true, 'initial' );
		expect( bridge().state.ad_storage ).toBe( 'granted' );
		expect( bridge().basis.ad_storage ).toBe( 'initial' );

		bridge().report( 'ad_storage', false, 'change' );
		expect( bridge().state.ad_storage ).toBe( 'denied' );
		expect( bridge().basis.ad_storage ).toBe( 'change' );

		bridge().report( 'ad_storage', true, 'change' );
		expect( bridge().state.ad_storage ).toBe( 'granted' );
		expect( bridge().basis.ad_storage ).toBe( 'change' );

		vi.runAllTimers();
		expect( bridged ).toHaveBeenCalledTimes( 4 );
	} );

	it( 'only observes: no gtag call, no change to GTM Kit consent state, no GTM Kit consent event', () => {
		window.gtag = vi.fn();
		window.dataLayer = [];
		window.gtmkit.consent = {
			state: { ad_storage: 'denied', ad_user_data: 'denied' },
			update: vi.fn(),
		};
		const before = JSON.stringify( window.gtmkit.consent.state );

		bridge().report( 'ad_storage', true, 'change' );
		bridge().report( 'ad_user_data', true, 'initial' );
		bridge().report( 'ad_storage', false, 'change' );
		vi.runAllTimers();

		expect( window.gtag ).not.toHaveBeenCalled();
		expect( window.dataLayer ).toEqual( [] );
		expect( window.gtmkit.consent.update ).not.toHaveBeenCalled();
		expect( JSON.stringify( window.gtmkit.consent.state ) ).toBe( before );
		expect( updated ).not.toHaveBeenCalled();
		expect( bridged ).toHaveBeenCalledTimes( 3 );
	} );
} );
