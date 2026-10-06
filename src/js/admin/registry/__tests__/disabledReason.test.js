/**
 * A field's `disabledReason` is shown only while its `enabledWhen` disables it.
 */
import { getDisabledReason } from '../conditions';
import { getAllFields } from '../assemble';

const getField = ( key ) =>
	getAllFields().find( ( entry ) => entry.key === key );

const field = {
	key: 'general.sgtm_domain',
	disabledReason: 'Not available while the gateway is on.',
	enabledWhen: { falsy: [ 'general.google_tag_gateway' ] },
};

describe( 'getDisabledReason', () => {
	it( 'returns the reason while the condition disables the field', () => {
		expect(
			getDisabledReason( field, {
				general: { google_tag_gateway: true },
			} )
		).toBe( 'Not available while the gateway is on.' );
	} );

	it( 'returns nothing while the field is enabled', () => {
		expect(
			getDisabledReason( field, {
				general: { google_tag_gateway: false },
			} )
		).toBe( '' );
	} );

	it( 'returns nothing for a disabled field that declares no reason', () => {
		const { disabledReason, ...withoutReason } = field;
		expect( disabledReason ).toBeTruthy();
		expect(
			getDisabledReason( withoutReason, {
				general: { google_tag_gateway: true },
			} )
		).toBe( '' );
	} );
} );

describe( 'the gateway and sGTM domain fields', () => {
	it.each( [
		[ 'general.google_tag_gateway', { sgtm_domain: 'data.example.com' } ],
		[ 'general.sgtm_domain', { google_tag_gateway: true } ],
	] )(
		'%s explains itself only while the other setting disables it',
		( key, blocking ) => {
			const entry = getField( key );
			expect( entry.description ?? '' ).not.toMatch( /Not available/ );
			expect( getDisabledReason( entry, { general: blocking } ) ).toMatch(
				/Not available/
			);
			expect( getDisabledReason( entry, { general: {} } ) ).toBe( '' );
		}
	);
} );
