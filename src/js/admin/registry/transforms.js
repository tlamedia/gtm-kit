/**
 * Named value transforms referenced by field schema.
 *
 * A field references a transform by id (a serialisable string) rather than an
 * inline function, so a registered add-on schema can reuse a core transform
 * without shipping executable code through the registration bridge.
 */
import { normalizeGtmId } from '../utils/gtm-validation';

const TRANSFORMS = {
	normalizeGtmId,
};

/**
 * Resolve a transform function by id.
 *
 * @param {string} [id] The transform id from a field's `transform` property.
 * @return {Function|undefined} The transform, or undefined when unmapped.
 */
export const getTransform = ( id ) => ( id ? TRANSFORMS[ id ] : undefined );

export default TRANSFORMS;
