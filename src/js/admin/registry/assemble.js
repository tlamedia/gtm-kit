/**
 * Registry assembly.
 *
 * Combines the bundled free field schema, the core upsell stub manifest, and
 * any fields registered at runtime by add-ons through the settings data bridge
 * (`window.gtmkitSettings`), then composes a section's renderable items (fields
 * + inline content blocks) and its aside blocks. A registered real field
 * supersedes a stub of the same `key` (real-over-stub resolution), so adding an
 * add-on field is a data change, not a shell change.
 */
import SETUP_FIELDS from './fields/setup';
import EVENTS_FIELDS from './fields/events';
import CONSENT_FIELDS from './fields/consent';
import TOOLS_FIELDS from './fields/tools';
import COMMERCE_FIELDS from './fields/commerce';
import STUB_FIELDS from './fields/stubs';
import { getRegisteredFields } from './bridge';
import { SETUP_CONTENT } from './content/setup';
import { EVENTS_CONTENT } from './content/events';
import { CONSENT_CONTENT } from './content/consent';
import { TOOLS_CONTENT } from './content/tools';
import { COMMERCE_CONTENT } from './content/commerce';

const FREE_FIELDS = [
	...SETUP_FIELDS,
	...EVENTS_FIELDS,
	...CONSENT_FIELDS,
	...TOOLS_FIELDS,
	...COMMERCE_FIELDS,
];

const CONTENT = {
	...SETUP_CONTENT,
	...EVENTS_CONTENT,
	...CONSENT_CONTENT,
	...TOOLS_CONTENT,
	...COMMERCE_CONTENT,
};

/**
 * All fields, deduplicated by `key` with last-wins precedence. Stubs come after
 * the free schema and registered real fields come last, so a registered field
 * supersedes its stub (real-over-stub resolution).
 *
 * @return {Array} The merged field list.
 */
export const getAllFields = () => {
	const byKey = new Map();
	[ ...FREE_FIELDS, ...STUB_FIELDS, ...getRegisteredFields() ].forEach(
		( field ) => {
			byKey.set( field.key, field );
		}
	);
	return [ ...byKey.values() ];
};

/**
 * Content (inline + aside blocks) for a section.
 *
 * @param {string} capabilityId Capability id.
 * @param {string} sectionId    Section id.
 * @return {{inline?: Array, aside?: Array}} The section content.
 */
export const getSectionContent = ( capabilityId, sectionId ) =>
	CONTENT[ `${ capabilityId }/${ sectionId }` ] || {};

/**
 * Renderable items for a section: fields and inline content blocks merged and
 * ordered, plus the section's aside blocks.
 *
 * Each item is tagged with `isBlock` so the renderer knows whether to resolve
 * it through the control-type map or the block-type map.
 *
 * @param {string} capabilityId Capability id.
 * @param {string} sectionId    Section id.
 * @return {{items: Array, aside: Array}} The composed section.
 */
export const getSectionItems = ( capabilityId, sectionId ) => {
	const fields = getAllFields()
		.filter(
			( field ) =>
				field.capability === capabilityId && field.section === sectionId
		)
		.map( ( field ) => ( { ...field, isBlock: false } ) );

	const content = getSectionContent( capabilityId, sectionId );

	const inline = ( content.inline || [] ).map( ( block ) => ( {
		...block,
		isBlock: true,
	} ) );

	const items = [ ...fields, ...inline ].sort(
		( a, b ) => ( a.order ?? 0 ) - ( b.order ?? 0 )
	);

	return { items, aside: content.aside || [] };
};
