/**
 * Declarative condition evaluation for field/block visibility and enablement.
 *
 * Conditions are pure data so they round-trip through the registration bridge
 * and cannot smuggle logic into the registry. A condition is an object whose
 * entries are ANDed together. Each entry is one of:
 *
 * - a dotted option key mapped to an expected value (equality):
 *     `{ 'integrations.woocommerce_integration': true }`
 * - the `truthy` operator mapped to a list of option keys that must all be
 *   truthy (non-empty for strings):
 *     `{ truthy: [ 'general.sgtm_domain', 'general.sgtm_container_identifier' ] }`
 * - the `falsy` operator mapped to a list of option keys that must all be
 *   falsy (the mirror of `truthy`):
 *     `{ falsy: [ 'general.gcm_default_settings' ] }`
 * - the `in` operator mapped to `{ optionKey: [ allowed, values ] }`:
 *     `{ in: { 'general.script_implementation': [ 0, 1 ] } }`
 *
 * Keep this operator set minimal; widen it only when the inventory forces it.
 */

const OPERATORS = [ 'truthy', 'falsy', 'in' ];

/**
 * Resolve a dotted option key against the settings store.
 *
 * @param {Object} settings The settings store ({ general: {...}, ... }).
 * @param {string} path     A dotted key such as `general.sgtm_domain`.
 * @return {*} The resolved value, or undefined.
 */
const resolve = ( settings, path ) =>
	path.split( '.' ).reduce( ( acc, segment ) => acc?.[ segment ], settings );

/**
 * Truthiness with string-trim semantics, matching how the bespoke pages
 * treated "configured" text inputs.
 *
 * @param {*} value A resolved option value.
 * @return {boolean} True when the value counts as set.
 */
const isTruthy = ( value ) =>
	typeof value === 'string' ? value.trim() !== '' : Boolean( value );

/**
 * Evaluate a declarative condition against the settings store.
 *
 * @param {Object|null|undefined} condition The condition object, or nullish.
 * @param {Object}                settings  The settings store.
 * @return {boolean} True when the condition holds (or is absent).
 */
export const evaluateCondition = ( condition, settings ) => {
	if ( ! condition ) {
		return true;
	}

	return Object.entries( condition ).every( ( [ key, expected ] ) => {
		if ( key === 'truthy' ) {
			const keys = Array.isArray( expected ) ? expected : [ expected ];
			return keys.every( ( optionKey ) =>
				isTruthy( resolve( settings, optionKey ) )
			);
		}

		if ( key === 'falsy' ) {
			const keys = Array.isArray( expected ) ? expected : [ expected ];
			return keys.every(
				( optionKey ) => ! isTruthy( resolve( settings, optionKey ) )
			);
		}

		if ( key === 'in' ) {
			return Object.entries( expected ).every(
				( [ optionKey, allowed ] ) =>
					allowed.includes( resolve( settings, optionKey ) )
			);
		}

		// Plain dotted key → equality.
		return resolve( settings, key ) === expected;
	} );
};

/**
 * Whether a field/block is visible given its `visibleWhen` and the store.
 *
 * @param {Object} entry    A field or block with an optional `visibleWhen`.
 * @param {Object} settings The settings store.
 * @return {boolean} True when visible.
 */
export const isVisible = ( entry, settings ) =>
	evaluateCondition( entry?.visibleWhen, settings );

/**
 * Whether a field is enabled given its `enabledWhen` and the store. A field
 * with no `enabledWhen` is always enabled.
 *
 * @param {Object} entry    A field with an optional `enabledWhen`.
 * @param {Object} settings The settings store.
 * @return {boolean} True when enabled.
 */
export const isEnabled = ( entry, settings ) =>
	evaluateCondition( entry?.enabledWhen, settings );

export { OPERATORS };
