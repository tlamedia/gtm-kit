/**
 * Mutual exclusivity between the Google tag gateway and a custom sGTM domain.
 *
 * Both serve the container from a domain the site owner controls, by different
 * means, and composing them would mean silently picking one. The registry
 * settles that by disabling each while the other is in use, so these assertions
 * read the shipped field definitions rather than a copy of their conditions:
 * a condition dropped or inverted in the registry fails here.
 */
import { isEnabled } from '../conditions';
import { SETUP_FIELDS } from '../fields/setup';

const field = ( key ) => {
	const found = SETUP_FIELDS.find( ( entry ) => entry.key === key );

	if ( ! found ) {
		throw new Error( `No registry field for ${ key }` );
	}

	return found;
};

const GATEWAY = 'general.google_tag_gateway';
const SGTM_DOMAIN = 'general.sgtm_domain';
const SGTM_IDENTIFIER = 'general.sgtm_container_identifier';
const COOKIE_KEEPER = 'general.sgtm_cookie_keeper';

/**
 * Build a settings store.
 *
 * @param {Object} general The general option group.
 * @return {Object} The store.
 */
const store = ( general ) => ( { general } );

const NEITHER = store( {
	google_tag_gateway: false,
	sgtm_domain: '',
	sgtm_container_identifier: '',
} );

const GATEWAY_ON = store( {
	google_tag_gateway: true,
	sgtm_domain: '',
	sgtm_container_identifier: '',
} );

const SGTM_ON = store( {
	google_tag_gateway: false,
	sgtm_domain: 'gtm.example.com',
	sgtm_container_identifier: 'abcd',
} );

describe( 'the Google tag gateway and a custom sGTM domain exclude each other', () => {
	it( 'offers both while neither is in use', () => {
		expect( isEnabled( field( GATEWAY ), NEITHER ) ).toBe( true );
		expect( isEnabled( field( SGTM_DOMAIN ), NEITHER ) ).toBe( true );
	} );

	it( 'disables the gateway while an sGTM domain is set', () => {
		expect( isEnabled( field( GATEWAY ), SGTM_ON ) ).toBe( false );
	} );

	it( 'disables the sGTM domain and identifier while the gateway is on', () => {
		expect( isEnabled( field( SGTM_DOMAIN ), GATEWAY_ON ) ).toBe( false );
		expect( isEnabled( field( SGTM_IDENTIFIER ), GATEWAY_ON ) ).toBe(
			false
		);
	} );

	it( 'releases the gateway again once the sGTM domain is cleared', () => {
		const cleared = store( {
			...SGTM_ON.general,
			sgtm_domain: '',
			sgtm_container_identifier: '',
		} );

		expect( isEnabled( field( GATEWAY ), cleared ) ).toBe( true );
	} );

	it( 'treats a whitespace-only sGTM domain as unset', () => {
		const blank = store( { ...NEITHER.general, sgtm_domain: '   ' } );

		expect( isEnabled( field( GATEWAY ), blank ) ).toBe( true );
	} );

	it( 'keeps the Cookie Keeper unavailable in gateway mode', () => {
		// Its own prerequisites are met, so only the gateway can hold it back.
		const both = store( {
			google_tag_gateway: true,
			sgtm_domain: 'gtm.example.com',
			sgtm_container_identifier: 'abcd',
		} );

		expect( isEnabled( field( COOKIE_KEEPER ), both ) ).toBe( false );
		expect( isEnabled( field( COOKIE_KEEPER ), SGTM_ON ) ).toBe( true );
	} );

	it( 'explains itself on both sides rather than greying out silently', () => {
		expect( field( GATEWAY ).description ).toMatch( /sGTM/i );
		expect( field( SGTM_DOMAIN ).description ).toMatch(
			/Google tag gateway/i
		);
	} );
} );
