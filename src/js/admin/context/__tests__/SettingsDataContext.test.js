/**
 * Covers what a failed save leaves behind: the save control usable again,
 * the error recorded, and the server's explanation shown in a toast that
 * stays until dismissed.
 */

/* eslint-disable import/no-extraneous-dependencies */

import { useContext } from '@wordpress/element';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

import {
	SettingsDataContext,
	SettingsDataProvider,
} from '../SettingsDataContext';
import { ToastContext } from '../ToastContext';
import { APIError } from '../../utils/errors';
import { updateSettings as apiUpdateSettings } from '../../api/settings';

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getSettings: () => ( { general: { gtm_id: 'GTM-OLD1234' } } ),
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

jest.mock( '../../api/settings', () => ( {
	updateSettings: jest.fn(),
} ) );

const NOT_KEPT =
	'Your changes were sent, but 1 setting still reads back with its old value. Flush the object cache, then save again.';

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

const renderProvider = ( toast ) =>
	render(
		<ToastContext.Provider value={ toast }>
			<SettingsDataProvider>
				<Probe />
			</SettingsDataProvider>
		</ToastContext.Provider>
	);

describe( 'SettingsDataProvider failed save', () => {
	it( 'shows the server explanation in a persistent toast and ends the pending state', async () => {
		apiUpdateSettings.mockRejectedValueOnce(
			new APIError( NOT_KEPT, {
				code: 'gtmkit_save_not_kept',
				message: NOT_KEPT,
				data: { status: 409 },
			} )
		);
		const toast = { error: jest.fn() };

		renderProvider( toast );
		fireEvent.click( screen.getByText( 'save' ) );

		await waitFor( () =>
			expect( toast.error ).toHaveBeenCalledWith( NOT_KEPT, 0 )
		);
		expect( screen.getByTestId( 'state' ).textContent ).toBe(
			'false|true'
		);
	} );

	it( 'keeps the generic message for other failures', async () => {
		apiUpdateSettings.mockRejectedValueOnce(
			new APIError( 'Internal detail', { code: 'rest_forbidden' } )
		);
		const toast = { error: jest.fn() };

		renderProvider( toast );
		fireEvent.click( screen.getByText( 'save' ) );

		await waitFor( () =>
			expect( toast.error ).toHaveBeenCalledWith(
				'Server error. Please try again later.',
				0
			)
		);
	} );

	it( 'still records the error without a toast provider', async () => {
		apiUpdateSettings.mockRejectedValueOnce( new APIError( 'Boom' ) );

		render(
			<SettingsDataProvider>
				<Probe />
			</SettingsDataProvider>
		);
		fireEvent.click( screen.getByText( 'save' ) );

		await waitFor( () =>
			expect( screen.getByTestId( 'state' ).textContent ).toBe(
				'false|true'
			)
		);
	} );
} );
