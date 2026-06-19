/**
 * Field gating predicates.
 *
 * Covers the license-tier lock and the stale-stub safeguard (a licensed-for
 * field still served as a stub because the providing add-on is too old to
 * register), which the free dev environment cannot exercise directly.
 */
import { isTierLocked, isStaleStub } from '../gating';

// Active tier = woo: meets free and woo, not premium.
const meetsWoo = ( tier ) =>
	tier === 'free' || tier === 'woo' || tier === undefined;
// Active tier = free: meets only free.
const meetsFree = ( tier ) => tier === 'free' || tier === undefined;

describe( 'isTierLocked', () => {
	it( 'locks a woo field for a free license', () => {
		expect( isTierLocked( { tier: 'woo' }, meetsFree ) ).toBe( true );
	} );

	it( 'does not lock a woo field for a woo license', () => {
		expect( isTierLocked( { tier: 'woo' }, meetsWoo ) ).toBe( false );
	} );

	it( 'never locks a free field', () => {
		expect( isTierLocked( { tier: 'free' }, meetsFree ) ).toBe( false );
	} );
} );

describe( 'isStaleStub', () => {
	it( 'flags a stub the license qualifies for (outdated add-on)', () => {
		expect( isStaleStub( { tier: 'woo', stub: true }, meetsWoo ) ).toBe(
			true
		);
	} );

	it( 'does not flag a stub the license does not reach (normal upsell)', () => {
		expect( isStaleStub( { tier: 'woo', stub: true }, meetsFree ) ).toBe(
			false
		);
	} );

	it( 'does not flag a real (registered) field', () => {
		expect( isStaleStub( { tier: 'woo' }, meetsWoo ) ).toBe( false );
	} );

	it( 'does not flag a superseded field even if stub was true upstream', () => {
		// A real field never carries stub: true; only the stub does.
		expect( isStaleStub( { tier: 'woo', stub: false }, meetsWoo ) ).toBe(
			false
		);
	} );
} );
