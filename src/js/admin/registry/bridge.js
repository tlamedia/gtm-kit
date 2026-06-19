/**
 * Runtime registration bridge.
 *
 * Add-ons register capabilities/sections/fields by populating
 * `window.gtmkitSettings.settingsRegistry` server-side (via the
 * `gtmkit_settings_registry` PHP filter). The payload is a versioned envelope:
 *
 *   { schemaVersion: 1, fields: [...], sections: [...] }
 *
 * The shell reads it through these helpers, ignoring a payload whose
 * `schemaVersion` it does not understand so an add-on built against a newer
 * contract cannot inject malformed schema into an older shell.
 */

/**
 * The contract version this shell understands. Bump only with a compatibility
 * story; a field schema/condition-grammar change is a breaking change.
 */
export const SCHEMA_VERSION = 1;

let warned = false;

/**
 * The registration envelope from the bridge, or null when absent or built
 * against an incompatible contract version.
 *
 * @return {Object|null} The validated envelope.
 */
const getEnvelope = () => {
	const registry =
		typeof window !== 'undefined'
			? window.gtmkitSettings?.settingsRegistry
			: null;

	if ( ! registry || typeof registry !== 'object' ) {
		return null;
	}

	if ( registry.schemaVersion !== SCHEMA_VERSION ) {
		if ( ! warned && typeof window !== 'undefined' && window.console ) {
			warned = true;
			// eslint-disable-next-line no-console
			window.console.warn(
				`GTM Kit: ignoring a settings registration built for schema version ${ registry.schemaVersion } (this shell understands ${ SCHEMA_VERSION }).`
			);
		}
		return null;
	}

	return registry;
};

/**
 * Fields registered at runtime by add-ons.
 *
 * @return {Array} Registered field schema (empty when none).
 */
export const getRegisteredFields = () => {
	const envelope = getEnvelope();
	return envelope && Array.isArray( envelope.fields ) ? envelope.fields : [];
};

/**
 * Sections registered at runtime by add-ons.
 *
 * @return {Array} Registered section definitions (empty when none).
 */
export const getRegisteredSections = () => {
	const envelope = getEnvelope();
	return envelope && Array.isArray( envelope.sections )
		? envelope.sections
		: [];
};
