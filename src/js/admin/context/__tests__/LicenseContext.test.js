/**
 * Covers how the license screen reports activation and deactivation
 * outcomes: a failed request is described as a failed request, a rejected
 * key keeps the server's own message, and a failed deactivation leaves the
 * page in place with the button usable again.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent, act } from '@testing-library/react';
import { useContext, useState } from '@wordpress/element';

import LicenseProvider, { LicenseContext } from '../LicenseContext';
import LicensePage from '../../app/shell/LicensePage';
import { APIError, NetworkError } from '../../utils/errors';

const mockSendLicenseKey = jest.fn();
const mockDeactivateLicense = jest.fn();
jest.mock( '../../api/settings', () => ( {
	sendLicenseKey: ( ...args ) => mockSendLicenseKey( ...args ),
	deactivateLicense: ( ...args ) => mockDeactivateLicense( ...args ),
} ) );

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		isPremium: () => true,
		isPremiumPlugin: () => true,
		hasValidLicense: () => true,
		getActiveTier: () => 'premium',
		getCurrentLicenseKey: () => 'GTMK-****-1234',
		getLicenseExpiresAt: () => '',
	},
} ) );

jest.mock( '../../app/shell/ContextPane', () => () => null );

const NETWORK_MESSAGE =
	'Network error. Please check your connection and try again.';
const SERVER_MESSAGE = 'Server error. Please try again later.';
const LICENSE_MESSAGE =
	'License validation failed. Please check your license key.';

// Exposes the activation state and action as inspectable DOM nodes.
const Probe = () => {
	const { isLicenseKeySent, licenseKeyMessage, sendLicenseKey } =
		useContext( LicenseContext );

	return (
		<div>
			<span data-testid="sent">{ String( isLicenseKeySent ) }</span>
			<span data-testid="message">{ licenseKeyMessage }</span>
			<button onClick={ sendLicenseKey }>activate</button>
		</div>
	);
};

const activate = async () => {
	render(
		<LicenseProvider>
			<Probe />
		</LicenseProvider>
	);
	await act( async () => {
		fireEvent.click( screen.getByText( 'activate' ) );
	} );
};

describe( 'LicenseContext activation', () => {
	beforeEach( () => {
		mockSendLicenseKey.mockReset();
	} );

	it( 'reports a dropped connection as a network error, not a key problem', async () => {
		mockSendLicenseKey.mockRejectedValue(
			new NetworkError( 'Network error while calling send-license-key' )
		);

		await activate();

		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			NETWORK_MESSAGE
		);
		expect( screen.getByTestId( 'sent' ).textContent ).toBe( 'false' );
	} );

	it( 'reports an expired session as a server error, not a key problem', async () => {
		mockSendLicenseKey.mockRejectedValue(
			new APIError( 'Cookie check failed', {
				code: 'rest_cookie_invalid_nonce',
				data: { status: 403 },
			} )
		);

		await activate();

		const message = screen.getByTestId( 'message' ).textContent;
		expect( message ).toBe( SERVER_MESSAGE );
		expect( message ).not.toBe( LICENSE_MESSAGE );
		expect( screen.getByTestId( 'sent' ).textContent ).toBe( 'false' );
	} );

	it( 'passes a rejected key message through unchanged', async () => {
		mockSendLicenseKey.mockResolvedValue( {
			success: false,
			data: 'Invalid license key format.',
		} );

		await activate();

		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			'Invalid license key format.'
		);
		expect( screen.getByTestId( 'sent' ).textContent ).toBe( 'false' );
	} );
} );

describe( 'LicensePage deactivation', () => {
	let consoleError;

	// jsdom cannot replace or spy on `window.location`; it reports a reload
	// as an unimplemented navigation on the console instead.
	const reloaded = () =>
		consoleError.mock.calls.some( ( args ) =>
			String( args[ 0 ]?.message ?? args[ 0 ] ).includes(
				'Not implemented: navigation'
			)
		);

	beforeEach( () => {
		mockDeactivateLicense.mockReset();
		consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => {
		consoleError.mockRestore();
	} );

	const deactivate = async () => {
		render(
			<LicenseProvider>
				<LicensePage />
			</LicenseProvider>
		);
		await act( async () => {
			fireEvent.click( screen.getByText( 'Deactivate license' ) );
		} );
	};

	it( 'shows the failure, re-enables the button and does not reload', async () => {
		mockDeactivateLicense.mockRejectedValue(
			new NetworkError( 'Network error while calling deactivate-license' )
		);

		await deactivate();

		expect( screen.getByText( NETWORK_MESSAGE ) ).toBeTruthy();
		expect(
			screen.getByText( 'Deactivate license' ).closest( 'button' )
				.disabled
		).toBe( false );
		expect( reloaded() ).toBe( false );
	} );

	it( 'reloads after a successful deactivation', async () => {
		mockDeactivateLicense.mockResolvedValue( {
			success: true,
			data: 'Your license has been deactivated.',
		} );

		await deactivate();

		expect( reloaded() ).toBe( true );
		expect( screen.queryByText( NETWORK_MESSAGE ) ).toBeNull();
	} );
} );

describe( 'LicenseContext deactivation result', () => {
	// Exposes the deactivation result and message as inspectable DOM nodes.
	const DeactivateProbe = () => {
		const { deactivateLicense, deactivateLicenseMessage } =
			useContext( LicenseContext );
		const [ result, setResult ] = useState( 'pending' );

		return (
			<div>
				<span data-testid="result">{ result }</span>
				<span data-testid="message">{ deactivateLicenseMessage }</span>
				<button
					onClick={ async () =>
						setResult( String( await deactivateLicense() ) )
					}
				>
					deactivate
				</button>
			</div>
		);
	};

	const deactivate = async () => {
		render(
			<LicenseProvider>
				<DeactivateProbe />
			</LicenseProvider>
		);
		await act( async () => {
			fireEvent.click( screen.getByText( 'deactivate' ) );
		} );
	};

	beforeEach( () => {
		mockDeactivateLicense.mockReset();
	} );

	it( 'reports a refused deactivation with the server message', async () => {
		mockDeactivateLicense.mockResolvedValue( {
			success: false,
			data: 'Deactivation failed.',
		} );

		await deactivate();

		expect( screen.getByTestId( 'result' ).textContent ).toBe( 'false' );
		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			'Deactivation failed.'
		);
	} );

	it( 'reports a refused deactivation without a message as a server error', async () => {
		mockDeactivateLicense.mockResolvedValue( { success: false } );

		await deactivate();

		expect( screen.getByTestId( 'result' ).textContent ).toBe( 'false' );
		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			SERVER_MESSAGE
		);
	} );

	it( 'reports a completed deactivation', async () => {
		mockDeactivateLicense.mockResolvedValue( {
			success: true,
			data: 'Your license has been deactivated.',
		} );

		await deactivate();

		expect( screen.getByTestId( 'result' ).textContent ).toBe( 'true' );
		expect( screen.getByTestId( 'message' ).textContent ).toBe( '' );
	} );
} );
