/**
 * Access-tier constants and comparison helpers.
 *
 * The admin app gates features on a linear access tier: free < woo < premium.
 * Premium is a functional superset of Woo, so a higher tier unlocks everything
 * a lower tier unlocks. A feature declares the minimum tier it needs; the gate
 * compares it against the resolved active tier.
 */

export const TIERS = {
	FREE: 'free',
	WOO: 'woo',
	PREMIUM: 'premium',
};

// Linear ordering. A larger rank unlocks everything a smaller rank unlocks.
const TIER_RANK = {
	[ TIERS.FREE ]: 0,
	[ TIERS.WOO ]: 1,
	[ TIERS.PREMIUM ]: 2,
};

/**
 * Rank of a tier. Unknown values fall back to the free rank so an unrecognised
 * tier never accidentally unlocks gated content.
 *
 * @param {string} tier - A tier value.
 * @return {number} The tier's rank.
 */
export const tierRank = ( tier ) =>
	TIER_RANK[ tier ] ?? TIER_RANK[ TIERS.FREE ];

/**
 * Whether the active tier satisfies a required tier.
 *
 * A missing required tier defaults to free, so an un-tagged control is never
 * gated.
 *
 * @param {string} activeTier   - The resolved active tier.
 * @param {string} requiredTier - The minimum tier the feature needs.
 * @return {boolean} True when the active tier meets or exceeds the requirement.
 */
export const meetsRequiredTier = ( activeTier, requiredTier = TIERS.FREE ) =>
	tierRank( activeTier ) >= tierRank( requiredTier );
