import { useState, useContext } from '@wordpress/element';
import { Button, Spinner, RadioControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { SiteDataContext } from '../../context/SiteDataContext';
import RegisterContainer from './register-container';

const ImportSettings = () => {
	const { importSettings, isPending: useIsPending } =
		useContext( SettingsDataContext );
	const { useInstallData } = useContext( SiteDataContext );

	const [ showContent, setShowContent ] = useState( true );

	const defaultOption = Object.keys( useInstallData.import_data )[ 0 ];
	const [ importOption, setImportOption ] = useState( defaultOption );

	const importOptions = [];

	Object.keys( useInstallData.import_data ).forEach( ( key ) => {
		importOptions.push( {
			value: key,
			label: useInstallData.import_data[ key ].name,
		} );
	} );

	return showContent ? (
		<>
			<div className="gtmkit-max-w-lg gtmkit-mx-auto gtmkit-border gtmkit-p-8 gtmkit-my-8 gtmkit-text-left">
				<h2 className="gtmkit-text-lg gtmkit-text-color-heading gtmkit-font-bold">
					{ __(
						'Would you like to import plugin settings?',
						'gtm-kit'
					) }
				</h2>
				<p className="gtmkit-text-base gtmkit-my-4 gtmkit-text-color-grey">
					{ __(
						'We have found the configuration of other plugins in the database.',
						'gtm-kit'
					) }
					&nbsp;
					{ __(
						'Would you like to import your settings to GTM Kit?',
						'gtm-kit'
					) }
				</p>
				<RadioControl
					help={ __(
						'Select the plugin you want to import settings from.',
						'gtm-kit'
					) }
					selected={ importOption }
					options={ importOptions }
					onChange={ ( value ) => setImportOption( value ) }
				/>

				<div className="gtmkit-flex gtmkit-mt-12">
					<Button
						variant={ 'primary' }
						className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base"
						onClick={ () => {
							importSettings(
								importOption,
								useInstallData.import_data[ importOption ]
							);
							setShowContent( false );
						} }
					>
						<span className="gtmkit-text-lg gtmkit-font-bold">
							{ __( 'Yes', 'gtm-kit' ) }
						</span>
						{ useIsPending ? <Spinner /> : '' }
					</Button>

					<Button
						variant={ 'primary' }
						className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base"
						onClick={ () => {
							setShowContent( false );
						} }
					>
						<span className="gtmkit-text-lg gtmkit-font-bold">
							{ __( 'No', 'gtm-kit' ) }
						</span>
						{ useIsPending ? <Spinner /> : '' }
					</Button>
				</div>
			</div>
		</>
	) : (
		<RegisterContainer />
	);
};

export default ImportSettings;
