/**
 * Covers the Support page's live-sync surface: the active-sync indicator
 * with ticket and end date, the Stop sharing control wiring, and the
 * share form staying available while no sync session is active.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';

import SupportPage from '../SupportPage';
import { SupportContext } from '../../../context/SupportContext';

jest.mock( '../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getSupportSync: () => ( { active: false } ),
		getTutorials: () => [],
		getOpportunities: () => ( {} ),
		isPremiumPlugin: () => false,
		// Consumed by the api/settings module SupportContext pulls in.
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

const baseContext = {
	supportTicket: '',
	isSendingSystemData: false,
	isSystemDataSent: false,
	systemDataMessage: '',
	supportSync: { active: false },
	isStoppingSupportSync: false,
	updateSupportTicket: jest.fn(),
	sendSystemData: jest.fn(),
	stopSupportSync: jest.fn(),
	useSupportTicket: '',
	useIsSendingSystemData: false,
	useIsSystemDataSent: false,
	useSystemDataMessage: '',
};

const renderPage = ( overrides = {} ) =>
	render(
		<SupportContext.Provider value={ { ...baseContext, ...overrides } }>
			<SupportPage />
		</SupportContext.Provider>
	);

describe( 'SupportPage live support sync', () => {
	it( 'shows the sync indicator and Stop sharing while a session is active', () => {
		const stopSupportSync = jest.fn();
		renderPage( {
			supportSync: {
				active: true,
				ticket: 'FS123-ABC45',
				until: 'July 16, 2026',
			},
			stopSupportSync,
		} );

		expect(
			screen.getByText(
				'System data syncs to support while ticket FS123-ABC45 is open, until July 16, 2026.'
			)
		).toBeInTheDocument();

		// The share form is replaced by the indicator while syncing.
		expect( screen.queryByLabelText( 'Support ticket' ) ).toBeNull();

		fireEvent.click( screen.getByText( 'Stop sharing' ) );
		expect( stopSupportSync ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'disables the Stop sharing button while the stop request runs', () => {
		renderPage( {
			supportSync: {
				active: true,
				ticket: 'FS123-ABC45',
				until: 'July 16, 2026',
			},
			isStoppingSupportSync: true,
		} );

		expect(
			screen.getByText( 'Stop sharing' ).closest( 'button' )
		).toBeDisabled();
	} );

	it( 'shows the share form when no session is active', () => {
		renderPage();

		expect( screen.getByLabelText( 'Support ticket' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Stop sharing' ) ).toBeNull();
	} );
} );
