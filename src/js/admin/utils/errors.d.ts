/**
 * TypeScript definitions for custom error classes
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

/**
 * Base error class for GTM Kit
 */
export class GTMKitError extends Error {
	code: string;
	constructor( message: string, code?: string );
}

/**
 * API request failed
 */
export class APIError extends GTMKitError {
	response: unknown;
	constructor( message: string, response?: unknown );
}

/**
 * Validation error
 */
export class ValidationError extends GTMKitError {
	field: string | null;
	constructor( message: string, field?: string | null );
}

/**
 * License error
 */
export class LicenseError extends GTMKitError {
	constructor( message: string );
}

/**
 * Network error
 */
export class NetworkError extends GTMKitError {
	constructor( message: string );
}
