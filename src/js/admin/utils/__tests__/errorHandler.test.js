/**
 * Error Handler Utilities Unit Tests
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import {
	getUserFriendlyMessage,
	logError,
	withRetry,
	safeAsync,
} from '../errorHandler';
import {
	APIError,
	ValidationError,
	LicenseError,
	NetworkError,
} from '../errors';

// Mock console.error to avoid cluttering test output
// eslint-disable-next-line no-console
const originalConsoleError = console.error;
beforeAll( () => {
	// eslint-disable-next-line no-console
	console.error = jest.fn();
} );
afterAll( () => {
	// eslint-disable-next-line no-console
	console.error = originalConsoleError;
} );

describe( 'Error Handler Utilities', () => {
	describe( 'getUserFriendlyMessage', () => {
		it( 'should return validation error message as-is', () => {
			const error = new ValidationError( 'GTM ID is required', 'gtm_id' );
			const message = getUserFriendlyMessage( error );
			expect( message ).toBe( 'GTM ID is required' );
		} );

		it( 'should return friendly message for license error', () => {
			const error = new LicenseError( 'Invalid license key' );
			const message = getUserFriendlyMessage( error );
			expect( message ).toBe(
				'License validation failed. Please check your license key.'
			);
		} );

		it( 'should return friendly message for network error', () => {
			const error = new NetworkError( 'Failed to fetch' );
			const message = getUserFriendlyMessage( error );
			expect( message ).toBe(
				'Network error. Please check your connection and try again.'
			);
		} );

		it( 'should return friendly message for API error', () => {
			const error = new APIError( 'Server returned 500' );
			const message = getUserFriendlyMessage( error );
			expect( message ).toBe( 'Server error. Please try again later.' );
		} );

		it( 'should return generic message for unknown error', () => {
			const error = new Error( 'Something broke' );
			const message = getUserFriendlyMessage( error );
			expect( message ).toBe(
				'An unexpected error occurred. Please try again.'
			);
		} );
	} );

	describe( 'logError', () => {
		beforeEach( () => {
			// eslint-disable-next-line no-console
			console.error.mockClear();
		} );

		it( 'should log error with context in development mode', () => {
			const originalEnv = process.env.NODE_ENV;
			process.env.NODE_ENV = 'development';

			const error = new APIError( 'Test error' );
			const context = { action: 'updateSettings', group: 'general' };

			logError( error, context );

			// eslint-disable-next-line no-console
			expect( console.error ).toHaveBeenCalledWith(
				'GTM Kit Error:',
				expect.objectContaining( {
					error,
					message: 'Test error',
					code: 'API_ERROR',
					context,
				} )
			);

			process.env.NODE_ENV = originalEnv;
		} );

		it( 'should not log in production mode', () => {
			const originalEnv = process.env.NODE_ENV;
			process.env.NODE_ENV = 'production';

			const error = new APIError( 'Test error' );
			logError( error, {} );

			// eslint-disable-next-line no-console
			expect( console.error ).not.toHaveBeenCalled();

			process.env.NODE_ENV = originalEnv;
		} );

		it( 'should handle empty context', () => {
			const originalEnv = process.env.NODE_ENV;
			process.env.NODE_ENV = 'development';

			const error = new NetworkError( 'Connection lost' );
			logError( error );

			// eslint-disable-next-line no-console
			expect( console.error ).toHaveBeenCalledWith(
				'GTM Kit Error:',
				expect.objectContaining( {
					error,
					context: {},
				} )
			);

			process.env.NODE_ENV = originalEnv;
		} );
	} );

	describe( 'withRetry', () => {
		it( 'should return result on first attempt if successful', async () => {
			const apiCall = jest.fn().mockResolvedValue( { success: true } );
			const result = await withRetry( apiCall, 3 );

			expect( result ).toEqual( { success: true } );
			expect( apiCall ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should retry on failure and eventually succeed', async () => {
			const apiCall = jest
				.fn()
				.mockRejectedValueOnce( new NetworkError( 'Failed' ) )
				.mockRejectedValueOnce( new NetworkError( 'Failed again' ) )
				.mockResolvedValue( { success: true } );

			const result = await withRetry( apiCall, 3 );

			expect( result ).toEqual( { success: true } );
			expect( apiCall ).toHaveBeenCalledTimes( 3 );
		} );

		it( 'should throw after max retries exhausted', async () => {
			const apiCall = jest
				.fn()
				.mockRejectedValue( new NetworkError( 'Failed' ) );

			await expect( withRetry( apiCall, 3 ) ).rejects.toThrow(
				NetworkError
			);
			expect( apiCall ).toHaveBeenCalledTimes( 3 );
		} );

		it( 'should not retry validation errors', async () => {
			const error = new ValidationError( 'Invalid input' );
			const apiCall = jest.fn().mockRejectedValue( error );

			await expect( withRetry( apiCall, 3 ) ).rejects.toThrow(
				ValidationError
			);
			expect( apiCall ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'should use exponential backoff', async () => {
			const startTime = Date.now();
			const apiCall = jest
				.fn()
				.mockRejectedValueOnce( new NetworkError( 'Failed' ) )
				.mockRejectedValueOnce( new NetworkError( 'Failed' ) )
				.mockResolvedValue( { success: true } );

			await withRetry( apiCall, 3 );

			const elapsed = Date.now() - startTime;
			// Should wait at least 1s + 2s = 3s for two retries
			// Allow some tolerance for test execution
			expect( elapsed ).toBeGreaterThanOrEqual( 2900 );
		}, 10000 );
	} );

	describe( 'safeAsync', () => {
		it( 'should return result when function succeeds', async () => {
			const fn = jest.fn().mockResolvedValue( { data: 'test' } );
			const safeFn = safeAsync( fn, { action: 'test' } );

			const result = await safeFn( 'arg1', 'arg2' );

			expect( result ).toEqual( { data: 'test' } );
			expect( fn ).toHaveBeenCalledWith( 'arg1', 'arg2' );
		} );

		it( 'should log and rethrow error when function fails', async () => {
			const originalEnv = process.env.NODE_ENV;
			process.env.NODE_ENV = 'development';

			const error = new APIError( 'Failed' );
			const fn = jest.fn().mockRejectedValue( error );
			const safeFn = safeAsync( fn, { action: 'updateSettings' } );

			await expect( safeFn() ).rejects.toThrow( APIError );

			// eslint-disable-next-line no-console
			expect( console.error ).toHaveBeenCalledWith(
				'GTM Kit Error:',
				expect.objectContaining( {
					error,
					context: { action: 'updateSettings' },
				} )
			);

			process.env.NODE_ENV = originalEnv;
		} );

		it( 'should pass through all arguments to wrapped function', async () => {
			const fn = jest.fn().mockResolvedValue( 'success' );
			const safeFn = safeAsync( fn );

			await safeFn( 'a', 'b', 'c', { key: 'value' } );

			expect( fn ).toHaveBeenCalledWith( 'a', 'b', 'c', {
				key: 'value',
			} );
		} );
	} );
} );
