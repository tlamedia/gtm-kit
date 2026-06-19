/**
 * GTM ID Help Popup Component
 *
 * Displays a help popup with a screenshot showing where to find
 * the GTM Container ID in Google Tag Manager.
 */

import { Button, Popover } from '@wordpress/components';
import { useState, memo } from '@wordpress/element';
import { help } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import SettingsService from '../../services/SettingsService';

const GtmIdHelpPopup = memo( () => {
	const [ isVisible, setIsVisible ] = useState( false );

	return (
		<div className="gtmkit-inline-block">
			<Button
				icon={ help }
				onClick={ () => setIsVisible( ! isVisible ) }
				aria-label={ __( 'Help finding GTM Container ID', 'gtm-kit' ) }
				className="gtmkit-help-button"
			/>
			{ isVisible && (
				<Popover
					position="middle right"
					onClose={ () => setIsVisible( false ) }
					className="gtmkit-help-popover"
				>
					<div className="gtmkit-p-4 gtmkit-max-w-[90vw] gtmkit-w-[600px] gtmkit-min-w-[600px]">
						<h3 className="gtmkit-font-bold gtmkit-text-base gtmkit-mb-3">
							{ __(
								'Where to find your GTM Container ID',
								'gtm-kit'
							) }
						</h3>
						<p className="gtmkit-mb-4 gtmkit-text-sm gtmkit-text-color-grey">
							{ __(
								'You can find your GTM Container ID in Google Tag Manager at the top of your workspace. It looks like GTM-XXXXXXX.',
								'gtm-kit'
							) }
						</p>
						<img
							src={ `${ SettingsService.getPluginUrl() }/assets/images/gtm-id-location.png` }
							alt={ __(
								'GTM Container ID location in Google Tag Manager',
								'gtm-kit'
							) }
							className="gtmkit-w-full gtmkit-h-auto gtmkit-border gtmkit-rounded"
						/>
						<p className="gtmkit-mt-4 gtmkit-text-sm">
							<a
								href="https://tagmanager.google.com/"
								target="_blank"
								rel="noreferrer"
								className="gtmkit-text-color-primary gtmkit-underline"
							>
								{ __( 'Open Google Tag Manager', 'gtm-kit' ) }
							</a>
						</p>
					</div>
				</Popover>
			) }
		</div>
	);
} );

export default GtmIdHelpPopup;
