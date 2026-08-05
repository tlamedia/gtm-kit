/**
 * Click-add registry: the units-already-reported handoff between a block
 * UI's click handler and the cart-store subscriber.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import {
	recordClickAdd,
	claimClickAdd,
	clearClickAdds,
} from '../../../src/js/frontend/woocommerce-blocks/click-add-registry.js';

describe( 'click-add registry', () => {
	beforeEach( () => {
		clearClickAdds();
	} );

	it( 'hands recorded units to the claimer', () => {
		recordClickAdd( 'prefix_123', 1 );

		expect( claimClickAdd( 'prefix_123' ) ).toBe( 1 );
	} );

	it( 'claims are one-shot', () => {
		recordClickAdd( 'prefix_123', 1 );

		claimClickAdd( 'prefix_123' );

		expect( claimClickAdd( 'prefix_123' ) ).toBe( 0 );
	} );

	it( 'returns zero for an item nothing was recorded for', () => {
		expect( claimClickAdd( 'unknown' ) ).toBe( 0 );
	} );

	it( 'accumulates rapid repeat clicks on the same item', () => {
		recordClickAdd( 'prefix_123', 1 );
		recordClickAdd( 'prefix_123', 1 );

		expect( claimClickAdd( 'prefix_123' ) ).toBe( 2 );
	} );

	it( 'expires an entry past the TTL, so an add that never reached the cart cannot swallow a later one', () => {
		const t0 = 1_000_000;
		recordClickAdd( 'prefix_123', 1, t0 );

		expect( claimClickAdd( 'prefix_123', 1, t0 + 301_000 ) ).toBe( 0 );
	} );

	it( 'keeps an entry within the TTL', () => {
		const t0 = 1_000_000;
		recordClickAdd( 'prefix_123', 1, t0 );

		expect( claimClickAdd( 'prefix_123', 1, t0 + 299_000 ) ).toBe( 1 );
	} );

	it( 'claims only the units being confirmed and keeps the surplus', () => {
		recordClickAdd( 'prefix_123', 1 );
		recordClickAdd( 'prefix_123', 1 );

		expect( claimClickAdd( 'prefix_123', 1 ) ).toBe( 1 );
		expect( claimClickAdd( 'prefix_123', 1 ) ).toBe( 1 );
		expect( claimClickAdd( 'prefix_123', 1 ) ).toBe( 0 );
	} );

	it( 'caps a claim at the units actually recorded', () => {
		recordClickAdd( 'prefix_123', 1 );

		expect( claimClickAdd( 'prefix_123', 5 ) ).toBe( 1 );
	} );

	it( 'expires surplus units on the clock of the click that recorded them', () => {
		const t0 = 1_000_000;
		recordClickAdd( 'prefix_123', 2, t0 );

		expect( claimClickAdd( 'prefix_123', 1, t0 + 1000 ) ).toBe( 1 );
		expect( claimClickAdd( 'prefix_123', 1, t0 + 301_000 ) ).toBe( 0 );
	} );

	it( 'an expired entry does not inflate a fresh record', () => {
		const t0 = 1_000_000;
		recordClickAdd( 'prefix_123', 1, t0 );
		recordClickAdd( 'prefix_123', 1, t0 + 301_000 );

		expect( claimClickAdd( 'prefix_123', 1, t0 + 301_000 ) ).toBe( 1 );
	} );

	it( 'ignores unusable ids and non-positive quantities', () => {
		recordClickAdd( undefined, 1 );
		recordClickAdd( 'prefix_123', 0 );

		expect( claimClickAdd( '' ) ).toBe( 0 );
		expect( claimClickAdd( 'prefix_123' ) ).toBe( 0 );
	} );
} );
