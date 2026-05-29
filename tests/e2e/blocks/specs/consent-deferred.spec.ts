/**
 * Consent-deferred block events.
 *
 * With the block deferral sink registered (tests/e2e/blocks/fixtures/
 * deferral-sink/, a core stand-in for the Premium Event Deferral Queue),
 * block events must buffer while consent is denied, flush in original
 * order when consent is granted, and a later push must land immediately.
 *
 * Events are driven through the public `window.gtmkit.events.push` seam so
 * the assertion targets the deferral contract itself, independent of any
 * particular block's DOM behaviour.
 */

import { test, expect } from '@playwright/test';
import { readEventNames, waitForEvent } from './helpers';

test.describe( 'block events defer, flush, and drain on consent', () => {
	test( 'buffers under denied consent, flushes in order on grant, drains next push', async ( {
		page,
		context,
		baseURL,
	} ) => {
		const host = new URL( baseURL ?? 'http://localhost:8891' ).hostname;
		await context.addCookies( [
			{ name: 'gtmkit_e2e_deferral', value: '1', domain: host, path: '/' },
		] );

		await page.goto( '/mini-cart-page/' );

		// The sink and the consent surface are present, defaulting to denied.
		const surface = await page.evaluate( () => ( {
			hasShouldDefer:
				typeof ( window as unknown as {
					gtmkit?: { events?: { shouldDefer?: unknown } };
				} ).gtmkit?.events?.shouldDefer === 'function',
			analytics: ( window as unknown as {
				gtmkit?: { consent?: { state?: Record< string, string > } };
			} ).gtmkit?.consent?.state?.analytics_storage,
		} ) );
		expect( surface.hasShouldDefer ).toBe( true );
		expect( surface.analytics ).toBe( 'denied' );

		// Push two block events while consent is denied.
		await page.evaluate( () => {
			const w = window as unknown as {
				gtmkit: { events: { push: ( e: unknown, n?: string ) => void } };
			};
			w.gtmkit.events.push(
				{ event: 'add_to_cart', ecommerce: { value: 19.99 } },
				'dataLayer'
			);
			w.gtmkit.events.push(
				{ event: 'view_cart', ecommerce: { value: 19.99 } },
				'dataLayer'
			);
		} );

		await page.waitForTimeout( 100 );
		let names = await readEventNames( page );
		expect( names ).not.toContain( 'add_to_cart' );
		expect( names ).not.toContain( 'view_cart' );

		// Grant consent — the sink flushes the buffer in order.
		await page.evaluate( () => {
			const w = window as unknown as {
				gtmkit: { consent: { update: ( s: Record< string, string > ) => void } };
			};
			w.gtmkit.consent.update( {
				analytics_storage: 'granted',
				ad_storage: 'granted',
			} );
		} );

		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );

		names = await readEventNames( page );
		const addIdx = names.indexOf( 'add_to_cart' );
		const viewIdx = names.indexOf( 'view_cart' );
		expect( addIdx ).toBeGreaterThanOrEqual( 0 );
		expect( viewIdx ).toBeGreaterThanOrEqual( 0 );
		expect( addIdx ).toBeLessThan( viewIdx );

		// A push after the grant lands immediately (queue drained).
		await page.evaluate( () => {
			const w = window as unknown as {
				gtmkit: { events: { push: ( e: unknown, n?: string ) => void } };
			};
			w.gtmkit.events.push(
				{ event: 'select_item', ecommerce: {} },
				'dataLayer'
			);
		} );
		await page.waitForTimeout( 100 );
		expect( await readEventNames( page ) ).toContain( 'select_item' );
	} );
} );
