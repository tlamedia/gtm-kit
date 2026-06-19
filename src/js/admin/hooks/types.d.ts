/**
 * TypeScript definitions for custom hooks
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import type { Tier } from '../constants/tiers';

/**
 * useSettingField hook return type
 */
export type UseSettingFieldReturn = [
	value: unknown,
	setValue: (newValue: unknown) => void
];

/**
 * useFeatureFlags hook return type
 */
export interface UseFeatureFlagsReturn {
	isPremium: boolean;
	hasValidLicense: boolean;
	requiresPremium: () => boolean;
	activeTier: Tier;
	meetsRequiredTier: (requiredTier?: Tier) => boolean;
	isPluginActive: (pluginSlug: string) => boolean;
	hasSGTM: () => boolean;
	canUseFeature: (feature: string) => boolean;
}

/**
 * useNotification hook return type
 */
export interface UseNotificationReturn {
	notifications: {
		metrics?: {
			total: number;
			problem?: number;
		};
		[key: string]: unknown;
	};
	dismissNotification: (id: string) => Promise<void>;
	removeNotification: (id: string) => Promise<void>;
	isUpdatingNotifications: boolean;
	totalNotifications: number;
	problemNotifications: number;
	hasProblems: boolean;
	hasNotifications: boolean;
}

/**
 * Hook function signatures
 */
export function useSettingField(group: string, key: string): UseSettingFieldReturn;
export function useFeatureFlags(): UseFeatureFlagsReturn;
export function useNotification(): UseNotificationReturn;
