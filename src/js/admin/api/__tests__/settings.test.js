/**
 * How the API client classifies a rejected request.
 *
 * api-fetch rejects a request that never reached the server with a code and a
 * translated message, so a lost connection has to be recognised by its code.
 */

/* eslint-disable import/no-extraneous-dependencies */

import apiFetch from '@wordpress/api-fetch';

import { probeHealth, refreshSgtmLoader, updateSettings } from '../settings';
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

describe( 'probeHealth nonce recovery', () => {
	const NONCE_ENDPOINT =
		'http://localhost/wp-admin/admin-ajax.php?action=rest-nonce';

	// An unparsed api-fetch failure: the raw response, readable more than once.
	const rawResponse = ( status, body ) => {
		const response = {
			ok: status >= 200 && status < 300,
			status,
			json: async () => body,
			text: async () => JSON.stringify( body ),
		};
		response.clone = () => response;
		return response;
	};

	const expiredNonce = () =>
		rawResponse( 403, {
			code: 'rest_cookie_invalid_nonce',
			message: 'Cookie check failed',
			data: { status: 403 },
		} );

	let originalFetch;

	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.nonceEndpoint = NONCE_ENDPOINT;
		apiFetch.nonceMiddleware = { nonce: 'stale-nonce' };
		originalFetch = window.fetch;
		window.fetch = jest.fn();
	} );

	afterEach( () => {
		window.fetch = originalFetch;
		delete apiFetch.nonceEndpoint;
		delete apiFetch.nonceMiddleware;
	} );

	it( 'refreshes an expired nonce once and retries the probe', async () => {
		const healthy = rawResponse( 200, { reachable: true } );
		apiFetch
			.mockRejectedValueOnce( expiredNonce() )
			.mockResolvedValueOnce( healthy );
		window.fetch.mockResolvedValue( {
			ok: true,
			text: async () => 'fresh-nonce',
		} );

		await expect( probeHealth() ).resolves.toBe( healthy );
		expect( window.fetch ).toHaveBeenCalledWith(
			NONCE_ENDPOINT,
			expect.any( Object )
		);
		expect( apiFetch.nonceMiddleware.nonce ).toBe( 'fresh-nonce' );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'reports the rejected nonce when no fresh one can be fetched', async () => {
		const rejected = expiredNonce();
		apiFetch.mockRejectedValueOnce( rejected );
		window.fetch.mockResolvedValue( { ok: false, text: async () => '0' } );

		await expect( probeHealth() ).rejects.toBe( rejected );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch.nonceMiddleware.nonce ).toBe( 'stale-nonce' );
	} );

	it( 'reports the rejected nonce when the refresh request itself fails', async () => {
		const rejected = expiredNonce();
		apiFetch.mockRejectedValueOnce( rejected );
		window.fetch.mockRejectedValue( new TypeError( 'Failed to fetch' ) );

		await expect( probeHealth() ).rejects.toBe( rejected );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'lets an abort during the refresh through as an abort', async () => {
		apiFetch.mockRejectedValueOnce( expiredNonce() );
		const aborted = new Error( 'The operation was aborted.' );
		aborted.name = 'AbortError';
		window.fetch.mockRejectedValue( aborted );

		await expect( probeHealth() ).rejects.toBe( aborted );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'retries only once when the fresh nonce is rejected too', async () => {
		const second = expiredNonce();
		apiFetch
			.mockRejectedValueOnce( expiredNonce() )
			.mockRejectedValueOnce( second );
		window.fetch.mockResolvedValue( {
			ok: true,
			text: async () => 'fresh-nonce',
		} );

		await expect( probeHealth() ).rejects.toBe( second );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it.each( [
		[ 'a 403 with another code', 403, { code: 'rest_forbidden' } ],
		[ 'a 401', 401, { code: 'rest_cookie_invalid_nonce' } ],
	] )( 'does not refresh the nonce for %s', async ( label, status, body ) => {
		const failure = rawResponse( status, body );
		apiFetch.mockRejectedValueOnce( failure );

		await expect( probeHealth() ).rejects.toBe( failure );
		expect( window.fetch ).not.toHaveBeenCalled();
	} );
} );
