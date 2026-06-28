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

		// Network errors (fetch failed, no response)
		if (
			error.message?.includes( 'NetworkError' ) ||
			error.message?.includes( 'Failed to fetch' )
		) {
			throw new NetworkError( `Network error while calling ${ path }` );
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

export const updateSettings = ( data ) =>
	handleApiFetch( 'gtmkit/v1/set-options', 'POST', data );

export const sendSystemData = ( data ) =>
	handleApiFetch( 'gtmkit/v1/send-support-data', 'POST', data, true );

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
