import { __ } from '@wordpress/i18n';

import { getAdminLink } from '../utils/get-admin-link';

const SettingsFooter = () => {
	return (
		<>
			<footer className="gtm-kit-settings-footer gtmkit-my-8 gtmkit-text-color-grey">
				<p className="gtmkit-mx-auto gtmkit-max-w-max">
					<a className="gtmkit-underline" href={ getAdminLink() }>
						{ __( 'Go to the dashboard.', 'gtm-kit' ) }
					</a>
				</p>
			</footer>
		</>
	);
};

export default SettingsFooter;
