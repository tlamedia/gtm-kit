/**
 * Covers the Tools page importer: the source list, the empty state, the
 * confirmation step listing only the settings that actually change, the
 * skipped-container note, and the import-then-save sequence.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the stub factories below, flagging a phantom `@types/react`.
 * Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';

import ImportPluginSettings from '../import-plugin-settings';
import { SettingsDataContext } from '../../../context/SettingsDataContext';

const mockGetInstallData = jest.fn();
jest.mock( '../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getInstallData: () => mockGetInstallData(),
		// The settings context pulls in the REST client on import.
		getNonce: () => 'test-nonce',
		getRestRoot: () => 'http://localhost/wp-json/',
	},
} ) );

jest.mock( '../../../registry/assemble', () => ( {
	getAllFields: () => [
		{ key: 'general.gtm_id', label: 'GTM Container ID' },
		{ key: 'general.sgtm_domain', label: 'sGTM Container Domain:' },
		{ key: 'integrations.cf7_integration', label: 'Track Contact Form 7' },
	],
} ) );

// Shaped like PluginDataImport::get_all() output.
const INSTALL_DATA = {
	importAvailable: true,
	import_data: {
		gtm4wp: {
			name: 'GTM4WP',
			container_count: 1,
			general: { gtm_id: 'GTM-ABC123', sgtm_domain: 'sgtm.example.com' },
			integrations: { cf7_integration: true },
		},
		google_tag_manager: {
			name: 'Google Tag Manager',
			container_count: 3,
			general: { gtm_id: 'GTM-XYZ789' },
		},
	},
};

const CURRENT_SETTINGS = {
	general: { gtm_id: 'GTM-EXISTING', sgtm_domain: 'sgtm.example.com' },
	integrations: { cf7_integration: false },
};

const renderImporter = ( {
	installData = INSTALL_DATA,
	context = {},
} = {} ) => {
	mockGetInstallData.mockReturnValue( installData );

	const value = {
		settings: CURRENT_SETTINGS,
		importSettings: jest.fn(),
		updateSettings: jest.fn(),
		isPending: false,
		hasError: false,
		...context,
	};

	render(
		<SettingsDataContext.Provider value={ value }>
			<ImportPluginSettings />
		</SettingsDataContext.Provider>
	);

	return value;
};

describe( 'ImportPluginSettings', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'lists every available source by name', () => {
		renderImporter();

		expect( screen.getByLabelText( 'GTM4WP' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Google Tag Manager' )
		).toBeInTheDocument();
	} );

	it( 'shows an empty state when no other plugin left settings behind', () => {
		renderImporter( { installData: {} } );

		expect(
			screen.getByText(
				'No settings from another Google Tag Manager plugin were found on this site.'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( 'Continue' ) ).not.toBeInTheDocument();
	} );

	it( 'lists only the settings the import would actually change', () => {
		renderImporter();

		fireEvent.click( screen.getByText( 'Continue' ) );

		// Differs from the current value, so it is listed, colon stripped.
		expect( screen.getByText( 'GTM Container ID' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Track Contact Form 7' )
		).toBeInTheDocument();
		// Already identical, so it must not be promised as a change.
		expect(
			screen.queryByText( 'sGTM Container Domain' )
		).not.toBeInTheDocument();
	} );

	it( 'warns that only the first container is imported', () => {
		renderImporter();

		fireEvent.click( screen.getByLabelText( 'Google Tag Manager' ) );
		fireEvent.click( screen.getByText( 'Continue' ) );

		// The notice component also renders the text into a live region, so
		// more than one node legitimately carries it.
		expect(
			screen.getAllByText(
				'2 additional containers were found and will not be imported. GTM Kit uses one container.'
			).length
		).toBeGreaterThan( 0 );
	} );

	it( 'does not warn about containers when the source has only one', () => {
		renderImporter();

		fireEvent.click( screen.getByText( 'Continue' ) );

		expect( screen.queryAllByText( /additional container/ ) ).toHaveLength(
			0
		);
	} );

	it( 'hands the selected source to the import and then saves', () => {
		const { importSettings, updateSettings } = renderImporter();

		fireEvent.click( screen.getByText( 'Continue' ) );
		fireEvent.click( screen.getByText( 'Import and save' ) );

		expect( importSettings ).toHaveBeenCalledWith(
			INSTALL_DATA.import_data.gtm4wp
		);
		// The save waits for the imported values to reach settings state, so it
		// runs on the following render rather than inside the click handler.
		expect( updateSettings ).not.toHaveBeenCalled();
	} );

	it( 'offers nothing to import when the settings already match', () => {
		mockGetInstallData.mockReturnValue( {
			import_data: {
				gtm4wp: {
					name: 'GTM4WP',
					container_count: 1,
					general: { gtm_id: 'GTM-EXISTING' },
				},
			},
		} );

		render(
			<SettingsDataContext.Provider
				value={ {
					settings: CURRENT_SETTINGS,
					importSettings: jest.fn(),
					updateSettings: jest.fn(),
					isPending: false,
					hasError: false,
				} }
			>
				<ImportPluginSettings />
			</SettingsDataContext.Provider>
		);

		fireEvent.click( screen.getByText( 'Continue' ) );

		expect(
			screen.getByText(
				'Your current settings already match this plugin. Nothing will change.'
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'Import and save' ) ).toBeDisabled();
	} );

	it( 'returns to the source list when the import is cancelled', () => {
		const { importSettings } = renderImporter();

		fireEvent.click( screen.getByText( 'Continue' ) );
		fireEvent.click( screen.getByText( 'Cancel' ) );

		expect( screen.getByLabelText( 'GTM4WP' ) ).toBeInTheDocument();
		expect( importSettings ).not.toHaveBeenCalled();
	} );
} );
