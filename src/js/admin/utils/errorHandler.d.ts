/**
 * TypeScript definitions for error handler utilities
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

/**
 * Get user-friendly error message
 *
 * @param error The error to describe.
 */
export function getUserFriendlyMessage( error: Error ): string;

/**
 * Log error for debugging
 *
 * @param error   The error to log.
 * @param context Extra details logged with the error.
 */
export function logError(
	error: Error,
	context?: Record< string, unknown >
): void;

/**
 * Handle API errors with retry logic
 *
 * @param apiCall    The request to run.
 * @param maxRetries How many times to retry a failed request.
 */
export function withRetry< T >(
	apiCall: () => Promise< T >,
	maxRetries?: number
): Promise< T >;

/**
 * Safe async wrapper that catches and logs errors
 *
 * @param fn      The async function to wrap.
 * @param context Extra details logged with an error.
 */
export function safeAsync< T extends ( ...args: any[] ) => Promise< any > >(
	fn: T,
	context?: Record< string, unknown >
): T;
