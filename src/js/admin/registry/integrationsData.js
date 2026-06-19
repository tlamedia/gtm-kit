/**
 * Integrations hub data.
 *
 * The hub lists the integrations the server exposes through the settings bridge
 * (`window.gtmkitSettings.integrations`), each resolved to a status and to the
 * sections that hold its settings. The per-integration config page renders
 * those sections, narrowed to the one integration.
 */
import { __ } from '@wordpress/i18n';
import SettingsService from '../services/SettingsService';
import { getAllFields, getSectionItems } from './assemble';
import { getCapability, getSections } from './capabilities';

export const STATUS = {
	ACTIVE: 'active',
	OFF: 'off',
	NOT_INSTALLED: 'not-installed',
};

/**
 * Context-pane content shared by the Integrations hub and its config pages,
 * explaining what is configured here versus on the capability pages.
 */
export const INTEGRATIONS_CONTEXT = {
	about: {
		title: __( 'What you configure here', 'gtm-kit' ),
		text: [
			__(
				'This page is for enabling integrations and their basic plugin settings.',
				'gtm-kit'
			),
			__(
				'Tracking itself, which events fire and what goes into the data layer, is configured on the capability pages (Commerce, Events & data layer, Consent & privacy).',
				'gtm-kit'
			),
		],
	},
};

const isOn = ( value ) => value === true || value === 1 || value === '1';

/**
 * The status of an integration: its plugin is not active (Not installed), or it
 * is active with the integration toggle on (Active) or off (Off).
 *
 * @param {string} slug     Integration slug.
 * @param {string} option   Integration toggle option key (under `integrations`).
 * @param {Object} settings The live settings store (defaults to the bridge).
 * @return {string} One of STATUS.
 */
export const getIntegrationStatus = (
	slug,
	option,
	settings = SettingsService.getSettings()
) => {
	if ( ! SettingsService.isPluginActive( slug ) ) {
		return STATUS.NOT_INSTALLED;
	}
	return isOn( settings?.integrations?.[ option ] )
		? STATUS.ACTIVE
		: STATUS.OFF;
};

/**
 * The integrations to list in the hub, in bridge order, each with its resolved
 * status and a premium flag. Pass the live settings store so the status
 * reflects just-saved toggles instead of the initial bridge snapshot.
 *
 * @param {Object} settings The live settings store (defaults to the bridge).
 * @return {Array<Object>} Integration descriptors.
 */
export const getIntegrations = ( settings = SettingsService.getSettings() ) =>
	Object.entries( SettingsService.getIntegrations() ).map(
		( [ slug, meta ] ) => ( {
			slug,
			title: meta.title || slug,
			description: meta.description || '',
			option: meta.option || '',
			// Anything that is not a free core integration carries a Premium
			// badge in the hub (e.g. the Gravity Forms add-on).
			isPremium: !! meta.type && meta.type !== 'core',
			status: getIntegrationStatus( slug, meta.option, settings ),
		} )
	);

/**
 * A single integration descriptor, or null when the slug is unknown.
 *
 * @param {string} slug Integration slug.
 * @return {Object|null} The descriptor.
 */
export const getIntegration = ( slug ) =>
	getIntegrations().find( ( integration ) => integration.slug === slug ) ||
	null;

/**
 * The capability whose page the integration's settings live on (used for the
 * "settings live under X" note), inferred from the integration's fields.
 *
 * @param {string} slug Integration slug.
 * @return {Object|null} The capability definition, or null.
 */
export const getIntegrationPrimaryCapability = ( slug ) => {
	const field = getAllFields().find(
		( candidate ) => candidate.integration === slug
	);
	return field ? getCapability( field.capability ) || null : null;
};

/**
 * The sections shown on an integration's config page: sections flagged
 * `integrationConfig` and dedicated to the integration (every field belongs to
 * it). This narrows the config page to the integration's own knobs (enable,
 * advanced, custom selectors); the rest of its settings stay on the capability
 * pages. A section merely containing one of the integration's fields among
 * universal ones (e.g. the GA4 events section) is never dedicated.
 *
 * @param {string} slug Integration slug.
 * @return {Array<Object>} Section definitions.
 */
export const getIntegrationSections = ( slug ) => {
	const capabilities = [
		...new Set(
			getAllFields()
				.filter( ( field ) => field.integration === slug )
				.map( ( field ) => field.capability )
		),
	];

	const sections = [];
	capabilities.forEach( ( capabilityId ) => {
		getSections( capabilityId ).forEach( ( section ) => {
			if ( ! section.integrationConfig ) {
				return;
			}

			const fields = getSectionItems(
				capabilityId,
				section.id
			).items.filter( ( item ) => ! item.isBlock );

			const dedicated =
				fields.length > 0 &&
				fields.every( ( field ) => field.integration === slug );

			if ( dedicated ) {
				sections.push( section );
			}
		} );
	} );

	return sections;
};
