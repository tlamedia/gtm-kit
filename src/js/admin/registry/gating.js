/**
 * Field gating helpers.
 *
 * Pure predicates over a field's tier and stub status, kept here so they are
 * unit-testable independently of the React row that consumes them.
 */

/**
 * Whether a field is locked because the active license does not meet its tier.
 *
 * @param {Object}   field             A field definition.
 * @param {Function} meetsRequiredTier Predicate: does the active tier meet a tier.
 * @return {boolean} True when the field should render locked for upsell.
 */
export const isTierLocked = ( field, meetsRequiredTier ) =>
	! meetsRequiredTier( field?.tier );

/**
 * Whether a field is a stale stub: a core upsell stub the user's license tier
 * already qualifies for, yet no add-on registered the real field over it. That
 * means the providing add-on is active but too old to register against the
 * settings contract, so the stub must not masquerade as a working control.
 *
 * @param {Object}   field             A field definition.
 * @param {Function} meetsRequiredTier Predicate: does the active tier meet a tier.
 * @return {boolean} True when the stub is backed by an outdated add-on.
 */
export const isStaleStub = ( field, meetsRequiredTier ) =>
	field?.stub === true && meetsRequiredTier( field?.tier );
