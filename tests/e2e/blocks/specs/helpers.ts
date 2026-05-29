/**
 * Shared dataLayer helpers for the block E2E specs.
 */

import type { Page } from '@playwright/test';

interface DataLayerEntry {
	event?: string;
	ecommerce?: Record< string, unknown >;
	[ key: string ]: unknown;
}

/**
 * Read the events currently on window.dataLayer (entries with an `event` key).
 */
export async function readEvents( page: Page ): Promise< DataLayerEntry[] > {
	try {
		return await page.evaluate( () => {
			const layer = ( window as unknown as { dataLayer?: unknown[] } ).dataLayer ?? [];
			return layer.filter(
				( entry ): entry is DataLayerEntry =>
					!! entry &&
					typeof entry === 'object' &&
					typeof ( entry as { event?: unknown } ).event === 'string'
			);
		} );
	} catch ( e ) {
		// A navigation can destroy the execution context mid-evaluate
		// (e.g. checkout → thank-you). Treat that as "no events yet"; the
		// caller polls and will re-read on the settled page.
		return [];
	}
}

/**
 * The event names on window.dataLayer, in order.
 */
export async function readEventNames( page: Page ): Promise< string[] > {
	return ( await readEvents( page ) ).map( ( e ) => e.event as string );
}

/**
 * Poll until an event with the given name appears, or the timeout elapses.
 */
export async function waitForEvent(
	page: Page,
	name: string,
	timeoutMs = 5_000
): Promise< boolean > {
	const deadline = Date.now() + timeoutMs;
	while ( Date.now() < deadline ) {
		if ( ( await readEventNames( page ) ).includes( name ) ) {
			return true;
		}
		await page.waitForTimeout( 100 );
	}
	return false;
}

/**
 * Count how many times an event name appears on the dataLayer.
 */
export async function countEvent( page: Page, name: string ): Promise< number > {
	return ( await readEventNames( page ) ).filter( ( e ) => e === name ).length;
}

/**
 * The most recent event with the given name.
 */
export async function latestEvent(
	page: Page,
	name: string
): Promise< DataLayerEntry | undefined > {
	const matches = ( await readEvents( page ) ).filter( ( e ) => e.event === name );
	return matches[ matches.length - 1 ];
}
