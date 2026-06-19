/*Inbuilt Components*/
import { __, sprintf } from '@wordpress/i18n';
import { memo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import SectionBox from './section-box';
import SettingsService from '../../services/SettingsService';

const PluginInactive = memo( ( { pluginName } ) => {
	return (
		<SectionBox>
			<SectionBox.Header
				title={ sprintf(
					// translators: %s is the name of the plugin.
					__( '%s is not active', 'gtm-kit' ),
					pluginName
				) }
				className={ 'gtmkit-text-red-600' }
			/>
			<SectionBox.Content>
				<p className="gtmkit-mb-6">
					{ sprintf(
						// translators: %s is the name of the plugin.
						__(
							"If you haven't installed and activated %s you must do that.",
							'gtm-kit'
						),
						pluginName
					) }
				</p>
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-4 !gtmkit-px-6 gtmkit-text-sm disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						window.location.href =
							SettingsService.getPluginInstallUrl() + pluginName;
					} }
				>
					{ sprintf(
						// translators: %s is the name of the plugin.
						__( 'Install %s', 'gtm-kit' ),
						pluginName
					) }
				</Button>
			</SectionBox.Content>
		</SectionBox>
	);
} );

export default PluginInactive;
