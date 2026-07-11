/**
 * Covers the SupportContext live-sync state: seeding from the settings
 * bootstrap payload, the stop-sharing action (success, failure, and
 * rejection paths), and the send-system-data response handling for both
 * the structured `{ message, supportSync }` body and a plain string body.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent, act } from '@testing-library/react';
import { useContext } from '@wordpress/element';

import SupportProvider, { SupportContext } from '../SupportContext';

const mockSendSystemData = jest.fn();
const mockStopSupportSync = jest.fn();
jest.mock( '../../api/settings', () => ( {
	sendSystemData: ( ...args ) => mockSendSystemData( ...args ),
	stopSupportSync: ( ...args ) => mockStopSupportSync( ...args ),
} ) );

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getSupportSync: () => ( {
			active: true,
			ticket: 'FS123-ABC45',
			until: 'July 16, 2026',
		} ),
	},
} ) );

// Exposes the context state and actions as inspectable DOM nodes.
const Probe = () => {
	const {
		supportSync,
		isStoppingSupportSync,
		isSystemDataSent,
		systemDataMessage,
		stopSupportSync,
		sendSystemData,
	} = useContext( SupportContext );

	return (
		<div>
			<span data-testid="active">{ String( supportSync.active ) }</span>
			<span data-testid="ticket">{ supportSync.ticket || '' }</span>
			<span data-testid="stopping">
				{ String( isStoppingSupportSync ) }
			</span>
			<span data-testid="sent">{ String( isSystemDataSent ) }</span>
			<span data-testid="message">{ systemDataMessage }</span>
			<button onClick={ stopSupportSync }>stop</button>
			<button onClick={ sendSystemData }>send</button>
		</div>
	);
};

const renderProbe = () =>
	render(
		<SupportProvider>
			<Probe />
		</SupportProvider>
	);

describe( 'SupportContext live support sync', () => {
	beforeEach( () => {
		mockSendSystemData.mockReset();
		mockStopSupportSync.mockReset();
	} );

	it( 'seeds the sync state from the settings bootstrap payload', () => {
		renderProbe();

		expect( screen.getByTestId( 'active' ).textContent ).toBe( 'true' );
		expect( screen.getByTestId( 'ticket' ).textContent ).toBe(
			'FS123-ABC45'
		);
		expect( screen.getByTestId( 'stopping' ).textContent ).toBe( 'false' );
	} );

	it( 'stop sharing clears the sync state on success', async () => {
		mockStopSupportSync.mockResolvedValue( {
			success: true,
			data: { supportSync: { active: false } },
		} );

		renderProbe();

		await act( async () => {
			fireEvent.click( screen.getByText( 'stop' ) );
		} );

		expect( mockStopSupportSync ).toHaveBeenCalledTimes( 1 );
		expect( screen.getByTestId( 'active' ).textContent ).toBe( 'false' );
		expect( screen.getByTestId( 'stopping' ).textContent ).toBe( 'false' );
	} );

	it( 'keeps the sync state when the stop request fails', async () => {
		mockStopSupportSync.mockResolvedValue( { success: false, data: '' } );

		renderProbe();

		await act( async () => {
			fireEvent.click( screen.getByText( 'stop' ) );
		} );

		expect( screen.getByTestId( 'active' ).textContent ).toBe( 'true' );
		expect( screen.getByTestId( 'stopping' ).textContent ).toBe( 'false' );
	} );

	it( 'keeps the sync state when the stop request rejects', async () => {
		mockStopSupportSync.mockRejectedValue( new Error( 'offline' ) );

		renderProbe();

		await act( async () => {
			fireEvent.click( screen.getByText( 'stop' ) );
		} );

		expect( screen.getByTestId( 'active' ).textContent ).toBe( 'true' );
		expect( screen.getByTestId( 'stopping' ).textContent ).toBe( 'false' );
	} );

	it( 'send system data reads the structured response body', async () => {
		mockSendSystemData.mockResolvedValue( {
			success: true,
			data: {
				message: 'Thank you! We have received the data.',
				supportSync: {
					active: true,
					ticket: 'FS999-NEW99',
					until: 'August 1, 2026',
				},
			},
		} );

		renderProbe();

		await act( async () => {
			fireEvent.click( screen.getByText( 'send' ) );
		} );

		expect( screen.getByTestId( 'sent' ).textContent ).toBe( 'true' );
		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			'Thank you! We have received the data.'
		);
		expect( screen.getByTestId( 'ticket' ).textContent ).toBe(
			'FS999-NEW99'
		);
	} );

	it( 'send system data still accepts a plain string body', async () => {
		mockSendSystemData.mockResolvedValue( {
			success: false,
			data: 'The support ticket was not found.',
		} );

		renderProbe();

		await act( async () => {
			fireEvent.click( screen.getByText( 'send' ) );
		} );

		expect( screen.getByTestId( 'sent' ).textContent ).toBe( 'false' );
		expect( screen.getByTestId( 'message' ).textContent ).toBe(
			'The support ticket was not found.'
		);
		// A failed share never touches the existing sync state.
		expect( screen.getByTestId( 'ticket' ).textContent ).toBe(
			'FS123-ABC45'
		);
	} );
} );
