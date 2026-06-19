/**
 * Error handling utilities
 *
 * Provides centralized error handling, user-friendly messages,
 * logging, and retry logic for API calls.
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { __ } from '@wordpress/i18n';
import {
	APIError,
	ValidationError,
	LicenseError,
	NetworkError,
} from './errors';

/**
 * Get user-friendly error message
 *
 * Converts technical error objects into user-readable messages.
 *
 * @param {Error} error - Error object
 * @return {string} User-friendly message
 *
 * @example
 * const message = getUserFriendlyMessage(error);
 * dispatch({ type: 'SET_ERROR', payload: { message } });
 */
export const getUserFriendlyMessage = ( error ) => {
	if ( error instanceof ValidationError ) {
		return error.message;
	}

	if ( error instanceof LicenseError ) {
		return __(
			'License validation failed. Please check your license key.',
			'gtm-kit'
		);
	}

	if ( error instanceof NetworkError ) {
		return __(
			'Network error. Please check your connection and try again.',
			'gtm-kit'
		);
	}

	if ( error instanceof APIError ) {
		return __( 'Server error. Please try again later.', 'gtm-kit' );
	}

	return __( 'An unexpected error occurred. Please try again.', 'gtm-kit' );
};

/**
 * Log error for debugging
 *
 * Logs errors to console in development mode.
 * Can be extended to send to error reporting service in production.
 *
 * @param {Error}  error   - Error object
 * @param {Object} context - Additional context about where/why error occurred
 *
 * @example
 * logError(error, { action: 'updateSettings', settingsGroup: 'general' });
 */
export const logError = ( error, context = {} ) => {
	if ( process.env.NODE_ENV === 'development' ) {
		// eslint-disable-next-line no-console
		console.error( 'GTM Kit Error:', {
			error,
			message: error.message,
			code: error.code,
			context,
		} );
	}

	// TODO: Send to error reporting service in production
	// if (process.env.NODE_ENV === 'production') {
	//     sendToErrorReporting(error, context);
	// }
};

/**
 * Handle API errors with retry logic
 *
 * Automatically retries failed API calls with exponential backoff.
 * Useful for transient network errors.
 *
 * @param {Function} apiCall    - API call function to execute
 * @param {number}   maxRetries - Maximum retry attempts (default: 3)
 * @return {Promise} API response
 *
 * @example
 * const settings = await withRetry(() => updateSettings(data), 3);
 */
export const withRetry = async ( apiCall, maxRetries = 3 ) => {
	let lastError;

	for ( let attempt = 0; attempt < maxRetries; attempt++ ) {
		try {
			return await apiCall();
		} catch ( error ) {
			lastError = error;

			// Don't retry validation errors - they won't succeed on retry
			if ( error instanceof ValidationError ) {
				throw error;
			}

			// Wait before retry (exponential backoff: 1s, 2s, 4s)
			if ( attempt < maxRetries - 1 ) {
				await new Promise( ( resolve ) =>
					setTimeout( resolve, Math.pow( 2, attempt ) * 1000 )
				);
			}
		}
	}

	throw lastError;
};

/**
 * Safe async wrapper that catches and logs errors
 *
 * Wraps async functions to ensure errors are caught and logged.
 *
 * @param {Function} fn      - Async function to wrap
 * @param {Object}   context - Context for error logging
 * @return {Function} Wrapped function
 *
 * @example
 * const safeUpdateSettings = safeAsync(updateSettings, { action: 'updateSettings' });
 * await safeUpdateSettings(data);
 */
export const safeAsync = ( fn, context = {} ) => {
	return async ( ...args ) => {
		try {
			return await fn( ...args );
		} catch ( error ) {
			logError( error, context );
			throw error;
		}
	};
};
