/*WordPress*/
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { getAdminLink } from '../utils/get-admin-link';

const GettingStarted = () => {
	return (
		<>
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading gtmkit-text-center">
				{ __( 'You are ready to use GTM Kit!', 'gtm-kit' ) }
			</h1>
			<p className="gtmkit-text-base gtmkit-mb-4 gtmkit-text-color-grey gtmkit-text-center">
				{ __(
					'Your Google Tag Manager Container is now sending data to Google Tag Manager.',
					'gtm-kit'
				) }
			</p>
			<p className="gtmkit-text-base gtmkit-mb-12 gtmkit-text-color-grey gtmkit-text-center">
				{ __(
					'Below you will find GTM container import files, with all the necessary tags, trigger, and variables to use Google Analytics 4.',
					'gtm-kit'
				) }
				&nbsp;
			</p>

			<div className="gtmkit-border-2 gtmkit-max-w-lg gtmkit-mx-auto gtmkit-mb-8">
				<h3 className="gtmkit-p-3 gtmkit-font-bold gtmkit-text-xl gtmkit-border-b-2">
					{ __( 'Getting Started', 'gtm-kit' ) }
				</h3>
				<p className="gtmkit-text-base gtmkit-text-color-grey gtmkit-m-4">
					{ __(
						'The next step is to configure your Google Tag Manager container. You will find templates for this in the GTM Templates section.',
						'gtm-kit'
					) }
					&nbsp;
					<a
						className="gtmkit-text-color-primary gtmkit-whitespace-nowrap"
						href={ getAdminLink( 'templates' ) }
						rel="noreferrer"
					>
						{ __( 'Go to GTM Templates', 'gtm-kit' ) }
					</a>
				</p>
				<p className="gtmkit-text-base gtmkit-text-color-grey gtmkit-m-4">
					{ __(
						'To get the most out of Google Tag Manager with GTM Kit, you should also review the tutorials.',
						'gtm-kit'
					) }
					&nbsp;
					<a
						className="gtmkit-text-color-primary gtmkit-whitespace-nowrap"
						href={ getAdminLink( 'help', 'help' ) }
						rel="noreferrer"
					>
						{ __( 'Go to Tutorials', 'gtm-kit' ) }
					</a>
				</p>
			</div>

			<div className="gtmkit-flex gtmkit-mt-12">
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						window.location.href = getAdminLink( 'general' );
					} }
				>
					{ __( 'Go to the dashboard', 'gtm-kit' ) }
				</Button>
			</div>
		</>
	);
};

export default GettingStarted;
