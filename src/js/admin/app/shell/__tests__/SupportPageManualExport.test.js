/**
 * Covers the Support page's manual system-data route: hidden while sending
 * works, offered once sending has failed or stalled, never offered without
 * the server-rendered export, and copying or downloading the exact payload
 * without any request.
 */

/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent, act } from '@testing-library/react';

import SupportPage from '../SupportPage';
import { SupportContext } from '../../../context/SupportContext';

const EXPORT_JSON =
	'{"system_data":"{\\"options\\":{\\"general\\":{\\"gtm_id\\":\\"GTM-ABC123\\"}},\\"note\\":\\"a & b\\"}","source":"export"}';

let mockSupportExport = null;

jest.mock( '../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getSupportSync: () => ( { active: false } ),
		getSupportExport: () => mockSupportExport,
		getTutorials: () => [],
		getOpportunities: () => ( {} ),
		isPremiumPlugin: () => true,
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

const baseContext = {
	isSendingSystemData: false,
	supportSync: { active: false },
	isStoppingSupportSync: false,
	updateSupportTicket: jest.fn(),
	sendSystemData: jest.fn(),
	stopSupportSync: jest.fn(),
	useSupportTicket: '',
	useIsSystemDataSent: false,
	useIsSystemDataFailed: false,
	useSystemDataMessage: '',
};

const FAILED = {
	useIsSystemDataFailed: true,
	useSystemDataMessage:
		'Your site could not reach the GTM Kit support server, so your system data was not sent.',
};

const page = ( overrides = {} ) => (
	<SupportContext.Provider value={ { ...baseContext, ...overrides } }>
		<SupportPage />
	</SupportContext.Provider>
);

const readBlob = ( blob ) =>
	new Promise( ( resolve ) => {
		const reader = new window.FileReader();
		reader.onload = () => resolve( reader.result );
		reader.readAsText( blob );
	} );

describe( 'SupportPage manual system data', () => {
	let fetchSpy;

	beforeEach( () => {
		mockSupportExport = {
			json: EXPORT_JSON,
			filename: 'gtmkit-system-data-shop.example.test-2026-09-11.json',
		};
		fetchSpy = jest.fn();
		global.fetch = fetchSpy;
	} );

	afterEach( () => {
		delete global.fetch;
		delete window.navigator.clipboard;
		jest.restoreAllMocks();
		jest.useRealTimers();
	} );

	it( 'stays hidden while sending works', () => {
		render( page() );

		expect( screen.queryByText( 'Send system data manually' ) ).toBeNull();
	} );

	it( 'stays hidden when the ticket is refused rather than the sending failing', () => {
		render(
			page( {
				useSystemDataMessage:
					'The support ticket was not found. Please check that you have entered the correct ticket.',
			} )
		);

		expect( screen.queryByText( 'Send system data manually' ) ).toBeNull();
		expect(
			screen.queryByText( /You can send your system data manually/ )
		).toBeNull();
	} );

	it( 'is offered with the exact payload once sending has failed', () => {
		render( page( FAILED ) );

		expect( screen.getByText( 'Send system data manually' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'System data' ).value ).toBe(
			EXPORT_JSON
		);
		expect(
			screen.getByText( /You can send your system data manually/ )
		).toBeTruthy();
		expect(
			screen.getByText( /never post it anywhere public/ )
		).toBeTruthy();
	} );

	it( 'is offered once a send has been running for too long', () => {
		jest.useFakeTimers();
		render( page( { isSendingSystemData: true } ) );

		act( () => {
			jest.advanceTimersByTime( 14000 );
		} );
		expect( screen.queryByText( 'Send system data manually' ) ).toBeNull();

		act( () => {
			jest.advanceTimersByTime( 1000 );
		} );
		expect( screen.getByText( 'Send system data manually' ) ).toBeTruthy();
	} );

	it( 'is hidden again when a slow send finally succeeds', () => {
		jest.useFakeTimers();
		const { rerender } = render( page( { isSendingSystemData: true } ) );

		act( () => {
			jest.advanceTimersByTime( 15000 );
		} );
		expect( screen.getByText( 'Send system data manually' ) ).toBeTruthy();

		rerender(
			page( {
				useIsSystemDataSent: true,
				useSystemDataMessage: 'Thank you! We have received the data.',
			} )
		);
		expect( screen.queryByText( 'Send system data manually' ) ).toBeNull();
	} );

	it( 'is never offered without the export, as on sites without Premium', () => {
		mockSupportExport = null;

		render( page( FAILED ) );

		expect( screen.queryByText( 'Send system data manually' ) ).toBeNull();
		expect(
			screen.queryByText( /You can send your system data manually/ )
		).toBeNull();
	} );

	it( 'copies the exact payload without making a request', async () => {
		const writeText = jest.fn().mockResolvedValue( undefined );
		Object.defineProperty( window.navigator, 'clipboard', {
			value: { writeText },
			configurable: true,
		} );

		render( page( FAILED ) );
		fireEvent.click( screen.getByText( 'Copy system data' ) );

		expect( await screen.findByText( 'Copied' ) ).toBeTruthy();
		expect( writeText ).toHaveBeenCalledWith( EXPORT_JSON );
		expect( fetchSpy ).not.toHaveBeenCalled();
		expect( baseContext.sendSystemData ).not.toHaveBeenCalled();
	} );

	it( 'falls back to copying the selected text without the Clipboard API', async () => {
		document.execCommand = jest.fn( () => true );

		render( page( FAILED ) );
		fireEvent.click( screen.getByText( 'Copy system data' ) );

		expect( await screen.findByText( 'Copied' ) ).toBeTruthy();
		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		delete document.execCommand;
	} );

	it( 'downloads the exact payload as a named JSON file without making a request', async () => {
		let blob;
		let clicked;
		window.URL.createObjectURL = jest.fn( ( value ) => {
			blob = value;
			return 'blob:system-data';
		} );
		window.URL.revokeObjectURL = jest.fn();
		jest.spyOn(
			window.HTMLAnchorElement.prototype,
			'click'
		).mockImplementation( function () {
			clicked = { download: this.download, href: this.href };
		} );

		render( page( FAILED ) );
		fireEvent.click( screen.getByText( 'Download as a file' ) );

		expect( clicked ).toEqual( {
			download: 'gtmkit-system-data-shop.example.test-2026-09-11.json',
			href: 'blob:system-data',
		} );
		expect( blob.type ).toBe( 'application/json' );
		expect( await readBlob( blob ) ).toBe( EXPORT_JSON );
		expect( window.URL.revokeObjectURL ).toHaveBeenCalledWith(
			'blob:system-data'
		);
		expect( fetchSpy ).not.toHaveBeenCalled();
	} );
} );
