/**
 * Custom error classes for GTM Kit Settings
 *
 * Provides structured error types for better error handling
 * and user feedback throughout the application.
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

/**
 * Base error class for GTM Kit
 *
 * @class GTMKitError
 * @augments Error
 */
export class GTMKitError extends Error {
	/**
	 * @param {string} message - Error message
	 * @param {string} code    - Error code
	 */
	constructor( message, code = 'GTMKIT_ERROR' ) {
		super( message );
		this.name = 'GTMKitError';
		this.code = code;

		// Maintains proper stack trace (only available in V8 engines)
		if ( Error.captureStackTrace ) {
			Error.captureStackTrace( this, GTMKitError );
		}
	}
}

/**
 * API request failed
 *
 * @class APIError
 * @augments GTMKitError
 */
export class APIError extends GTMKitError {
	/**
	 * @param {string} message  - Error message
	 * @param {Object} response - API response object
	 */
	constructor( message, response = null ) {
		super( message, 'API_ERROR' );
		this.name = 'APIError';
		this.response = response;

		if ( Error.captureStackTrace ) {
			Error.captureStackTrace( this, APIError );
		}
	}
}

/**
 * Validation error
 *
 * @class ValidationError
 * @augments GTMKitError
 */
export class ValidationError extends GTMKitError {
	/**
	 * @param {string} message - Error message
	 * @param {string} field   - Field name that failed validation
	 */
	constructor( message, field = null ) {
		super( message, 'VALIDATION_ERROR' );
		this.name = 'ValidationError';
		this.field = field;

		if ( Error.captureStackTrace ) {
			Error.captureStackTrace( this, ValidationError );
		}
	}
}

/**
 * License error
 *
 * @class LicenseError
 * @augments GTMKitError
 */
export class LicenseError extends GTMKitError {
	/**
	 * @param {string} message - Error message
	 */
	constructor( message ) {
		super( message, 'LICENSE_ERROR' );
		this.name = 'LicenseError';

		if ( Error.captureStackTrace ) {
			Error.captureStackTrace( this, LicenseError );
		}
	}
}

/**
 * Network error
 *
 * @class NetworkError
 * @augments GTMKitError
 */
export class NetworkError extends GTMKitError {
	/**
	 * @param {string} message - Error message
	 */
	constructor( message ) {
		super( message, 'NETWORK_ERROR' );
		this.name = 'NetworkError';

		if ( Error.captureStackTrace ) {
			Error.captureStackTrace( this, NetworkError );
		}
	}
}
