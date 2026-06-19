/**
 * TypeScript definitions for error handler utilities
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

/**
 * Get user-friendly error message
 */
export function getUserFriendlyMessage( error: Error ): string;

/**
 * Log error for debugging
 */
export function logError( error: Error, context?: Record< string, unknown > ): void;

/**
 * Handle API errors with retry logic
 */
export function withRetry< T >(
	apiCall: () => Promise< T >,
	maxRetries?: number
): Promise< T >;

/**
 * Safe async wrapper that catches and logs errors
 */
export function safeAsync< T extends ( ...args: any[] ) => Promise< any > >(
	fn: T,
	context?: Record< string, unknown >
): T;
