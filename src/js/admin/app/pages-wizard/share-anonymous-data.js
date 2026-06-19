/*WordPress*/
import { useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

import { useNavigate } from 'react-router-dom';

/*Inbuilt Context*/
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { SiteDataContext } from '../../context/SiteDataContext';

const ShareAnonymousData = () => {
	const {
		useSettings,
		updateStateSettings,
		updateSettings,
		isPending: useIsPending,
	} = useContext( SettingsDataContext );
	const { useSiteData } = useContext( SiteDataContext );
	const navigate = useNavigate();

	return (
		<>
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading gtmkit-text-center">
				{ __( 'Help improve GTM Kit', 'gtm-kit' ) }
			</h1>
			<p className="gtmkit-text-sm gtmkit-mb-4 gtmkit-text-color-grey">
				{ __(
					'GTM Kit is used together with a wide variety of server configurations and plugins. It is very helpful for us to know what some of these configurations are so we can test the most common configurations.',
					'gtm-kit'
				) }
			</p>
			<p className="gtmkit-text-sm gtmkit-mb-8 gtmkit-text-color-grey">
				{ __(
					'You can help by sharing anonymous data with us. Below is a detailed view of all data GTM Kit will collect if granted permission:',
					'gtm-kit'
				) }
			</p>

			<table className="gtmkit-border-2 gtmkit-table-fixed gtmkit-w-full gtmkit-text-sm">
				<tbody>
					<tr className="">
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>Server type:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.web_server }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>PHP version number:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.php_version }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>WordPress version number:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.wordpress_version }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>WordPress multisite:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.multisite
									? __( 'Yes', 'gtm-kit' )
									: __( 'No', 'gtm-kit' ) }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>Current theme:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.current_theme }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>Current site language:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<code className="gtmkit-text-sm">
								{ useSiteData.locale }
							</code>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>Active plugins:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<em>Plugin names of all active plugins</em>
						</td>
					</tr>
					<tr>
						<td className="gtmkit-font-bold gtmkit-px-2 gtmkit-py-1">
							<strong>Anonymized GTM Kit settings:</strong>
						</td>
						<td className="gtmkit-px-2 gtmkit-py-1">
							<em>Which GTM Kit settings are active</em>
						</td>
					</tr>
				</tbody>
			</table>

			<div className="gtmkit-settings-field-wrap gtmkit-max-w-max !gtmkit-px-8 gtmkit-mx-auto ">
				<BaseControl
					label={ __( 'Share anonymous data', 'gtm-kit' ) }
					id="share-anonymous-data"
				>
					<ToggleControl
						label={ __(
							'I agree to share anonymous data with the development team to help improve GTM Kit.',
							'gtm-kit'
						) }
						checked={
							useSettings && useSettings.general.analytics_active
						}
						onChange={ () => {
							updateStateSettings(
								'general',
								'analytics_active',
								! (
									useSettings &&
									useSettings.general.analytics_active
								)
							);
						} }
					/>
				</BaseControl>
			</div>

			<div className="gtmkit-flex gtmkit-mt-12">
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						updateSettings();
						navigate( '/automatic-updates', { replace: true } );
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

export default ShareAnonymousData;
