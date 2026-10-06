import { useState, useContext } from '@wordpress/element';
import { Button, Notice, Spinner, RadioControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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

	const source = useInstallData.import_data[ importOption ] || {};
	const sourceName = source.name || importOption;

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

				{ source.sgtm_domain_skipped && (
					<Notice
						status="info"
						isDismissible={ false }
						className="gtmkit-mt-4"
					>
						{ sprintf(
							/* translators: %s: name of the plugin the settings are imported from. */
							__(
								'The server-side tagging domain was not imported, because %s loads your container from a custom path that GTM Kit cannot reproduce. Set up server-side tagging in GTM Kit by hand after importing.',
								'gtm-kit'
							),
							sourceName
						) }
					</Notice>
				) }

				<div className="gtmkit-flex gtmkit-mt-12">
					<Button
						variant={ 'primary' }
						className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base"
						onClick={ () => {
							importSettings( source );
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
