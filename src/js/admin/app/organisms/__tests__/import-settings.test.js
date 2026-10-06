/**
 * Covers the wizard import offer: the source list rendered from the install
 * payload, and the Yes action handing the selected source's settings object to
 * the settings context so the imported values land in the settings state, and
 * the note explaining a server-side tagging domain that was not imported.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the stub components below, flagging a phantom `@types/react`.
 * Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';
import { useContext } from '@wordpress/element';

import ImportSettings from '../import-settings';
import {
	SettingsDataContext,
	SettingsDataProvider,
} from '../../../context/SettingsDataContext';
import { SiteDataContext } from '../../../context/SiteDataContext';

// The post-import screen pulls in routing and GTM ID helpers we don't exercise.
jest.mock( '../register-container', () => ( {
	__esModule: true,
	default: () => <div data-testid="register-container" />,
} ) );

// Shaped like PluginDataImport::get_all() output for a site with GTM4WP data.
const installData = {
	importAvailable: true,
	import_data: {
		gtm4wp: {
			name: 'GTM4WP',
			general: {
				gtm_id: 'GTM-ABC123',
				datalayer_name: 'dataLayer',
			},
			integrations: {
				woocommerce_integration: 'On',
			},
		},
		google_tag_manager: {
			name: 'Google Tag Manager',
			general: {
				gtm_id: 'GTM-XYZ789',
			},
		},
	},
};

// Renders the settings state so assertions can read what the import produced.
const SettingsProbe = () => {
	const { settings } = useContext( SettingsDataContext );

	return <span data-testid="settings">{ JSON.stringify( settings ) }</span>;
};

const renderImportSettings = ( data = installData ) =>
	render(
		<SettingsDataProvider>
			<SiteDataContext.Provider value={ { useInstallData: data } }>
				<ImportSettings />
				<SettingsProbe />
			</SiteDataContext.Provider>
		</SettingsDataProvider>
	);

const readSettings = () =>
	JSON.parse( screen.getByTestId( 'settings' ).textContent );

describe( 'ImportSettings', () => {
	it( 'lists every available source by name', () => {
		renderImportSettings();

		expect( screen.getByLabelText( 'GTM4WP' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Google Tag Manager' )
		).toBeInTheDocument();
	} );

	it( 'applies the selected source settings to the settings state', () => {
		renderImportSettings();

		fireEvent.click( screen.getByText( 'Yes' ) );

		const settings = readSettings();

		expect( settings.general.gtm_id ).toBe( 'GTM-ABC123' );
		expect( settings.general.datalayer_name ).toBe( 'dataLayer' );
		expect( settings.integrations.woocommerce_integration ).toBe( 'On' );
	} );

	it( 'imports the source the user selects rather than the default', () => {
		renderImportSettings();

		fireEvent.click( screen.getByLabelText( 'Google Tag Manager' ) );
		fireEvent.click( screen.getByText( 'Yes' ) );

		expect( readSettings().general.gtm_id ).toBe( 'GTM-XYZ789' );
	} );

	it( 'moves on to the container step after importing', () => {
		renderImportSettings();

		fireEvent.click( screen.getByText( 'Yes' ) );

		expect(
			screen.getByTestId( 'register-container' )
		).toBeInTheDocument();
	} );

	it( 'skips the import and moves on when the user declines', () => {
		renderImportSettings();

		fireEvent.click( screen.getByText( 'No' ) );

		expect( readSettings().general.gtm_id ).toBeUndefined();
		expect(
			screen.getByTestId( 'register-container' )
		).toBeInTheDocument();
	} );

	describe( 'skipped server-side tagging domain', () => {
		const message =
			'The server-side tagging domain was not imported, because GTM4WP loads your container from a custom path that GTM Kit cannot reproduce. Set up server-side tagging in GTM Kit by hand after importing.';

		const withSkippedDomain = {
			...installData,
			import_data: {
				...installData.import_data,
				gtm4wp: {
					...installData.import_data.gtm4wp,
					sgtm_domain_skipped: true,
				},
			},
		};

		// The notice also announces its text through a screen-reader live
		// region that outlives each render, so only rendered notices count.
		const noticeCount = () =>
			[ ...document.querySelectorAll( '.components-notice' ) ].filter(
				( notice ) => notice.textContent.includes( message )
			).length;

		it( 'explains the skipped domain before the user imports', () => {
			renderImportSettings( withSkippedDomain );

			expect( noticeCount() ).toBe( 1 );
		} );

		it( 'says nothing when the source handed its domain over', () => {
			renderImportSettings();

			expect( noticeCount() ).toBe( 0 );
		} );

		it( 'follows the selected source', () => {
			renderImportSettings( withSkippedDomain );

			fireEvent.click( screen.getByLabelText( 'Google Tag Manager' ) );
			expect( noticeCount() ).toBe( 0 );

			fireEvent.click( screen.getByLabelText( 'GTM4WP' ) );
			expect( noticeCount() ).toBe( 1 );
		} );
	} );
} );
