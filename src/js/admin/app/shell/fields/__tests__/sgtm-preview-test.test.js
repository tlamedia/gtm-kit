/**
 * Covers the sGTM Preview test-send control's live-attach error path: when the
 * server rejects the toggle with a structured `{ success: false, data: { message } }`
 * body, the control surfaces the server's message string, and falls back to its
 * own hint when no message is supplied. Guards against the failure body's `data`
 * object being rendered verbatim as "[object Object]".
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
	},
} ) );

const mockSetWebhookPreviewToken = jest.fn();
jest.mock( '../../../../api/settings', () => ( {
	// A token is armed so the live-attach toggle is enabled after the mount
	// status fetch resolves.
	getWebhookPreviewStatus: () =>
		Promise.resolve( {
			success: true,
			data: {
				status: {
					armed: true,
					masked_token: '••••1234',
					live_attach: false,
					expires_in: 600,
				},
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

// Mounts the control and flips it to the live-attach toggle, flushing the
// async mount status fetch inside act() so React state settles cleanly.
const mountAndToggleLiveAttach = async () => {
	await act( async () => {
		render( <SgtmPreviewTest field={ FIELD } disabled={ false } /> );
	} );
	fireEvent.click( screen.getByText( 'Developer options' ) );
	const toggle = screen.getByLabelText(
		/Attach the preview header to live webhook traffic/
	);
	await act( async () => {
		fireEvent.click( toggle );
	} );
};

describe( 'SgtmPreviewTest live-attach error handling', () => {
	beforeEach( () => {
		mockSetWebhookPreviewToken.mockReset();
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
