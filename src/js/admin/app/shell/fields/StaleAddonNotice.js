/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';

/**
 * The add-on that provides each tier's fields, by name, so the notice can tell
 * the user exactly which plugin to update.
 */
const ADDON_NAMES = {
	woo: __( 'GTM Kit Woo Add-On', 'gtm-kit' ),
	premium: __( 'GTM Kit Premium', 'gtm-kit' ),
};

/**
 * Notice shown beneath a stale stub: the user is licensed for the field but the
 * add-on that should supply it is too old to register against the settings
 * contract, so the control is held locked and the user is told to update.
 *
 * @param {Object} props      Component props.
 * @param {string} props.tier The field tier whose add-on is outdated.
 * @return {JSX.Element|null} The notice, or null.
 */
const StaleAddonNotice = ( { tier } ) => {
	const name = ADDON_NAMES[ tier ];

	if ( ! name ) {
		return null;
	}

	return (
		<p className="gtmkit-m-0 gtmkit-mt-1 gtmkit-text-xs gtmkit-text-[#9a6700]">
			{ sprintf(
				/* translators: %s: the add-on plugin name to update. */
				__(
					'Update %s to its latest version to manage this setting here.',
					'gtm-kit'
				),
				name
			) }
		</p>
	);
};

export default StaleAddonNotice;
