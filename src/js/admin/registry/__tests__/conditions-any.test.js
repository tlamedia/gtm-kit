/**
 * The `any` operator holds when at least one of its conditions does, and
 * combines with the other entries of the same condition by AND.
 */
import { evaluateCondition } from '../conditions';

const settings = {
	general: { google_tag_gateway: true, sgtm_domain: '' },
};

describe( 'the any operator', () => {
	it( 'holds when one of its conditions holds', () => {
		expect(
			evaluateCondition(
				{
					any: [
						{ truthy: [ 'general.sgtm_domain' ] },
						{ truthy: [ 'general.google_tag_gateway' ] },
					],
				},
				settings
			)
		).toBe( true );
	} );

	it( 'fails when none of its conditions holds', () => {
		expect(
			evaluateCondition(
				{
					any: [
						{ truthy: [ 'general.sgtm_domain' ] },
						{ falsy: [ 'general.google_tag_gateway' ] },
					],
				},
				settings
			)
		).toBe( false );
	} );

	it( 'fails when it lists no conditions', () => {
		expect( evaluateCondition( { any: [] }, settings ) ).toBe( false );
	} );

	it( 'is ANDed with the other entries', () => {
		expect(
			evaluateCondition(
				{
					any: [ { truthy: [ 'general.google_tag_gateway' ] } ],
					truthy: [ 'general.sgtm_domain' ],
				},
				settings
			)
		).toBe( false );
	} );
} );
