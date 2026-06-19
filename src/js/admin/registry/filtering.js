/**
 * Integration filter ("Approach B") logic.
 *
 * The filter narrows a capability to one integration by each field's
 * `integration` tag. Two things keep a field off the page:
 *
 * - its integration's plugin is not active (the settings do not apply on this
 *   install) — the field is dropped everywhere, with no placeholder;
 * - the active filter selects a different (active) integration — the field is
 *   dropped and its integration is summarised by a single placeholder.
 *
 * Universal fields (no integration) always stay, so universal capabilities
 * (Setup, Consent) remain fully reachable under any filter.
 */
import { getAllFields, getSectionItems } from './assemble';
import { getSections } from './capabilities';
import SettingsService from '../services/SettingsService';

/**
 * Whether a field's integration is usable here: it has no integration tag, or
 * the tagged integration's plugin is installed and active.
 *
 * @param {string|null} integration Integration slug, or null.
 * @return {boolean} True when the integration's settings apply.
 */
export const isIntegrationPluginActive = ( integration ) =>
	! integration || SettingsService.isPluginActive( integration );

/**
 * Whether a field passes the active filter. No filter, or a universal field,
 * always passes.
 *
 * @param {Object}      field        A field definition.
 * @param {string|null} activeFilter The active integration slug, or null.
 * @return {boolean} True when the field is shown.
 */
export const fieldMatchesFilter = ( field, activeFilter ) =>
	! activeFilter || ! field.integration || field.integration === activeFilter;

/**
 * The number of fields in a capability tagged with a given integration. Drives
 * the sidebar count badge and the filter notice.
 *
 * @param {string} capabilityId Capability id.
 * @param {string} integration  Integration slug.
 * @return {number} The field count.
 */
export const countCapabilityIntegrationFields = ( capabilityId, integration ) =>
	getAllFields().filter(
		( field ) =>
			field.capability === capabilityId &&
			field.integration === integration
	).length;

/**
 * The sections to render for a capability under the active filter, plus the
 * active integrations whose sections the filter hid (so the page can show a
 * placeholder for each). Sections whose every field belongs to an inactive
 * integration are dropped outright, with no placeholder.
 *
 * @param {string}      capabilityId Capability id.
 * @param {string|null} activeFilter The active integration slug, or null.
 * @return {{visibleSections: Array, hiddenIntegrations: Array<string>}} The view.
 */
export const getCapabilitySectionsView = ( capabilityId, activeFilter ) => {
	const sections = getSections( capabilityId );
	const visibleSections = [];
	const hidden = new Set();

	sections.forEach( ( section ) => {
		const { items } = getSectionItems( capabilityId, section.id );
		const fields = items.filter( ( item ) => ! item.isBlock );
		const activeFields = fields.filter( ( field ) =>
			isIntegrationPluginActive( field.integration )
		);

		// Field-bearing section with no active integrations: drop it entirely.
		if ( fields.length > 0 && activeFields.length === 0 ) {
			return;
		}

		const keep =
			activeFields.length === 0 ||
			activeFields.some( ( field ) =>
				fieldMatchesFilter( field, activeFilter )
			);

		if ( keep ) {
			visibleSections.push( section );
			return;
		}

		activeFields
			.map( ( field ) => field.integration )
			.filter( Boolean )
			.forEach( ( integration ) => hidden.add( integration ) );
	} );

	return { visibleSections, hiddenIntegrations: [ ...hidden ] };
};
