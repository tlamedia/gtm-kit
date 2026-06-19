import { Button, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { useNavigate } from 'react-router-dom';
import GtmIdHelpPopup from '../atoms/gtm-id-help-popup';
import { validateGtmId, normalizeGtmId } from '../../utils/gtm-validation';

const RegisterContainer = () => {
	const {
		useSettings,
		updateStateSettings,
		updateSettings,
		isPending: useIsPending,
	} = useContext( SettingsDataContext );
	const navigate = useNavigate();

	return (
		<>
			<div className="md:gtmkit-grid gtmkit-grid-cols-2 gtmkit-gap-16 gtmkit-mb-8">
				<div className="">
					<div className="gtmkit-settings-field-wrap gtmkit-w-full !gtmkit-px-8 ">
						<TextControl
							label={ __( 'Container ID', 'gtm-kit' ) }
							placeholder={ __(
								'Enter Container ID',
								'gtm-kit'
							) }
							value={ useSettings && useSettings.general.gtm_id }
							className={ 'gtmkit-text-center' }
							onChange={ ( newVal ) =>
								updateStateSettings(
									'general',
									'gtm_id',
									newVal
								)
							}
							onBlur={ ( e ) => {
								const normalized = normalizeGtmId(
									e.target.value
								);
								if ( normalized !== e.target.value ) {
									updateStateSettings(
										'general',
										'gtm_id',
										normalized
									);
								}
							} }
							help={
								<div className="gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1">
									<span>
										{ __(
											'Find your Container ID in Google Tag Manager',
											'gtm-kit'
										) }
									</span>
									<GtmIdHelpPopup />
								</div>
							}
						/>
					</div>

					<div className="gtmkit-flex gtmkit-mt-12">
						<Button
							variant={ 'primary' }
							className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
							onClick={ () => {
								updateSettings();
								navigate( '/essential-settings', {
									replace: true,
								} );
							} }
							disabled={
								useIsPending ||
								! validateGtmId( useSettings.general.gtm_id )
							}
						>
							{ __( 'Save and continue', 'gtm-kit' ) }
							{ useIsPending ? <Spinner /> : '' }
						</Button>
					</div>
				</div>
				<div className="gtmkit-mt-8 gtmkit-border gtmkit-p-6 gtmkit-text-color-grey gtmkit-text-center">
					<h2 className="gtmkit-font-bold gtmkit-text-base gtmkit-mb-4">
						Need help?
					</h2>
					<p className="gtmkit-mb-6">
						{ __( 'Find your GTM container ID on', 'gtm-kit' ) }
						<a
							className="gtmkit-ml-2 gtmkit-text-color-primary gtmkit-underline"
							href="https://tagmanager.google.com/"
							target={ '_blank' }
							rel="noreferrer"
						>
							Google Tag Manager
						</a>
					</p>

					<p>
						{ __(
							'It should look something like this:',
							'gtm-kit'
						) }{ ' ' }
						GTM-12ZM7SF4
					</p>
				</div>
			</div>
		</>
	);
};

export default RegisterContainer;
