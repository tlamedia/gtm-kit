/**
 * Covers the wizard's first step: the import offer appears whenever another
 * plugin's settings are available, including on a re-run of the wizard, and the
 * container step is shown when there is nothing to import.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the stub factories below, flagging a phantom `@types/react`.
 * Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import { render, screen } from '@testing-library/react';

import Welcome from '../welcome';
import { SiteDataContext } from '../../../context/SiteDataContext';

jest.mock( '../../organisms/import-settings', () => ( {
	__esModule: true,
	default: () => <div data-testid="import-settings" />,
} ) );

jest.mock( '../../organisms/register-container', () => ( {
	__esModule: true,
	default: () => <div data-testid="register-container" />,
} ) );

const renderWelcome = ( installData ) =>
	render(
		<SiteDataContext.Provider value={ { useInstallData: installData } }>
			<Welcome />
		</SiteDataContext.Provider>
	);

describe( 'Welcome', () => {
	it( 'offers the import whenever another plugin has settings to import', () => {
		renderWelcome( { importAvailable: true } );

		expect( screen.getByTestId( 'import-settings' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'register-container' )
		).not.toBeInTheDocument();
	} );

	it( 'still offers the import on a wizard re-run, not just a fresh install', () => {
		// The payload no longer carries a first-install flag; anything left in
		// it must not suppress the offer.
		renderWelcome( { importAvailable: true, firstInstall: false } );

		expect( screen.getByTestId( 'import-settings' ) ).toBeInTheDocument();
	} );

	it( 'goes straight to the container step when there is nothing to import', () => {
		renderWelcome( { importAvailable: false } );

		expect(
			screen.getByTestId( 'register-container' )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'import-settings' )
		).not.toBeInTheDocument();
	} );
} );
