// @vitest-environment jsdom
/**
 * Shared cart snapshot: storage, reset and the window handle.
 */

import { beforeEach, describe, expect, it } from 'vitest';
import {
	getCartSnapshot,
	setCartSnapshot,
	resetCartSnapshot,
	installCartSnapshot,
} from '../../../src/js/frontend/woocommerce-blocks/cart-snapshot.js';

describe( 'cart snapshot', () => {
	beforeEach( () => {
		resetCartSnapshot();
		delete window.gtmkit;
	} );

	it( 'starts empty, stores arrays only, and resets', () => {
		expect( getCartSnapshot() ).toBeNull();
		setCartSnapshot( [ { key: 'a', quantity: 1 } ] );
		expect( getCartSnapshot() ).toHaveLength( 1 );
		setCartSnapshot( 'nope' );
		expect( getCartSnapshot() ).toBeNull();
		setCartSnapshot( [] );
		expect( getCartSnapshot() ).toEqual( [] );
		resetCartSnapshot();
		expect( getCartSnapshot() ).toBeNull();
	} );

	it( 'installs a handle on window.gtmkit.wc that reads and writes the snapshot', () => {
		const handle = installCartSnapshot();
		expect( window.gtmkit.wc.cartSnapshot ).toBe( handle );
		handle.set( [ { key: 'b', quantity: 2 } ] );
		expect( getCartSnapshot() ).toEqual( [ { key: 'b', quantity: 2 } ] );
		expect( handle.get() ).toEqual( [ { key: 'b', quantity: 2 } ] );
	} );

	it( 'adopts a handle another script installed first', () => {
		let own = null;
		const foreign = {
			get: () => own,
			set: ( items ) => {
				own = items;
			},
		};
		window.gtmkit = { wc: { cartSnapshot: foreign } };

		const handle = installCartSnapshot();
		expect( handle ).toBe( foreign );
		handle.set( [ { key: 'c', quantity: 1 } ] );
		expect( own ).toHaveLength( 1 );
		expect( getCartSnapshot() ).toBeNull();
	} );

	it( 'keeps other gtmkit.wc members intact', () => {
		window.gtmkit = { wc: { item: () => 'x' }, events: {} };
		installCartSnapshot();
		expect( window.gtmkit.wc.item() ).toBe( 'x' );
		expect( typeof window.gtmkit.events ).toBe( 'object' );
	} );
} );
