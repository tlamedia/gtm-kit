/*WordPress*/
import apiFetch from '@wordpress/api-fetch';
import SettingsService from '../services/SettingsService';
import { APIError, NetworkError, ValidationError } from '../utils/errors';
import { logError } from '../utils/errorHandler';

apiFetch.use( apiFetch.createNonceMiddleware( SettingsService.getNonce() ) );
apiFetch.use(
	apiFetch.createRootURLMiddleware( SettingsService.getRestRoot() )
);

/**
 * Handle API fetch with proper error handling
 *
 * @param {string}      path         - API endpoint path
 * @param {string}      method       - HTTP method (GET, POST, etc.)
 * @param {Object|null} data         - Request data
 * @param {boolean}     returnObject - Return full response object
 * @return {Promise<Object>} API response
 * @throws {APIError} API request failed
 * @throws {NetworkError} Network error occurred
 * @throws {ValidationError} Validation error occurred
 */
const handleApiFetch = async (
	path,
	method,
	data = null,
	returnObject = false
) => {
	try {
		const response = await apiFetch( {
			path,
			method,
			...( data && { data } ),
		} );

		if ( returnObject === true ) {
			return response;
		} else if ( response.success === true ) {
			return response.data;
		}

		// If response is not successful, treat it as an error
		throw new APIError(
			response.message || 'API request failed',
			response
		);
	} catch ( error ) {
		// Log error for debugging
		logError( error, { path, method, data } );

		// Network errors (fetch failed, no response). api-fetch reports them
		// by code, with a translated message.
		if (
			[ 'fetch_error', 'offline_error' ].includes( error.code ) ||
			error.message?.includes( 'NetworkError' ) ||
			error.message?.includes( 'Failed to fetch' )
		) {
			throw new NetworkError(
				( error.code && error.message ) ||
					`Network error while calling ${ path }`
			);
		}

		// Validation errors (400 status)
		if (
			error.code === 'rest_invalid_param' ||
			error.data?.status === 400
		) {
			throw new ValidationError(
				error.message || 'Validation failed',
				error.data?.params
			);
		}

		// If already a custom error, rethrow
		if (
			error instanceof APIError ||
			error instanceof NetworkError ||
			error instanceof ValidationError
		) {
			throw error;
		}

		// Generic API error
		throw new APIError(
			error.message || `API request failed: ${ path }`,
			error
		);
	}
};

/**
 * Save settings.
 *
 * Resolves with the whole response: `data` holds the settings as saved and
 * `sgtm_loader` what became of the loader Stape issues.
 *
 * @param {Object} data The settings to save.
 * @return {Promise<Object>} The response.
 * @throws {APIError} The save did not succeed.
 */
export const updateSettings = async ( data ) => {
	const response = await handleApiFetch(
		'gtmkit/v1/set-options',
		'POST',
		data,
		true
	);

	if ( response?.success !== true ) {
		throw new APIError(
			response?.message || 'API request failed',
			response
		);
	}

	return response;
};

export const refreshSgtmLoader = () =>
	handleApiFetch( 'gtmkit/v1/sgtm-loader-refresh', 'POST', null, true );

export const pasteSgtmLoader = ( code ) =>
	handleApiFetch( 'gtmkit/v1/sgtm-loader-paste', 'POST', { code }, true );

export const sendSystemData = ( data ) =>
	handleApiFetch( 'gtmkit/v1/send-support-data', 'POST', data, true );

export const stopSupportSync = () =>
	handleApiFetch( 'gtmkit/v1/stop-support-sync', 'POST', null, true );

export const sendLicenseKey = ( data ) =>
	handleApiFetch( 'gtmkit/v1/send-license-key', 'POST', data, true );

export const deactivateLicense = () =>
	handleApiFetch( 'gtmkit/v1/deactivate-license', 'POST', null, true );

export const sendNotificationStatus = ( data ) =>
	handleApiFetch( 'gtmkit/v1/set-notification-status', 'POST', data, true );

export const getWebhookPreviewStatus = () =>
	handleApiFetch( 'gtmkit/v1/webhook/preview-status', 'GET', null, true );

export const setWebhookPreviewToken = ( data ) =>
	handleApiFetch( 'gtmkit/v1/webhook/preview-token', 'POST', data, true );

export const clearWebhookPreviewToken = () =>
	handleApiFetch(
		'gtmkit/v1/webhook/preview-token/clear',
		'POST',
		null,
		true
	);

export const sendWebhookPreviewTest = ( data ) =>
	handleApiFetch( 'gtmkit/v1/webhook/preview-test', 'POST', data, true );

/**
 * Ask the site's settings API whether this browser can reach it.
 *
 * Goes through api-fetch, so it carries the same nonce, root URL and
 * permission check as a save, but skips the shared error handling: the
 * caller needs the raw response or failure to tell a block, a timeout and an
 * error answer apart. Nothing is logged or stored.
 *
 * An unparsed failure carries no error code, so api-fetch cannot see an
 * expired nonce and refresh it the way it does for a save. The probe does
 * that itself, once, so a stale nonce is not reported as a blocked browser.
 *
 * @param {AbortSignal} signal Aborts the request.
 * @return {Promise<Response>} The unparsed response. A non-2xx response is thrown as is.
 */
export const probeHealth = async ( signal ) => {
	const send = () =>
		apiFetch( {
			path: 'gtmkit/v1/health',
			method: 'POST',
			data: {},
			parse: false,
			signal,
		} );

	try {
		return await send();
	} catch ( error ) {
		if ( ! ( await refreshExpiredNonce( error, signal ) ) ) {
			throw error;
		}
		return send();
	}
};

/**
 * Fetch a fresh REST nonce when a failure is WordPress rejecting the current
 * one, and hand it to the nonce middleware every request goes through.
 *
 * @param {*}           error  What the probe request threw.
 * @param {AbortSignal} signal Aborts the refresh.
 * @return {Promise<boolean>} Whether a fresh nonce is now in place.
 */
const refreshExpiredNonce = async ( error, signal ) => {
	if (
		error?.status !== 403 ||
		typeof error?.clone !== 'function' ||
		! apiFetch.nonceEndpoint ||
		! apiFetch.nonceMiddleware
	) {
		return false;
	}

	let body = null;
	try {
		body = await error.clone().json();
	} catch ( bodyError ) {
		if ( bodyError?.name === 'AbortError' ) {
			throw bodyError;
		}
		return false;
	}

	if ( body?.code !== 'rest_cookie_invalid_nonce' ) {
		return false;
	}

	// A refresh that cannot be made leaves the original answer to report.
	try {
		const response = await window.fetch( apiFetch.nonceEndpoint, {
			signal,
		} );
		if ( ! response.ok ) {
			return false;
		}

		apiFetch.nonceMiddleware.nonce = await response.text();
		return true;
	} catch ( refreshError ) {
		if ( refreshError?.name === 'AbortError' ) {
			throw refreshError;
		}
		return false;
	}
};
