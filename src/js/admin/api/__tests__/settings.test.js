/**
 * How the API client classifies a rejected request.
 *
 * api-fetch rejects a request that never reached the server with a code and a
 * translated message, so a lost connection has to be recognised by its code.
 */

/* eslint-disable import/no-extraneous-dependencies */

import apiFetch from '@wordpress/api-fetch';

import { refreshSgtmLoader, updateSettings } from '../settings';
import { APIError, NetworkError, ValidationError } from '../../utils/errors';

jest.mock( '@wordpress/api-fetch', () => {
	const mock = jest.fn();
	mock.use = jest.fn();
	mock.createNonceMiddleware = jest.fn();
	mock.createRootURLMiddleware = jest.fn();
	return { __esModule: true, default: mock };
} );

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

jest.mock( '../../utils/errorHandler', () => ( {
	logError: jest.fn(),
} ) );

describe( 'handleApiFetch error classification', () => {
	beforeEach( () => {
		apiFetch.mockReset();
	} );

	it.each( [ 'fetch_error', 'offline_error' ] )(
		'turns an api-fetch %s into a NetworkError',
		async ( code ) => {
			apiFetch.mockRejectedValue( {
				code,
				message: 'Could not get a valid response from the server.',
			} );

			await expect( refreshSgtmLoader() ).rejects.toBeInstanceOf(
				NetworkError
			);
		}
	);

	it.each( [ 'fetch_error', 'offline_error' ] )(
		'keeps the translated message api-fetch gives a %s',
		async ( code ) => {
			apiFetch.mockRejectedValue( {
				code,
				message: 'Impossible de joindre le serveur.',
			} );

			const error = await refreshSgtmLoader().catch( ( e ) => e );

			expect( error ).toBeInstanceOf( NetworkError );
			expect( error.message ).toBe( 'Impossible de joindre le serveur.' );
		}
	);

	it( 'names the endpoint when a network error carries no message', async () => {
		apiFetch.mockRejectedValue( { code: 'fetch_error' } );

		const error = await refreshSgtmLoader().catch( ( e ) => e );

		expect( error ).toBeInstanceOf( NetworkError );
		expect( error.message ).toMatch( /^Network error while calling / );
	} );

	it( 'does not show the browser text of a raw fetch failure', async () => {
		apiFetch.mockRejectedValue( new TypeError( 'Failed to fetch' ) );

		const error = await refreshSgtmLoader().catch( ( e ) => e );

		expect( error ).toBeInstanceOf( NetworkError );
		expect( error.message ).toMatch( /^Network error while calling / );
	} );

	it( 'turns a 400 rejection into a ValidationError', async () => {
		apiFetch.mockRejectedValue( {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): gtm_id',
			data: { status: 400, params: { gtm_id: 'Invalid' } },
		} );

		const error = await updateSettings( {} ).catch( ( e ) => e );

		expect( error ).toBeInstanceOf( ValidationError );
		expect( error.message ).toBe( 'Invalid parameter(s): gtm_id' );
	} );

	it( 'keeps the response of a refused nonce on the APIError', async () => {
		const rejection = {
			code: 'rest_cookie_invalid_nonce',
			message: 'Cookie check failed',
			data: { status: 403 },
		};
		apiFetch.mockRejectedValue( rejection );

		const error = await refreshSgtmLoader().catch( ( e ) => e );

		expect( error ).toBeInstanceOf( APIError );
		expect( error ).not.toBeInstanceOf( NetworkError );
		expect( error.response.code ).toBe( 'rest_cookie_invalid_nonce' );
		expect( error.response.data.status ).toBe( 403 );
	} );
} );
