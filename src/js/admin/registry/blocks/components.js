/**
 * Escape-hatch component registry for the `component` block type.
 *
 * Genuinely non-form UI that cannot be expressed as fields or simple content
 * blocks is rendered here by id. This is the block-level escape hatch, the
 * counterpart to control-type and SlotFill escape hatches.
 */
import { memo } from '@wordpress/element';
import { Notice } from '@wordpress/components';

import ShareAnonymousData from '../../app/organisms/share-anonymous-data';
import PluginInactive from '../../app/molecules/plugin-inactive';
import SettingsService from '../../services/SettingsService';

/**
 * Server-provided consent admin badges, rendered as dismissable-free notices
 * above the consent sections.
 *
 * @return {JSX.Element|null} The badges, or null when none.
 */
const ConsentBadges = memo( () => {
	const badges = SettingsService.getConsentAdminBadges();
	if ( ! badges.length ) {
		return null;
	}
	return (
		<div className="gtmkit-mb-6 gtmkit-space-y-2">
			{ badges.map( ( badge ) => (
				<Notice
					key={ badge.id }
					status={ badge.severity || 'info' }
					isDismissible={ false }
				>
					{ badge.message }
				</Notice>
			) ) }
		</div>
	);
} );

/**
 * Plugin-inactive notice. Reads its plugin name and slug from block props and
 * renders only when the plugin is inactive.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.pluginName Display name.
 * @param {Object} props.pluginSlug Plugin slug to check.
 * @return {JSX.Element|null} The notice, or null when the plugin is active.
 */
const PluginInactiveNotice = ( { pluginName, pluginSlug } ) => {
	if ( SettingsService.isPluginActive( pluginSlug ) ) {
		return null;
	}
	return <PluginInactive pluginName={ pluginName } />;
};

const COMPONENTS = {
	'share-anonymous-data': ShareAnonymousData,
	'consent-badges': ConsentBadges,
	'plugin-inactive': PluginInactiveNotice,
};

/**
 * Resolve an escape-hatch component by id.
 *
 * @param {string} id The block's `component` id.
 * @return {Function|undefined} The component.
 */
export const getComponent = ( id ) => COMPONENTS[ id ];

export default COMPONENTS;
