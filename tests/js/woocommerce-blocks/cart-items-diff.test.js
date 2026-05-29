// @vitest-environment node
/**
 * Unit tests for the pure cart-items diff.
 */

import { describe, expect, it } from 'vitest';
import { diffCartItems } from '../../../src/js/frontend/woocommerce-blocks/diff/cart-items-diff.js';

const cartItem = ( key, quantity, id = key ) => ( {
	key,
	quantity,
	item: { item_id: id, price: 10 },
} );

describe( 'diffCartItems', () => {
	it( 'reports a new item as an addition of its full quantity', () => {
		const { added, removed } = diffCartItems( [], [ cartItem( 'a', 2 ) ] );

		expect( removed ).toEqual( [] );
		expect( added ).toHaveLength( 1 );
		expect( added[ 0 ].quantity ).toBe( 2 );
		expect( added[ 0 ].item.quantity ).toBe( 2 );
		expect( added[ 0 ].item.item_id ).toBe( 'a' );
	} );

	it( 'reports a quantity increase as the delta only', () => {
		const { added, removed } = diffCartItems(
			[ cartItem( 'a', 1 ) ],
			[ cartItem( 'a', 3 ) ]
		);

		expect( removed ).toEqual( [] );
		expect( added ).toHaveLength( 1 );
		expect( added[ 0 ].quantity ).toBe( 2 );
	} );

	it( 'reports a quantity decrease as a removal of the delta', () => {
		const { added, removed } = diffCartItems(
			[ cartItem( 'a', 3 ) ],
			[ cartItem( 'a', 1 ) ]
		);

		expect( added ).toEqual( [] );
		expect( removed ).toHaveLength( 1 );
		expect( removed[ 0 ].quantity ).toBe( 2 );
	} );

	it( 'reports a disappeared key as a full removal', () => {
		const { added, removed } = diffCartItems(
			[ cartItem( 'a', 2 ), cartItem( 'b', 1 ) ],
			[ cartItem( 'b', 1 ) ]
		);

		expect( added ).toEqual( [] );
		expect( removed ).toHaveLength( 1 );
		expect( removed[ 0 ].item.item_id ).toBe( 'a' );
		expect( removed[ 0 ].quantity ).toBe( 2 );
	} );

	it( 'emits nothing for an unchanged cart', () => {
		const snapshot = [ cartItem( 'a', 2 ), cartItem( 'b', 1 ) ];
		const { added, removed } = diffCartItems( snapshot, snapshot );

		expect( added ).toEqual( [] );
		expect( removed ).toEqual( [] );
	} );

	it( 'handles simultaneous addition and removal across keys', () => {
		const { added, removed } = diffCartItems(
			[ cartItem( 'a', 1 ) ],
			[ cartItem( 'b', 2 ) ]
		);

		expect( added ).toHaveLength( 1 );
		expect( added[ 0 ].item.item_id ).toBe( 'b' );
		expect( removed ).toHaveLength( 1 );
		expect( removed[ 0 ].item.item_id ).toBe( 'a' );
	} );

	it( 'does not mutate the input item objects', () => {
		const prev = [ cartItem( 'a', 1 ) ];
		const next = [ cartItem( 'a', 3 ) ];
		diffCartItems( prev, next );

		expect( next[ 0 ].item.quantity ).toBeUndefined();
	} );
} );
