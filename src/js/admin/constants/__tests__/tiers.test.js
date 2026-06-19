/**
 * Access-tier constants and gate-comparison unit tests.
 */

import { TIERS, tierRank, meetsRequiredTier } from '../tiers';

describe( 'tiers', () => {
	describe( 'tierRank', () => {
		it( 'orders free < woo < premium', () => {
			expect( tierRank( TIERS.FREE ) ).toBeLessThan(
				tierRank( TIERS.WOO )
			);
			expect( tierRank( TIERS.WOO ) ).toBeLessThan(
				tierRank( TIERS.PREMIUM )
			);
		} );

		it( 'falls back to the free rank for an unknown tier', () => {
			expect( tierRank( 'enterprise' ) ).toBe( tierRank( TIERS.FREE ) );
			expect( tierRank( undefined ) ).toBe( tierRank( TIERS.FREE ) );
		} );
	} );

	describe( 'meetsRequiredTier', () => {
		// Full activeTier x requiredTier matrix.
		const cases = [
			[ TIERS.FREE, TIERS.FREE, true ],
			[ TIERS.FREE, TIERS.WOO, false ],
			[ TIERS.FREE, TIERS.PREMIUM, false ],
			[ TIERS.WOO, TIERS.FREE, true ],
			[ TIERS.WOO, TIERS.WOO, true ],
			[ TIERS.WOO, TIERS.PREMIUM, false ],
			[ TIERS.PREMIUM, TIERS.FREE, true ],
			[ TIERS.PREMIUM, TIERS.WOO, true ],
			[ TIERS.PREMIUM, TIERS.PREMIUM, true ],
		];

		it.each( cases )(
			'active %s vs required %s -> %s',
			( active, required, expected ) => {
				expect( meetsRequiredTier( active, required ) ).toBe(
					expected
				);
			}
		);

		it( 'gates a Woo user out of a Premium-only section (the newly-correct case)', () => {
			expect( meetsRequiredTier( TIERS.WOO, TIERS.PREMIUM ) ).toBe(
				false
			);
		} );

		it( 'keeps a Woo user in a Woo section', () => {
			expect( meetsRequiredTier( TIERS.WOO, TIERS.WOO ) ).toBe( true );
		} );

		it( 'treats a missing required tier as free (never gated)', () => {
			expect( meetsRequiredTier( TIERS.FREE ) ).toBe( true );
			expect( meetsRequiredTier( TIERS.WOO ) ).toBe( true );
			expect( meetsRequiredTier( TIERS.PREMIUM ) ).toBe( true );
		} );
	} );
} );
