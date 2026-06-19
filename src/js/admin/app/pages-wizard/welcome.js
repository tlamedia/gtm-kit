/*WordPress*/
import { useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/*Inbuilt Context*/
import { SiteDataContext } from '../../context/SiteDataContext';

/*Inbuilt Components*/
import RegisterContainer from '../organisms/register-container';
import ImportSettings from '../organisms/import-settings';

const Welcome = () => {
	const { useInstallData } = useContext( SiteDataContext );
	const { firstInstall } = useInstallData;
	const { importAvailable } = useInstallData;

	return (
		<div className="gtmkit-text-center">
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading">
				{ __( "You've successfully installed GTM Kit!", 'gtm-kit' ) }
			</h1>

			<div className="gtmkit-max-w-lg gtmkit-mx-auto">
				<p className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey">
					{ __(
						'To start collecting data with Google Tag manager you must register the Container ID of your Google Tag Manager container.',
						'gtm-kit'
					) }
				</p>
			</div>

			{ firstInstall && importAvailable ? (
				<ImportSettings />
			) : (
				<RegisterContainer />
			) }
		</div>
	);
};

export default Welcome;
