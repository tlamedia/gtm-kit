// @vitest-environment node
/**
 * Tests for the dismissal helper used by the introductions modal.
 *
 * Verifies that the helper posts to the seen-state route with the
 * correct URL, method, and headers, and that failures resolve to false
 * rather than rejecting.
 *
 * Target: src/js/admin/introductions/dismiss.js — `dismissIntroduction`.
 */

import { describe, expect, it, vi } from 'vitest';
import { dismissIntroduction } from '../../../src/js/admin/introductions/dismiss.js';

describe( 'dismissIntroduction', () => {
	it( 'posts to the seen route with the X-WP-Nonce header', async () => {
		const fetchImpl = vi.fn().mockResolvedValue( { ok: true } );

		const result = await dismissIntroduction( 'welcome-3.0', {
			restRoot: 'https://example.test/wp-json/gtmkit/v1',
			nonce: 'test-nonce',
			fetchImpl,
		} );

		expect( result ).toBe( true );
		expect( fetchImpl ).toHaveBeenCalledTimes( 1 );

		const [ url, init ] = fetchImpl.mock.calls[ 0 ];
		expect( url ).toBe(
			'https://example.test/wp-json/gtmkit/v1/introductions/welcome-3.0/seen'
		);
		expect( init.method ).toBe( 'POST' );
		expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
		expect( init.credentials ).toBe( 'same-origin' );
	} );

	it( 'resolves to false when fetch rejects', async () => {
		const fetchImpl = vi.fn().mockRejectedValue( new Error( 'network down' ) );

		const result = await dismissIntroduction( 'welcome-3.0', {
			restRoot: 'https://example.test/wp-json/gtmkit/v1',
			nonce: 'test-nonce',
			fetchImpl,
		} );

		expect( result ).toBe( false );
	} );

	it( 'resolves to false on a non-2xx response', async () => {
		const fetchImpl = vi.fn().mockResolvedValue( { ok: false } );

		const result = await dismissIntroduction( 'welcome-3.0', {
			restRoot: 'https://example.test/wp-json/gtmkit/v1',
			nonce: 'test-nonce',
			fetchImpl,
		} );

		expect( result ).toBe( false );
	} );

	it( 'returns false for a missing intro id or rest root', async () => {
		const fetchImpl = vi.fn();

		expect(
			await dismissIntroduction( '', { restRoot: 'x', nonce: 'n', fetchImpl } )
		).toBe( false );
		expect(
			await dismissIntroduction( 'welcome', { restRoot: '', nonce: 'n', fetchImpl } )
		).toBe( false );
		expect( fetchImpl ).not.toHaveBeenCalled();
	} );
} );
