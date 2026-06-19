/**
 * TypeScript definitions for access-tier constants and helpers.
 */

export declare const TIERS: {
	readonly FREE: 'free';
	readonly WOO: 'woo';
	readonly PREMIUM: 'premium';
};

export type Tier = typeof TIERS[keyof typeof TIERS];

export declare const tierRank: ( tier: Tier ) => number;

export declare const meetsRequiredTier: (
	activeTier: Tier,
	requiredTier?: Tier
) => boolean;
