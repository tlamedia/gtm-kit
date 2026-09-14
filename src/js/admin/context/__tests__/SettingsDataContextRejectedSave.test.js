/**
 * Covers a save that the server rejects as invalid, end to end through the
 * real API client: the REST error becomes a ValidationError, and its message
 * is shown as-is in a toast that stays until dismissed.
 */

/* eslint-disable import/no-extraneous-dependencies */

import { useContext } from '@wordpress/element';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import {
	SettingsDataContext,
	SettingsDataProvider,
} from '../SettingsDataContext';
import { ToastContext } from '../ToastContext';

jest.mock( '@wordpress/api-fetch', () => {
	const mock = jest.fn();
	mock.use = jest.fn();
	mock.createNonceMiddleware = jest.fn();
	mock.createRootURLMiddleware = jest.fn();
	return { __esModule: true, default: mock };
} );

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getSettings: () => ( { general: { gtm_id: 'GTM-OLD1234' } } ),
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

const REJECTED =
	'Your changes were not saved, because 1 setting has a value GTM Kit cannot accept: gtm_id (value not accepted). Correct it and save again.';

const Probe = () => {
	const { updateSettings, isPending, hasError } =
		useContext( SettingsDataContext );

	return (
		<>
			<button type="button" onClick={ updateSettings }>
				save
			</button>
			<span data-testid="state">{ `${ isPending }|${ hasError }` }</span>
		</>
	);
};

describe( 'SettingsDataProvider rejected save', () => {
	it( 'shows the server message for a save rejected as invalid', async () => {
		// apiFetch rejects with the parsed REST error body on a non-2xx answer.
		apiFetch.mockRejectedValueOnce( {
			code: 'gtmkit_settings_rejected',
			message: REJECTED,
			data: {
				status: 400,
				params: { 'general.gtm_id': 'Invalid value for gtm_id' },
			},
		} );
		const toast = { error: jest.fn() };

		render(
			<ToastContext.Provider value={ toast }>
				<SettingsDataProvider>
					<Probe />
				</SettingsDataProvider>
			</ToastContext.Provider>
		);
		fireEvent.click( screen.getByText( 'save' ) );

		await waitFor( () =>
			expect( toast.error ).toHaveBeenCalledWith( REJECTED, 0 )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: 'gtmkit/v1/set-options',
				method: 'POST',
			} )
		);
		expect( screen.getByTestId( 'state' ).textContent ).toBe(
			'false|true'
		);
	} );

	const rejection = {
		code: 'gtmkit_settings_rejected',
		message: REJECTED,
		data: { status: 400, params: {} },
	};

	const renderWithToast = ( toast ) =>
		render(
			<ToastContext.Provider value={ toast }>
				<SettingsDataProvider>
					<Probe />
				</SettingsDataProvider>
			</ToastContext.Provider>
		);

	it( 'takes a failed save’s message down when the next save succeeds', async () => {
		apiFetch.mockRejectedValueOnce( rejection );
		apiFetch.mockResolvedValueOnce( {
			success: true,
			data: { general: { gtm_id: 'GTM-NEW1234' } },
		} );
		const toast = { error: jest.fn( () => 7 ), removeToast: jest.fn() };

		renderWithToast( toast );

		fireEvent.click( screen.getByText( 'save' ) );
		await waitFor( () => expect( toast.error ).toHaveBeenCalledTimes( 1 ) );

		fireEvent.click( screen.getByText( 'save' ) );
		await waitFor( () =>
			expect( screen.getByTestId( 'state' ).textContent ).toBe(
				'false|false'
			)
		);

		expect( toast.removeToast ).toHaveBeenCalledWith( 7 );
		expect( toast.error ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps one failure message on screen across repeated failures', async () => {
		apiFetch.mockRejectedValueOnce( rejection );
		apiFetch.mockRejectedValueOnce( rejection );
		const toast = {
			error: jest.fn().mockReturnValueOnce( 7 ).mockReturnValueOnce( 8 ),
			removeToast: jest.fn(),
		};

		renderWithToast( toast );

		fireEvent.click( screen.getByText( 'save' ) );
		await waitFor( () => expect( toast.error ).toHaveBeenCalledTimes( 1 ) );

		fireEvent.click( screen.getByText( 'save' ) );
		await waitFor( () => expect( toast.error ).toHaveBeenCalledTimes( 2 ) );

		expect( toast.removeToast ).toHaveBeenCalledTimes( 1 );
		expect( toast.removeToast ).toHaveBeenCalledWith( 7 );
	} );
} );
