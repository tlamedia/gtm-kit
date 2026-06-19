/**
 * Error Classes Unit Tests
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import {
	GTMKitError,
	APIError,
	ValidationError,
	LicenseError,
	NetworkError,
} from '../errors';

describe( 'Error Classes', () => {
	describe( 'GTMKitError', () => {
		it( 'should create error with default code', () => {
			const error = new GTMKitError( 'Test error' );
			expect( error.message ).toBe( 'Test error' );
			expect( error.code ).toBe( 'GTMKIT_ERROR' );
			expect( error.name ).toBe( 'GTMKitError' );
			expect( error ).toBeInstanceOf( Error );
			expect( error ).toBeInstanceOf( GTMKitError );
		} );

		it( 'should create error with custom code', () => {
			const error = new GTMKitError( 'Test error', 'CUSTOM_CODE' );
			expect( error.message ).toBe( 'Test error' );
			expect( error.code ).toBe( 'CUSTOM_CODE' );
		} );

		it( 'should have stack trace', () => {
			const error = new GTMKitError( 'Test error' );
			expect( error.stack ).toBeDefined();
		} );
	} );

	describe( 'APIError', () => {
		it( 'should create API error without response', () => {
			const error = new APIError( 'API failed' );
			expect( error.message ).toBe( 'API failed' );
			expect( error.code ).toBe( 'API_ERROR' );
			expect( error.name ).toBe( 'APIError' );
			expect( error.response ).toBeNull();
			expect( error ).toBeInstanceOf( GTMKitError );
			expect( error ).toBeInstanceOf( APIError );
		} );

		it( 'should create API error with response object', () => {
			const response = { status: 500, data: 'Server error' };
			const error = new APIError( 'API failed', response );
			expect( error.message ).toBe( 'API failed' );
			expect( error.response ).toEqual( response );
		} );
	} );

	describe( 'ValidationError', () => {
		it( 'should create validation error without field', () => {
			const error = new ValidationError( 'Invalid input' );
			expect( error.message ).toBe( 'Invalid input' );
			expect( error.code ).toBe( 'VALIDATION_ERROR' );
			expect( error.name ).toBe( 'ValidationError' );
			expect( error.field ).toBeNull();
			expect( error ).toBeInstanceOf( GTMKitError );
			expect( error ).toBeInstanceOf( ValidationError );
		} );

		it( 'should create validation error with field name', () => {
			const error = new ValidationError( 'Invalid email', 'email' );
			expect( error.message ).toBe( 'Invalid email' );
			expect( error.field ).toBe( 'email' );
		} );
	} );

	describe( 'LicenseError', () => {
		it( 'should create license error', () => {
			const error = new LicenseError( 'License expired' );
			expect( error.message ).toBe( 'License expired' );
			expect( error.code ).toBe( 'LICENSE_ERROR' );
			expect( error.name ).toBe( 'LicenseError' );
			expect( error ).toBeInstanceOf( GTMKitError );
			expect( error ).toBeInstanceOf( LicenseError );
		} );
	} );

	describe( 'NetworkError', () => {
		it( 'should create network error', () => {
			const error = new NetworkError( 'Connection failed' );
			expect( error.message ).toBe( 'Connection failed' );
			expect( error.code ).toBe( 'NETWORK_ERROR' );
			expect( error.name ).toBe( 'NetworkError' );
			expect( error ).toBeInstanceOf( GTMKitError );
			expect( error ).toBeInstanceOf( NetworkError );
		} );
	} );

	describe( 'Error inheritance', () => {
		it( 'should allow instanceof checks across hierarchy', () => {
			const apiError = new APIError( 'API failed' );
			const validationError = new ValidationError( 'Invalid' );
			const licenseError = new LicenseError( 'License invalid' );
			const networkError = new NetworkError( 'Network failed' );

			// All custom errors are GTMKitErrors
			expect( apiError ).toBeInstanceOf( GTMKitError );
			expect( validationError ).toBeInstanceOf( GTMKitError );
			expect( licenseError ).toBeInstanceOf( GTMKitError );
			expect( networkError ).toBeInstanceOf( GTMKitError );

			// All custom errors are native Errors
			expect( apiError ).toBeInstanceOf( Error );
			expect( validationError ).toBeInstanceOf( Error );
			expect( licenseError ).toBeInstanceOf( Error );
			expect( networkError ).toBeInstanceOf( Error );

			// But they're not instances of each other
			expect( apiError ).not.toBeInstanceOf( ValidationError );
			expect( validationError ).not.toBeInstanceOf( APIError );
		} );
	} );

	describe( 'Error codes', () => {
		it( 'should have unique error codes', () => {
			const errors = [
				new GTMKitError( 'test' ),
				new APIError( 'test' ),
				new ValidationError( 'test' ),
				new LicenseError( 'test' ),
				new NetworkError( 'test' ),
			];

			const codes = errors.map( ( e ) => e.code );
			const uniqueCodes = [ ...new Set( codes ) ];

			expect( codes.length ).toBe( uniqueCodes.length );
		} );
	} );
} );
