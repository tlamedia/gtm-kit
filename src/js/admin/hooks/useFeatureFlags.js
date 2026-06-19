/**
 * Custom hook for feature flag checks
 *
 * Centralizes premium/license/plugin checks, making it easier to manage
 * feature access across components.
 *
 * @return {Object} Feature flags and helper methods
 *
 * @example
 * const { isPremium, requiresPremium, isPluginActive } = useFeatureFlags();
 *
 * if (!requiresPremium()) {
 *   return <UpgradePrompt />;
 * }
 *
 * if (!isPluginActive('woocommerce')) {
 *   return <PluginInactive pluginName="WooCommerce" />;
 * }
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { useContext, useCallback } from '@wordpress/element';
import { LicenseContext } from '../context/LicenseContext';
import { SettingsDataContext } from '../context/SettingsDataContext';
import SettingsService from '../services/SettingsService';
import { meetsRequiredTier as compareTiers } from '../constants/tiers';

export const useFeatureFlags = () => {
	const { isPremium, hasValidLicense, activeTier } =
		useContext( LicenseContext );
	const { settings } = useContext( SettingsDataContext );

	/**
	 * Whether the active tier satisfies a required tier.
	 *
	 * The single gate decision used across the app. A missing required tier
	 * defaults to free, so an un-tagged control is never gated.
	 *
	 * @param {string} requiredTier - The minimum tier the feature needs.
	 * @return {boolean} True when the active tier meets or exceeds it.
	 */
	const meetsRequiredTier = useCallback(
		( requiredTier ) => compareTiers( activeTier, requiredTier ),
		[ activeTier ]
	);

	/**
	 * Check if premium features are available
	 *
	 * Gates on `isPremium` (the premium plugin is loaded) only.
	 * `hasValidLicense` is only localized on the Upgrades page, so
	 * requiring it here would lock premium UI everywhere else.
	 *
	 * @return {boolean} True if premium features available
	 */
	const requiresPremium = useCallback( () => {
		return isPremium;
	}, [ isPremium ] );

	/**
	 * Check if a specific plugin is active
	 *
	 * @param {string} pluginSlug - Plugin slug (e.g., 'woocommerce', 'edd')
	 * @return {boolean} True if plugin is active
	 */
	const isPluginActive = useCallback( ( pluginSlug ) => {
		return SettingsService.isPluginActive( pluginSlug );
	}, [] );

	/**
	 * Check if server-side GTM is configured
	 *
	 * @return {boolean} True if sGTM is configured
	 */
	const hasSGTM = useCallback( () => {
		return Boolean(
			settings?.general?.sgtm_domain &&
				settings.general.sgtm_domain.trim()
		);
	}, [ settings?.general?.sgtm_domain ] );

	/**
	 * Check if a specific feature is available
	 *
	 * @param {string} feature - Feature name
	 * @return {boolean} True if feature is available
	 */
	const canUseFeature = useCallback(
		( feature ) => {
			const featureFlags = {
				// Premium features
				webhooks: requiresPremium() && hasSGTM(),
				advancedTracking: requiresPremium(),
				premiumIntegrations: requiresPremium(),

				// Integration features
				woocommerce: isPluginActive( 'woocommerce' ),
				edd: isPluginActive( 'easy-digital-downloads' ),
				cf7: isPluginActive( 'contact-form-7' ),

				// Advanced features
				sgtm: hasSGTM(),
				cookieKeeper: hasSGTM() && requiresPremium(),
			};

			return featureFlags[ feature ] ?? false;
		},
		[ requiresPremium, isPluginActive, hasSGTM ]
	);

	return {
		// License status
		isPremium,
		hasValidLicense,
		requiresPremium,

		// Access tier
		activeTier,
		meetsRequiredTier,

		// Plugin status
		isPluginActive,

		// Configuration status
		hasSGTM,

		// Feature checks
		canUseFeature,
	};
};

export default useFeatureFlags;
