/*WordPress*/
import { useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	BaseControl,
	ToggleControl,
} from '@wordpress/components';

import { useNavigate } from 'react-router-dom';

/*Inbuilt Context*/
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { SiteDataContext } from '../../context/SiteDataContext';

const EssentialSettings = () => {
	const {
		useSettings,
		updateStateSettings,
		updateSettings,
		isPending: useIsPending,
	} = useContext( SettingsDataContext );
	const { useInstallData } = useContext( SiteDataContext );
	const navigate = useNavigate();

	return (
		<div className="gtmkit-text-center">
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading">
				{ __( 'Essential Settings', 'gtm-kit' ) }
			</h1>

			<div className="gtmkit-max-w-lg gtmkit-mx-auto">
				<p className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey">
					{ __(
						'There are a lot of settings in GTM Kit and we recommend that you uses our recommended settings but you can also choose to use the default settings and go through the settings at your convenience.',
						'gtm-kit'
					) }
				</p>
			</div>

			<div className="gtmkit-settings-field-wrap gtmkit-max-w-lg gtmkit-mx-auto">
				<BaseControl
					label={ __( 'Page type', 'gtm-kit' ) }
					id={ 'page-type' }
				>
					<ToggleControl
						label={ __(
							'Include the page type i.e. page, product, category, cart, checkout etc in the datalayer?',
							'gtm-kit'
						) }
						checked={
							useSettings &&
							useSettings.general.datalayer_page_type
						}
						onChange={ () => {
							updateStateSettings(
								'general',
								'datalayer_page_type',
								! (
									useSettings &&
									useSettings.general.datalayer_page_type
								)
							);
						} }
					/>
				</BaseControl>
			</div>

			{ useInstallData.woocommerce_integration ? (
				<div className="gtmkit-settings-field-wrap gtmkit-max-w-lg gtmkit-mx-auto">
					<BaseControl
						label={ __( 'Track WooCommerce', 'gtm-kit' ) }
						id={ 'track-wooCommerce' }
					>
						<ToggleControl
							label={ __(
								'Would you like to track e-commerce data from WooCommerce?',
								'gtm-kit'
							) }
							checked={
								useSettings &&
								useSettings.integrations.woocommerce_integration
							}
							onChange={ () => {
								updateStateSettings(
									'integrations',
									'woocommerce_integration',
									! (
										useSettings &&
										useSettings.integrations
											.woocommerce_integration
									)
								);
							} }
						/>
					</BaseControl>
				</div>
			) : null }

			{ useInstallData.cf7_integration ? (
				<div className="gtmkit-settings-field-wrap gtmkit-max-w-lg gtmkit-mx-auto">
					<BaseControl
						label={ __( 'Track Contact Form 7', 'gtm-kit' ) }
						id="track-cf7"
					>
						<ToggleControl
							label={ __(
								'Would you like to track form submissions from Contact Form 7?',
								'gtm-kit'
							) }
							checked={
								useSettings &&
								useSettings.integrations.cf7_integration
							}
							onChange={ () => {
								updateStateSettings(
									'integrations',
									'cf7_integration',
									! (
										useSettings &&
										useSettings.integrations.cf7_integration
									)
								);
							} }
						/>
					</BaseControl>
				</div>
			) : null }

			{ useInstallData.edd_integration ? (
				<div className="gtmkit-settings-field-wrap">
					<BaseControl
						label={ __(
							'Track Easy Digital Downloads',
							'gtm-kit'
						) }
						id="track-edd"
					>
						<ToggleControl
							label={ __(
								'Would you like to track e-commerce data from Easy Digital Downloads?',
								'gtm-kit'
							) }
							checked={
								useSettings &&
								useSettings.integrations.edd_integration
							}
							onChange={ () => {
								updateStateSettings(
									'integrations',
									'edd_integration',
									! (
										useSettings &&
										useSettings.integrations.edd_integration
									)
								);
							} }
						/>
					</BaseControl>
				</div>
			) : null }

			<div className="gtmkit-flex gtmkit-mt-12">
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						updateSettings();
						navigate( '/share-anonymous-data', { replace: true } );
					} }
					disabled={ useIsPending }
				>
					{ __( 'Save and continue', 'gtm-kit' ) }
					{ useIsPending ? <Spinner /> : '' }
				</Button>
			</div>
		</div>
	);
};

export default EssentialSettings;
