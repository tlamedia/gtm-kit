/**
 * Covers the sGTM Preview test-send control's live-attach toggle: it is part of
 * the panel proper rather than hidden behind a disclosure, so the checkbox, its
 * help text and the armed banner all render on first paint with no clicking.
 * Also covers the toggle's error path: when the server rejects it with a
 * structured `{ success: false, data: { message } }` body, the control surfaces
 * the server's message string, and falls back to its own hint when no message
 * is supplied. Guards against the failure body's `data` object being rendered
 * verbatim as "[object Object]".
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent, act } from '@testing-library/react';

import SgtmPreviewTest from '../sgtm-preview-test';

jest.mock( '../../../../hooks/useFeatureFlags', () => ( {
	useFeatureFlags: () => ( { meetsRequiredTier: () => true } ),
} ) );

jest.mock( '../../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		isPluginActive: () => false,
		// Read by the locked-field upgrade link this control can render.
		getActiveTier: () => 'free',
	},
} ) );

// Default: a token is armed, so the live-attach toggle is enabled after the
// mount status fetch resolves. Individual tests overwrite this before mounting.
const ARMED_STATUS = {
	armed: true,
	masked_token: '••••1234',
	live_attach: false,
	expires_in: 600,
};

let mockStatus = { ...ARMED_STATUS };

const mockSetWebhookPreviewToken = jest.fn();
jest.mock( '../../../../api/settings', () => ( {
	getWebhookPreviewStatus: () =>
		Promise.resolve( {
			success: true,
			data: {
				status: mockStatus,
				events: [ { value: 'purchase', requiresSubscriptions: false } ],
				subscriptionsActive: false,
			},
		} ),
	setWebhookPreviewToken: ( ...args ) =>
		mockSetWebhookPreviewToken( ...args ),
	clearWebhookPreviewToken: () => Promise.resolve( { success: true } ),
	sendWebhookPreviewTest: () => Promise.resolve( { success: true } ),
} ) );

const FIELD = {
	key: 'general.sgtm_preview_test',
	control: 'sgtm-preview-test',
	label: 'sGTM Preview test send',
	tier: 'premium',
};

const LIVE_ATTACH_LABEL = /Attach the preview header to live webhook traffic/;

const LIVE_ATTACH_HELP =
	/Live orders will appear in Preview while this is on\./;

const ARMED_BANNER = /Live preview header is ON\./;

// Mounts the control, flushing the async mount status fetch inside act() so
// React state settles cleanly.
const mountControl = async () => {
	await act( async () => {
		render( <SgtmPreviewTest field={ FIELD } disabled={ false } /> );
	} );
};

// Mounts the control and flips the live-attach toggle. The toggle sits in the
// panel proper, so no disclosure has to be opened to reach it.
const mountAndToggleLiveAttach = async () => {
	await mountControl();
	const toggle = screen.getByLabelText( LIVE_ATTACH_LABEL );
	await act( async () => {
		fireEvent.click( toggle );
	} );
};

describe( 'SgtmPreviewTest live-attach visibility', () => {
	beforeEach( () => {
		mockSetWebhookPreviewToken.mockReset();
		mockStatus = { ...ARMED_STATUS };
	} );

	it( 'renders the live-attach checkbox without opening a disclosure', async () => {
		await mountControl();

		expect(
			screen.getByLabelText( LIVE_ATTACH_LABEL )
		).toBeInTheDocument();
		expect( screen.queryByText( 'Developer options' ) ).toBeNull();
	} );

	it( 'renders the live-attach help text alongside the checkbox', async () => {
		await mountControl();

		expect( screen.getByText( LIVE_ATTACH_HELP ) ).toBeInTheDocument();
	} );

	it( 'renders the armed banner without opening a disclosure', async () => {
		mockStatus = { ...ARMED_STATUS, live_attach: true };

		await mountControl();

		expect( screen.getByText( ARMED_BANNER ) ).toBeInTheDocument();
		expect( screen.getByLabelText( LIVE_ATTACH_LABEL ) ).toBeChecked();
	} );

	it( 'disables the checkbox with no armed token and no pasted token', async () => {
		mockStatus = { armed: false, live_attach: false };

		await mountControl();

		expect( screen.getByLabelText( LIVE_ATTACH_LABEL ) ).toBeDisabled();
	} );

	it( 'sends the new value when the checkbox is toggled', async () => {
		mockSetWebhookPreviewToken.mockResolvedValue( {
			success: true,
			data: { status: { ...ARMED_STATUS, live_attach: true } },
		} );

		await mountAndToggleLiveAttach();

		expect( mockSetWebhookPreviewToken ).toHaveBeenCalledWith( {
			token: '',
			live_attach: true,
		} );
	} );
} );

describe( 'SgtmPreviewTest live-attach error handling', () => {
	beforeEach( () => {
		mockSetWebhookPreviewToken.mockReset();
		mockStatus = { ...ARMED_STATUS };
	} );

	it( 'shows the server-reported message, not "[object Object]"', async () => {
		mockSetWebhookPreviewToken.mockResolvedValue( {
			success: false,
			data: { message: 'Token expired. Paste a fresh one.' },
		} );

		await mountAndToggleLiveAttach();

		expect(
			screen.getByText( 'Token expired. Paste a fresh one.' )
		).toBeInTheDocument();
		expect( screen.queryByText( /\[object Object\]/ ) ).toBeNull();
	} );

	it( 'falls back to the hint when the failure body has no message', async () => {
		mockSetWebhookPreviewToken.mockResolvedValue( {
			success: false,
			data: {},
		} );

		await mountAndToggleLiveAttach();

		expect(
			screen.getByText(
				'Paste your server container Preview token first.'
			)
		).toBeInTheDocument();
	} );
} );
