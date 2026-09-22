/**
 * The Stape loader readout's failure wording.
 *
 * A failed refresh or paste never removes a stored loader, so the fallback
 * sentence has to name the loader still in use, and a request that throws
 * has to be explained by its real cause rather than always as Stape being
 * unreachable.
 */
import {
	describeSgtmLoaderFailure,
	describeSgtmLoaderFallback,
	getSgtmLoaderErrorReason,
} from '../blocks/sgtmLoaderMessages';
import { APIError, NetworkError } from '../../utils/errors';

describe( 'describeSgtmLoaderFallback', () => {
	it( 'keeps the loader Stape issued when one is stored', () => {
		const sentence = describeSgtmLoaderFallback( 'api' );

		expect( sentence ).toContain( 'loader Stape issued earlier' );
		expect( sentence ).not.toContain( 'standard loader' );
	} );

	it( 'keeps the pasted loader when one is stored', () => {
		const sentence = describeSgtmLoaderFallback( 'pasted' );

		expect( sentence ).toContain( 'loader you pasted earlier' );
		expect( sentence ).not.toContain( 'standard loader' );
	} );

	it.each( [ 'standard', undefined, '' ] )(
		'keeps the standard loader when none is stored (%p)',
		( source ) => {
			expect( describeSgtmLoaderFallback( source ) ).toBe(
				'Your pages keep the standard loader, which still works. Try again, or paste the code Stape shows for your container.'
			);
		}
	);
} );

describe( 'getSgtmLoaderErrorReason', () => {
	it( 'reads a NetworkError as a lost connection', () => {
		expect(
			getSgtmLoaderErrorReason( new NetworkError( 'Network error' ) )
		).toBe( 'connection' );
	} );

	it( 'reads a refused nonce as an expired session', () => {
		const error = new APIError( 'Cookie check failed', {
			code: 'rest_cookie_invalid_nonce',
			message: 'Cookie check failed',
			data: { status: 403 },
		} );

		expect( getSgtmLoaderErrorReason( error ) ).toBe( 'session' );
	} );

	it( 'reads a logged-out request as an expired session', () => {
		const error = new APIError( 'Sorry', {
			code: 'rest_forbidden',
			data: { status: 401 },
		} );

		expect( getSgtmLoaderErrorReason( error ) ).toBe( 'session' );
	} );

	it.each( [
		[
			'a non-JSON response',
			new APIError( 'Not JSON', { code: 'invalid_json' } ),
		],
		[
			'a server error',
			new APIError( 'Error', {
				code: 'internal_server_error',
				data: { status: 500 },
			} ),
		],
		[ 'an error without a response', new APIError( 'Failed' ) ],
		[ 'a plain Error', new Error( 'Boom' ) ],
	] )( 'reads %s as unexpected', ( label, error ) => {
		expect( getSgtmLoaderErrorReason( error ) ).toBe( 'unexpected' );
	} );

	it( 'never reports a thrown error as Stape being unreachable', () => {
		const errors = [
			new NetworkError( 'Network error' ),
			new APIError( 'Cookie check failed', {
				code: 'rest_cookie_invalid_nonce',
				data: { status: 403 },
			} ),
			new APIError( 'Failed' ),
		];

		errors.forEach( ( error ) => {
			expect( getSgtmLoaderErrorReason( error ) ).not.toBe( 'network' );
		} );
	} );
} );

describe( 'describeSgtmLoaderFailure', () => {
	it( 'explains each derived reason without blaming Stape', () => {
		[ 'connection', 'session', 'unexpected' ].forEach( ( reason ) => {
			expect( describeSgtmLoaderFailure( reason ) ).not.toBe(
				'GTM Kit could not reach Stape.'
			);
		} );
	} );

	it( 'still blames Stape for a server-reported network failure', () => {
		expect( describeSgtmLoaderFailure( 'network' ) ).toBe(
			'GTM Kit could not reach Stape.'
		);
	} );

	it( 'explains a pasted loader made for another data layer name', () => {
		expect( describeSgtmLoaderFailure( 'datalayer_mismatch' ) ).toBe(
			'This code was made for a different data layer name than the one GTM Kit uses. Change the data layer name in Stape to match, then copy and paste the new code.'
		);
	} );

	it( 'keeps the HTTP status in a Stape error', () => {
		expect( describeSgtmLoaderFailure( 'http_500' ) ).toBe(
			'Stape answered with an error (HTTP 500).'
		);
	} );
} );
