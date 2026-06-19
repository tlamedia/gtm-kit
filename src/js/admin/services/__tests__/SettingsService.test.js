/**
 * SettingsService Unit Tests
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import SettingsService from '../SettingsService';
import { TIERS } from '../../constants/tiers';

describe( 'SettingsService', () => {
	beforeEach( () => {
		// Reset window.gtmkitSettings before each test
		window.gtmkitSettings = {
			settings: {
				general: { gtm_id: 'GTM-123' },
				integrations: { woocommerce_integration: '1' },
				premium: {},
			},
			site_data: {
				site_url: 'http://example.com',
			},
			notifications: {
				metrics: { total: 5, problem: 2 },
			},
			isPremium: true,
			hasValidLicense: true,
			plugins: {
				woocommerce: true,
				edd: false,
			},
			user_roles: [ 'administrator', 'editor' ],
			taxonomyOptions: [ { label: 'Category', value: 'category' } ],
			templates: [ { id: 'gtm-template-1', name: 'Template 1' } ],
			nonce: 'test-nonce-123',
			root: 'http://example.com/wp-json/',
			rootId: 'gtmkit-settings',
			adminPageUrl:
				'http://example.com/wp-admin/admin.php?page=gtmkit_general',
			currentPage: 'general',
		};

		// Reinitialize SettingsService with new data
		SettingsService.data = window.gtmkitSettings || {};
	} );

	describe( 'getSettings', () => {
		it( 'should return settings object', () => {
			const settings = SettingsService.getSettings();
			expect( settings ).toEqual( {
				general: { gtm_id: 'GTM-123' },
				integrations: { woocommerce_integration: '1' },
				premium: {},
			} );
		} );

		it( 'should return empty object if settings not defined', () => {
			delete window.gtmkitSettings.settings;
			SettingsService.data = window.gtmkitSettings;
			const settings = SettingsService.getSettings();
			expect( settings ).toEqual( {} );
		} );
	} );

	describe( 'getSiteData', () => {
		it( 'should return site data object', () => {
			const siteData = SettingsService.getSiteData();
			expect( siteData ).toEqual( {
				site_url: 'http://example.com',
			} );
		} );

		it( 'should return empty object if site_data not defined', () => {
			delete window.gtmkitSettings.site_data;
			SettingsService.data = window.gtmkitSettings;
			const siteData = SettingsService.getSiteData();
			expect( siteData ).toEqual( {} );
		} );
	} );

	describe( 'getNotifications', () => {
		it( 'should return notifications object', () => {
			const notifications = SettingsService.getNotifications();
			expect( notifications ).toEqual( {
				metrics: { total: 5, problem: 2 },
			} );
		} );

		it( 'should return default object if notifications not defined', () => {
			delete window.gtmkitSettings.notifications;
			SettingsService.data = window.gtmkitSettings;
			const notifications = SettingsService.getNotifications();
			expect( notifications ).toEqual( {
				metrics: { total: 0, problem: 0 },
			} );
		} );
	} );

	describe( 'getCurrentPage', () => {
		it( 'should return current page', () => {
			expect( SettingsService.getCurrentPage() ).toBe( 'general' );
		} );

		it( 'should return empty string if currentPage not defined', () => {
			delete window.gtmkitSettings.currentPage;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getCurrentPage() ).toBe( '' );
		} );
	} );

	describe( 'isPremium', () => {
		it( 'should return true when premium is active', () => {
			expect( SettingsService.isPremium() ).toBe( true );
		} );

		it( 'should return false when premium is not active', () => {
			window.gtmkitSettings.isPremium = false;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.isPremium() ).toBe( false );
		} );

		it( 'should return false when isPremium is undefined', () => {
			delete window.gtmkitSettings.isPremium;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.isPremium() ).toBe( false );
		} );
	} );

	describe( 'isPremiumPlugin', () => {
		it( 'should return true when the Premium plugin is active', () => {
			window.gtmkitSettings.isPremiumPlugin = true;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.isPremiumPlugin() ).toBe( true );
		} );

		it( 'should return false when only a non-Premium add-on is active', () => {
			// isPremium is true (a paid add-on) but Premium specifically is not.
			expect( SettingsService.isPremiumPlugin() ).toBe( false );
		} );

		it( 'should return false when isPremiumPlugin is undefined', () => {
			delete window.gtmkitSettings.isPremiumPlugin;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.isPremiumPlugin() ).toBe( false );
		} );
	} );

	describe( 'getActiveTier', () => {
		it( 'should resolve to premium when the Premium plugin is active', () => {
			window.gtmkitSettings.isPremium = true;
			window.gtmkitSettings.isPremiumPlugin = true;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getActiveTier() ).toBe( TIERS.PREMIUM );
		} );

		it( 'should resolve to woo when a paid add-on is active but not Premium', () => {
			window.gtmkitSettings.isPremium = true;
			window.gtmkitSettings.isPremiumPlugin = false;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getActiveTier() ).toBe( TIERS.WOO );
		} );

		it( 'should resolve to free when no paid add-on is active', () => {
			window.gtmkitSettings.isPremium = false;
			window.gtmkitSettings.isPremiumPlugin = false;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getActiveTier() ).toBe( TIERS.FREE );
		} );

		it( 'should let Premium win when both signals are set', () => {
			window.gtmkitSettings.isPremium = true;
			window.gtmkitSettings.isPremiumPlugin = true;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getActiveTier() ).toBe( TIERS.PREMIUM );
		} );
	} );

	describe( 'hasValidLicense', () => {
		it( 'should return true when license is valid', () => {
			expect( SettingsService.hasValidLicense() ).toBe( true );
		} );

		it( 'should return false when license is not valid', () => {
			window.gtmkitSettings.hasValidLicense = false;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.hasValidLicense() ).toBe( false );
		} );
	} );

	describe( 'getUserRoles', () => {
		it( 'should return user roles array', () => {
			const roles = SettingsService.getUserRoles();
			expect( roles ).toEqual( [ 'administrator', 'editor' ] );
		} );

		it( 'should return empty array if user_roles not defined', () => {
			delete window.gtmkitSettings.user_roles;
			SettingsService.data = window.gtmkitSettings;
			const roles = SettingsService.getUserRoles();
			expect( roles ).toEqual( [] );
		} );
	} );

	describe( 'getPlugins', () => {
		it( 'should return plugins object', () => {
			const plugins = SettingsService.getPlugins();
			expect( plugins ).toEqual( {
				woocommerce: true,
				edd: false,
			} );
		} );

		it( 'should return empty object if plugins not defined', () => {
			delete window.gtmkitSettings.plugins;
			SettingsService.data = window.gtmkitSettings;
			const plugins = SettingsService.getPlugins();
			expect( plugins ).toEqual( {} );
		} );
	} );

	describe( 'isPluginActive', () => {
		it( 'should return true for active plugin', () => {
			expect( SettingsService.isPluginActive( 'woocommerce' ) ).toBe(
				true
			);
		} );

		it( 'should return false for inactive plugin', () => {
			expect( SettingsService.isPluginActive( 'edd' ) ).toBe( false );
		} );

		it( 'should return false for non-existent plugin', () => {
			expect( SettingsService.isPluginActive( 'unknown' ) ).toBe( false );
		} );

		it( 'should return false when plugins object is undefined', () => {
			delete window.gtmkitSettings.plugins;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.isPluginActive( 'woocommerce' ) ).toBe(
				false
			);
		} );
	} );

	describe( 'getTaxonomyOptions', () => {
		it( 'should return taxonomy options array', () => {
			const options = SettingsService.getTaxonomyOptions();
			expect( options ).toEqual( [
				{ label: 'Category', value: 'category' },
			] );
		} );

		it( 'should return empty array if taxonomyOptions not defined', () => {
			delete window.gtmkitSettings.taxonomyOptions;
			SettingsService.data = window.gtmkitSettings;
			const options = SettingsService.getTaxonomyOptions();
			expect( options ).toEqual( [] );
		} );
	} );

	describe( 'getTemplates', () => {
		it( 'should return the templates array', () => {
			const templates = SettingsService.getTemplates();
			expect( templates ).toEqual( [
				{ id: 'gtm-template-1', name: 'Template 1' },
			] );
		} );

		it( 'should return an empty array if templates not defined', () => {
			delete window.gtmkitSettings.templates;
			SettingsService.data = window.gtmkitSettings;
			const templates = SettingsService.getTemplates();
			expect( templates ).toEqual( [] );
		} );
	} );

	describe( 'getRestRoot', () => {
		it( 'should return REST API root URL', () => {
			expect( SettingsService.getRestRoot() ).toBe(
				'http://example.com/wp-json/'
			);
		} );

		it( 'should return empty string if root not defined', () => {
			delete window.gtmkitSettings.root;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getRestRoot() ).toBe( '' );
		} );
	} );

	describe( 'getNonce', () => {
		it( 'should return nonce value', () => {
			expect( SettingsService.getNonce() ).toBe( 'test-nonce-123' );
		} );

		it( 'should return empty string if nonce not defined', () => {
			delete window.gtmkitSettings.nonce;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getNonce() ).toBe( '' );
		} );
	} );

	describe( 'getRootId', () => {
		it( 'should return root element ID', () => {
			expect( SettingsService.getRootId() ).toBe( 'gtmkit-settings' );
		} );

		it( 'should return default ID if rootId not defined', () => {
			delete window.gtmkitSettings.rootId;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getRootId() ).toBe( 'gtmkit-settings' );
		} );
	} );

	describe( 'getAdminPageUrl', () => {
		it( 'should return admin page URL', () => {
			expect( SettingsService.getAdminPageUrl() ).toBe(
				'http://example.com/wp-admin/admin.php?page=gtmkit_general'
			);
		} );

		it( 'should return empty string if adminPageUrl not defined', () => {
			delete window.gtmkitSettings.adminPageUrl;
			SettingsService.data = window.gtmkitSettings;
			expect( SettingsService.getAdminPageUrl() ).toBe( '' );
		} );
	} );

	describe( 'getRaw', () => {
		it( 'should return raw value for any key', () => {
			expect( SettingsService.getRaw( 'currentPage' ) ).toBe( 'general' );
			expect( SettingsService.getRaw( 'isPremium' ) ).toBe( true );
		} );

		it( 'should return undefined for non-existent key', () => {
			expect(
				SettingsService.getRaw( 'nonExistentKey' )
			).toBeUndefined();
		} );
	} );
} );
