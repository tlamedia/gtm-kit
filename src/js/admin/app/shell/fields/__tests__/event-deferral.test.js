/**
 * Covers the event-deferral composite shell control: a stored config renders
 * back exactly, every write is the full nested config object (never a bare
 * boolean), unchecking an event deletes it from the complete defer set, the
 * timeout is clamped to its bounds, the strong-block notice appears, and the
 * disabled/tier-locked states hold the control inert.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';

import { SettingsDataContext } from '../../../../context/SettingsDataContext';
import { getShellCompositeControl } from '../composite';
import ShellEventDeferral from '../event-deferral';

let mockMeetsTier = true;
jest.mock( '../../../../hooks/useFeatureFlags', () => ( {
	useFeatureFlags: () => ( { meetsRequiredTier: () => mockMeetsTier } ),
} ) );

jest.mock( '../../../../context/SettingsDataContext', () => {
	const React = require( 'react' );
	return { SettingsDataContext: React.createContext( {} ) };
} );

let mockConsentBadges = [];
jest.mock( '../../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getNonce: () => 'test-nonce',
		getRestRoot: () => '/wp-json/',
		getConsentAdminBadges: () => mockConsentBadges,
	},
} ) );

const WP_CONSENT_API_BADGE = {
	id: 'wp_consent_api',
	message: 'WP Consent API is the active consent source.',
	severity: 'info',
};

const FIELD = {
	key: 'premium.event_deferral_queue',
	capability: 'consent',
	section: 'event-deferral',
	order: 10,
	control: 'event-deferral',
	label: 'Defer events until consent is granted',
	tier: 'premium',
	integration: null,
};

const PRESET = [
	'add_to_cart',
	'begin_checkout',
	'add_payment_info',
	'add_shipping_info',
	'purchase',
	'view_cart',
];
const IMMEDIATE = [ 'view_item', 'view_item_list', 'page_view' ];

function renderControl( {
	premium = {},
	general = {},
	disabled = false,
} = {} ) {
	const updateStateSettings = jest.fn();
	const settings = { general, integrations: {}, premium };
	render(
		<SettingsDataContext.Provider
			value={ { settings, updateStateSettings } }
		>
			<ShellEventDeferral field={ FIELD } disabled={ disabled } />
		</SettingsDataContext.Provider>
	);
	return { updateStateSettings };
}

const STORED = {
	enabled: true,
	events: { purchase: true, page_view: true },
	timeout_ms: 5000,
	expiry_mode: 'drop',
	required_categories: [ 'analytics_storage', 'ad_storage' ],
};

beforeEach( () => {
	mockMeetsTier = true;
	mockConsentBadges = [];
} );

describe( 'event-deferral composite control', () => {
	it( 'is registered in the composite control map', () => {
		expect( getShellCompositeControl( 'event-deferral' ) ).toBe(
			ShellEventDeferral
		);
	} );

	it( 'renders a stored config back exactly', () => {
		renderControl( { premium: { event_deferral_queue: STORED } } );

		expect(
			screen.getByRole( 'switch', { name: FIELD.label } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'purchase' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'page_view' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', { name: 'add_to_cart' } )
		).not.toBeChecked();
		expect(
			screen.getByRole( 'spinbutton', { name: 'Seconds to wait' } )
		).toHaveValue( 5 );
		expect(
			screen.getByRole( 'radio', {
				name: 'Drop the queued events (never fire without consent)',
			} )
		).toBeChecked();
		expect(
			screen.getByText( 'analytics_storage, ad_storage' )
		).toBeInTheDocument();
	} );

	it( 'writes the full config object when toggled off, never a boolean', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: STORED },
		} );

		fireEvent.click( screen.getByRole( 'switch', { name: FIELD.label } ) );

		expect( updateStateSettings ).toHaveBeenCalledTimes( 1 );
		const [ group, key, value ] = updateStateSettings.mock.calls[ 0 ];
		expect( group ).toBe( 'premium' );
		expect( key ).toBe( 'event_deferral_queue' );
		expect( value ).toEqual( { ...STORED, enabled: false } );
	} );

	it( 'preselects the recommended preset when first enabled', () => {
		const { updateStateSettings } = renderControl();

		fireEvent.click( screen.getByRole( 'switch', { name: FIELD.label } ) );

		const value = updateStateSettings.mock.calls[ 0 ][ 2 ];
		expect( value.enabled ).toBe( true );
		expect( value.timeout_ms ).toBe( 3000 );
		expect( value.expiry_mode ).toBe( 'flush' );
		PRESET.forEach( ( name ) =>
			expect( value.events[ name ] ).toBe( true )
		);
		IMMEDIATE.forEach( ( name ) =>
			expect( value.events[ name ] ).toBeUndefined()
		);
	} );

	it( 'deletes an unchecked event from the complete defer set', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
		} );

		fireEvent.click( screen.getByRole( 'checkbox', { name: 'purchase' } ) );

		const value = updateStateSettings.mock.calls[ 0 ][ 2 ];
		// The stored map is the complete defer set, so an unchecked event is
		// omitted, not written as false.
		expect( 'purchase' in value.events ).toBe( false );
		expect( value.events.add_to_cart ).toBe( true );
	} );

	it( 'opts an immediate event into deferral', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
		} );

		fireEvent.click(
			screen.getByRole( 'checkbox', { name: 'view_item' } )
		);

		expect(
			updateStateSettings.mock.calls[ 0 ][ 2 ].events.view_item
		).toBe( true );
	} );

	it( 'stores the timeout in milliseconds and clamps above the maximum', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
		} );

		fireEvent.change(
			screen.getByRole( 'spinbutton', { name: 'Seconds to wait' } ),
			{ target: { value: '99' } }
		);

		expect( updateStateSettings.mock.calls[ 0 ][ 2 ].timeout_ms ).toBe(
			30000
		);
	} );

	it( 'clamps the timeout below the minimum', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
		} );

		fireEvent.change(
			screen.getByRole( 'spinbutton', { name: 'Seconds to wait' } ),
			{ target: { value: '0' } }
		);

		expect( updateStateSettings.mock.calls[ 0 ][ 2 ].timeout_ms ).toBe(
			1000
		);
	} );

	it( 'switches the expiry mode to drop', () => {
		const { updateStateSettings } = renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
		} );

		fireEvent.click(
			screen.getByRole( 'radio', {
				name: 'Drop the queued events (never fire without consent)',
			} )
		);

		expect( updateStateSettings.mock.calls[ 0 ][ 2 ].expiry_mode ).toBe(
			'drop'
		);
	} );

	it( 'shows the inert-mode notice in strong-block gating mode', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { consent_gating_mode: 'strong_block' },
		} );

		expect(
			screen.getByText( /per-event deferral stays inert/ )
		).toBeInTheDocument();
	} );

	it( 'hides the inert-mode notice in other gating modes', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { consent_gating_mode: 'consent_mode' },
		} );

		expect(
			screen.queryByText( /per-event deferral stays inert/ )
		).not.toBeInTheDocument();
	} );

	it( 'warns when deferral is on with no GCM activation and no consent source', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { gcm_default_settings: false },
		} );

		expect(
			screen.getByText( /needs a consent source to release events/ )
		).toBeInTheDocument();
	} );

	it( 'hides the warning when the WP Consent API supplies the consent source', () => {
		mockConsentBadges = [ WP_CONSENT_API_BADGE ];
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { gcm_default_settings: false },
		} );

		expect(
			screen.queryByText( /needs a consent source to release events/ )
		).not.toBeInTheDocument();
	} );

	it( 'still warns when consent badges are present but none are the WP Consent API', () => {
		mockConsentBadges = [
			{
				id: 'other_source',
				message: 'Something else.',
				severity: 'info',
			},
		];
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { gcm_default_settings: false },
		} );

		expect(
			screen.getByText( /needs a consent source to release events/ )
		).toBeInTheDocument();
	} );

	it( 'hides the warning once GCM activation is on', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			general: { gcm_default_settings: true },
		} );

		expect(
			screen.queryByText( /needs a consent source to release events/ )
		).not.toBeInTheDocument();
	} );

	it( 'does not warn about the consent source when deferral is off', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: false } },
			general: { gcm_default_settings: false },
		} );

		expect(
			screen.queryByText( /needs a consent source to release events/ )
		).not.toBeInTheDocument();
	} );

	it( 'holds the control inert when disabled', () => {
		renderControl( {
			premium: { event_deferral_queue: { enabled: true } },
			disabled: true,
		} );

		const pill = screen.getByRole( 'switch', { name: FIELD.label } );
		expect( pill ).toBeDisabled();
		expect( pill ).not.toBeChecked();
		expect( screen.queryAllByRole( 'checkbox' ) ).toHaveLength( 0 );
	} );

	it( 'shows the upsell when the license tier does not unlock the field', () => {
		mockMeetsTier = false;
		renderControl( { disabled: true } );

		expect(
			screen.getByText(
				'Upgrade to GTM Kit Premium to manage this setting here.'
			)
		).toBeInTheDocument();
	} );
} );
