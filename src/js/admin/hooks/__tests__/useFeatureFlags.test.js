/**
 * useFeatureFlags Hook Unit Tests
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { renderHook } from '@testing-library/react';
import { useFeatureFlags } from '../useFeatureFlags';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { LicenseContext } from '../../context/LicenseContext';
import SettingsService from '../../services/SettingsService';
import { TIERS } from '../../constants/tiers';

// Helper to create wrapper with contexts
const createWrapper = ( settingsOverrides = {}, licenseOverrides = {} ) => {
	const settingsValue = {
		settings: {
			general: {},
			integrations: {},
			premium: {},
		},
		...settingsOverrides,
	};

	const licenseValue = {
		isPremium: false,
		hasValidLicense: false,
		...licenseOverrides,
	};

	return ( { children } ) => (
		<SettingsDataContext.Provider value={ settingsValue }>
			<LicenseContext.Provider value={ licenseValue }>
				{ children }
			</LicenseContext.Provider>
		</SettingsDataContext.Provider>
	);
};

describe( 'useFeatureFlags', () => {
	describe( 'isPremium and hasValidLicense', () => {
		it( 'should return false when not premium', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.isPremium ).toBe( false );
			expect( result.current.hasValidLicense ).toBe( false );
		} );

		it( 'should return true when premium and licensed', () => {
			const wrapper = createWrapper(
				{},
				{
					isPremium: true,
					hasValidLicense: true,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.isPremium ).toBe( true );
			expect( result.current.hasValidLicense ).toBe( true );
		} );
	} );

	describe( 'activeTier and meetsRequiredTier', () => {
		it( 'exposes the active tier from the license context', () => {
			const wrapper = createWrapper( {}, { activeTier: TIERS.WOO } );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.activeTier ).toBe( TIERS.WOO );
		} );

		it( 'gates a Woo user out of a Premium-only section', () => {
			const wrapper = createWrapper( {}, { activeTier: TIERS.WOO } );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.meetsRequiredTier( TIERS.PREMIUM ) ).toBe(
				false
			);
			expect( result.current.meetsRequiredTier( TIERS.WOO ) ).toBe(
				true
			);
		} );

		it( 'gates a free user out of any paid section', () => {
			const wrapper = createWrapper( {}, { activeTier: TIERS.FREE } );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.meetsRequiredTier( TIERS.WOO ) ).toBe(
				false
			);
			expect( result.current.meetsRequiredTier( TIERS.PREMIUM ) ).toBe(
				false
			);
		} );

		it( 'unlocks everything for a Premium user', () => {
			const wrapper = createWrapper( {}, { activeTier: TIERS.PREMIUM } );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.meetsRequiredTier( TIERS.WOO ) ).toBe(
				true
			);
			expect( result.current.meetsRequiredTier( TIERS.PREMIUM ) ).toBe(
				true
			);
		} );
	} );

	describe( 'requiresPremium', () => {
		it( 'should return false when not premium', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.requiresPremium() ).toBe( false );
		} );

		it( 'should return true when premium plugin is active, regardless of license state', () => {
			// hasValidLicense is only localized on the Upgrades page, so the
			// premium UI on every other page must not depend on it.
			const wrapper = createWrapper(
				{},
				{
					isPremium: true,
					hasValidLicense: false,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.requiresPremium() ).toBe( true );
		} );

		it( 'should return true when premium and has valid license', () => {
			const wrapper = createWrapper(
				{},
				{
					isPremium: true,
					hasValidLicense: true,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.requiresPremium() ).toBe( true );
		} );
	} );

	describe( 'isPluginActive', () => {
		beforeEach( () => {
			// Mock SettingsService.isPluginActive
			window.gtmkitSettings = {
				...window.gtmkitSettings,
				plugins: {
					woocommerce: true,
					edd: false,
				},
			};
			// Reinitialize SettingsService with new data
			SettingsService.data = window.gtmkitSettings;
		} );

		it( 'should return true for active plugin', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.isPluginActive( 'woocommerce' ) ).toBe(
				true
			);
		} );

		it( 'should return false for inactive plugin', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.isPluginActive( 'edd' ) ).toBe( false );
		} );

		it( 'should return false for unknown plugin', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.isPluginActive( 'unknown' ) ).toBe( false );
		} );
	} );

	describe( 'hasSGTM', () => {
		it( 'should return false when no sGTM domain', () => {
			const wrapper = createWrapper( {
				settings: {
					general: {},
				},
			} );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.hasSGTM() ).toBe( false );
		} );

		it( 'should return false when sGTM domain is empty', () => {
			const wrapper = createWrapper( {
				settings: {
					general: {
						sgtm_domain: '',
					},
				},
			} );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.hasSGTM() ).toBe( false );
		} );

		it( 'should return true when sGTM domain is set', () => {
			const wrapper = createWrapper( {
				settings: {
					general: {
						sgtm_domain: 'gtm.example.com',
					},
				},
			} );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.hasSGTM() ).toBe( true );
		} );
	} );

	describe( 'canUseFeature', () => {
		it( 'should return false for unknown feature', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'unknownFeature' ) ).toBe(
				false
			);
		} );

		it( 'should return false for premium feature when not premium', () => {
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'advancedTracking' ) ).toBe(
				false
			);
		} );

		it( 'should return true for premium feature when premium plugin is active', () => {
			const wrapper = createWrapper(
				{},
				{
					isPremium: true,
					hasValidLicense: false,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'advancedTracking' ) ).toBe(
				true
			);
		} );

		it( 'should return false for webhooks without sGTM', () => {
			const wrapper = createWrapper(
				{
					settings: {
						general: {},
					},
				},
				{
					isPremium: true,
					hasValidLicense: true,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'webhooks' ) ).toBe( false );
		} );

		it( 'should return true for webhooks with sGTM and premium', () => {
			const wrapper = createWrapper(
				{
					settings: {
						general: {
							sgtm_domain: 'gtm.example.com',
						},
					},
				},
				{
					isPremium: true,
					hasValidLicense: true,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'webhooks' ) ).toBe( true );
		} );

		it( 'should return false for woocommerce when plugin not active', () => {
			window.gtmkitSettings.plugins = { woocommerce: false };
			SettingsService.data = window.gtmkitSettings;
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'woocommerce' ) ).toBe(
				false
			);
		} );

		it( 'should return true for woocommerce when plugin active', () => {
			window.gtmkitSettings.plugins = { woocommerce: true };
			SettingsService.data = window.gtmkitSettings;
			const wrapper = createWrapper();
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'woocommerce' ) ).toBe(
				true
			);
		} );

		it( 'should return true for sgtm when sGTM domain is set', () => {
			const wrapper = createWrapper( {
				settings: {
					general: {
						sgtm_domain: 'gtm.example.com',
					},
				},
			} );
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'sgtm' ) ).toBe( true );
		} );

		it( 'should return true for cookieKeeper with sGTM and premium', () => {
			const wrapper = createWrapper(
				{
					settings: {
						general: {
							sgtm_domain: 'gtm.example.com',
						},
					},
				},
				{
					isPremium: true,
					hasValidLicense: true,
				}
			);
			const { result } = renderHook( () => useFeatureFlags(), {
				wrapper,
			} );

			expect( result.current.canUseFeature( 'cookieKeeper' ) ).toBe(
				true
			);
		} );
	} );
} );
