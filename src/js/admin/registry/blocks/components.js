/**
 * Escape-hatch component registry for the `component` block type.
 *
 * Genuinely non-form UI that cannot be expressed as fields or simple content
 * blocks is rendered here by id. This is the block-level escape hatch, the
 * counterpart to control-type and SlotFill escape hatches.
 */
import { memo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

import ShareAnonymousData from '../../app/organisms/share-anonymous-data';
import ImportPluginSettings from '../../app/organisms/import-plugin-settings';
import PluginInactive from '../../app/molecules/plugin-inactive';
import SettingsService from '../../services/SettingsService';
import { useSettingField } from '../../hooks';

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

/**
 * State what kind of site WordPress reports this install to be, and what
 * GTM Kit therefore does with the container here.
 *
 * The reported kind comes from the server, resolved by the same code the
 * frontend output gate calls. The consequence is recomposed from the live
 * value of the override so the sentence keeps up with the toggle before the
 * page is saved.
 *
 * @return {JSX.Element} The readout.
 */
const SiteKindStatus = memo( () => {
	const { type, isProduction } = SettingsService.getSiteEnvironment();
	const [ loadAnyway ] = useSettingField(
		'general',
		'load_on_non_production'
	);

	const reported = sprintf(
		// translators: %s is the kind of site WordPress reports, for example "staging", "development" or "local".
		__( 'WordPress reports this site as %s.', 'gtm-kit' ),
		type
	);

	if ( isProduction ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ reported }{ ' ' }
				{ __(
					'GTM Kit loads your Google Tag Manager container here.',
					'gtm-kit'
				) }
			</Notice>
		);
	}

	if ( loadAnyway ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ reported }{ ' ' }
				{ __(
					'GTM Kit loads your Google Tag Manager container here anyway, because the setting below is switched on, so traffic from this site is measured alongside your real traffic.',
					'gtm-kit'
				) }
			</Notice>
		);
	}

	return (
		<Notice status="info" isDismissible={ false }>
			{ reported }{ ' ' }
			{ __(
				'GTM Kit does not load your Google Tag Manager container here, so traffic from this site stays out of your analytics and advertising audiences. The data layer is still built, so you can see what would be sent from a live site.',
				'gtm-kit'
			) }
		</Notice>
	);
} );

const COMPONENTS = {
	'share-anonymous-data': ShareAnonymousData,
	'import-plugin-settings': ImportPluginSettings,
	'consent-badges': ConsentBadges,
	'plugin-inactive': PluginInactiveNotice,
	'site-kind-status': SiteKindStatus,
};

/**
 * Resolve an escape-hatch component by id.
 *
 * @param {string} id The block's `component` id.
 * @return {Function|undefined} The component.
 */
export const getComponent = ( id ) => COMPONENTS[ id ];

export default COMPONENTS;
