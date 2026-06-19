/*WordPress*/
import { useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';

import { useNavigate } from 'react-router-dom';

/*Inbuilt Context*/
import { SettingsDataContext } from '../../context/SettingsDataContext';
import ToggleSetting from '../atoms/toggle-setting';

const AutomaticUpdates = () => {
	const {
		useSettings,
		updateStateSettings,
		updateSettings,
		isPending: useIsPending,
	} = useContext( SettingsDataContext );
	const navigate = useNavigate();

	return (
		<>
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading gtmkit-text-center">
				{ __( 'Automatic Updates', 'gtm-kit' ) }
			</h1>
			<p className="gtmkit-text-sm gtmkit-mb-4 gtmkit-text-color-grey">
				{ __(
					'New releases of GTM Kit may contain important updates to comply with changes in Google Tag Manager or analytics in general. We recommend enabling automatic plugin updates for GTM Kit to ensure it is always up to date.',
					'gtm-kit'
				) }
			</p>
			<p className="gtmkit-text-sm gtmkit-mb-8 gtmkit-text-color-grey">
				{ __(
					'You can, of course, manually update GTM Kit whenever it suits you, but we highly recommend that you regularly update your plugins and themes to the latest versions to keep your site secure.',
					'gtm-kit'
				) }
			</p>

			<div className="gtmkit-max-w-max gtmkit-mx-auto gtmkit-mt-12">
				<ToggleSetting
					title={ __( 'Enable Automatic Updates', 'gtm-kit' ) }
					label={ __(
						'Automatically update the GTM Kit plugin when new releases are available.',
						'gtm-kit'
					) }
					optionGroup={ 'misc' }
					optionName={ 'auto_update' }
					useSettings={ useSettings }
					updateStateSettings={ updateStateSettings }
				/>
			</div>

			<div className="gtmkit-flex gtmkit-mt-12">
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						updateSettings();
						navigate( '/getting-started', { replace: true } );
					} }
					disabled={ useIsPending }
				>
					{ __( 'Save and continue', 'gtm-kit' ) }
					{ useIsPending ? <Spinner /> : '' }
				</Button>
			</div>
		</>
	);
};

export default AutomaticUpdates;
