/**
 * SettingsService - Centralized access to window.gtmkitSettings
 *
 * This service provides a clean abstraction over the global window object,
 * making the codebase testable and reducing coupling. All access to
 * window.gtmkitSettings should go through this service.
 *
 * @example
 * import SettingsService from '../services/SettingsService';
 *
 * const settings = SettingsService.getSettings();
 * const isPremium = SettingsService.isPremium();
 * const isWooActive = SettingsService.isPluginActive('woocommerce');
 *
 * @typedef {import('./SettingsService').default} SettingsServiceType
 * @class SettingsService
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */
import { TIERS } from '../constants/tiers';

class SettingsService {
	constructor() {
		/** @type {import('./SettingsService').SettingsServiceData} */
		this.data = window.gtmkitSettings || {};
	}

	/**
	 * Get all settings data
	 *
	 * @return {Object} Settings object from window.gtmkitSettings.settings
	 */
	getSettings() {
		return this.data.settings || {};
	}

	/**
	 * Get site data
	 *
	 * @return {Object} Site data object from window.gtmkitSettings.site_data
	 */
	getSiteData() {
		return this.data.site_data || {};
	}

	/**
	 * Get install data (wizard only)
	 *
	 * @return {Object} Install data object from window.gtmkitSettings.install_data
	 */
	getInstallData() {
		return this.data.install_data || {};
	}

	/**
	 * Get notifications data
	 *
	 * @return {Object} Notifications object with metrics
	 */
	getNotifications() {
		return this.data.notifications || { metrics: { total: 0, problem: 0 } };
	}

	/**
	 * Get current page identifier
	 *
	 * @return {string} Current page (e.g., 'wizard', 'general')
	 */
	getCurrentPage() {
		return this.data.currentPage || '';
	}

	/**
	 * Check if premium version is active
	 *
	 * @return {boolean} True if premium version is active
	 */
	isPremium() {
		return Boolean( this.data.isPremium );
	}

	/**
	 * Check if the GTM Kit Premium plugin specifically is active
	 *
	 * Distinct from isPremium(), which is true for any paid add-on (Woo or
	 * Premium). This is true only for Premium, so the two tiers can be told
	 * apart.
	 *
	 * @return {boolean} True if the Premium plugin is active
	 */
	isPremiumPlugin() {
		return Boolean( this.data.isPremiumPlugin );
	}

	/**
	 * Resolve the active access tier (free | woo | premium).
	 *
	 * The single source of truth for the active tier. Premium wins when both
	 * add-ons are somehow active; Woo is any other paid add-on; free otherwise.
	 *
	 * @return {string} One of the TIERS values
	 */
	getActiveTier() {
		if ( this.isPremiumPlugin() ) {
			return TIERS.PREMIUM;
		}
		if ( this.isPremium() ) {
			return TIERS.WOO;
		}
		return TIERS.FREE;
	}

	/**
	 * Check if user has valid license
	 *
	 * @return {boolean} True if license is valid
	 */
	hasValidLicense() {
		return Boolean( this.data.hasValidLicense );
	}

	/**
	 * Get user roles
	 *
	 * @return {Array} Array of user role objects
	 */
	getUserRoles() {
		return this.data.user_roles || [];
	}

	/**
	 * Get the plugin version string.
	 *
	 * @return {string} The version.
	 */
	getVersion() {
		return this.data.version || '';
	}

	/**
	 * Get the stored (already-activated) license key, used to render the
	 * masked key on the License page. Empty when no license is active.
	 *
	 * @return {string} The current license key.
	 */
	getCurrentLicenseKey() {
		return this.data.currentLicenseKey || '';
	}

	/**
	 * Get the license expiry as a Unix timestamp (seconds), or null when the
	 * license never expires or no data is available.
	 *
	 * @return {number|null} The expiry timestamp.
	 */
	getLicenseExpiresAt() {
		return typeof this.data.licenseExpiresAt === 'number'
			? this.data.licenseExpiresAt
			: null;
	}

	/**
	 * Get all plugin status data
	 *
	 * @return {Object} Object with plugin slugs as keys, boolean status as values
	 */
	getPlugins() {
		return this.data.plugins || {};
	}

	/**
	 * Get the integration metadata keyed by slug ({ title, option, description,
	 * path, type }), used by the Integrations hub.
	 *
	 * @return {Object} Integration metadata by slug.
	 */
	getIntegrations() {
		return this.data.integrations || {};
	}

	/**
	 * Check if a specific plugin is active
	 *
	 * @param {string} pluginSlug - Plugin slug to check (e.g., 'woocommerce', 'edd')
	 * @return {boolean} True if plugin is active
	 *
	 * @example
	 * const isWooActive = SettingsService.isPluginActive('woocommerce');
	 */
	isPluginActive( pluginSlug ) {
		return Boolean( this.data.plugins?.[ pluginSlug ] );
	}

	/**
	 * Get taxonomy options (WooCommerce)
	 *
	 * @return {Array} Array of taxonomy option objects
	 */
	getTaxonomyOptions() {
		return this.data.taxonomyOptions || [];
	}

	/**
	 * Get templates data
	 *
	 * @return {Object} Templates object
	 */
	getTemplates() {
		return Array.isArray( this.data.templates ) ? this.data.templates : [];
	}

	/**
	 * Get REST API root URL
	 *
	 * @return {string} REST API root URL
	 */
	getRestRoot() {
		return this.data.root || '';
	}

	/**
	 * Get REST API nonce for authentication
	 *
	 * @return {string} REST API nonce
	 */
	getNonce() {
		return this.data.nonce || '';
	}

	/**
	 * Get root element ID for React mount point
	 *
	 * @return {string} Root element ID (default: 'gtmkit-settings')
	 */
	getRootId() {
		return this.data.rootId || 'gtmkit-settings';
	}

	/**
	 * Get admin page URL
	 *
	 * @return {string} Admin page URL
	 */
	getAdminPageUrl() {
		return this.data.adminPageUrl || '';
	}

	/**
	 * Get plugin URL
	 *
	 * @return {string} Plugin URL
	 */
	getPluginUrl() {
		return this.data.pluginUrl || '';
	}

	/**
	 * Get opportunities data (upgrades/upsells)
	 *
	 * @return {Array} Array of opportunity objects
	 */
	getOpportunities() {
		return this.data.opportunities || [];
	}

	/**
	 * Get plugin install URL
	 *
	 * @return {string} Plugin install URL
	 */
	getPluginInstallUrl() {
		return this.data.pluginInstallUrl || '';
	}

	/**
	 * Get current page (alias for getCurrentPage, kept for compatibility)
	 *
	 * @return {string} Current page
	 */
	getCurrentPageAlias() {
		return this.data.current_page || '';
	}

	/**
	 * Get page options for page select
	 *
	 * @return {Array} Array of page options
	 */
	getPageOptions() {
		return this.data.pageOptions || [];
	}

	/**
	 * Get tutorials data
	 *
	 * @return {Array} Array of tutorial objects
	 */
	getTutorials() {
		return this.data.tutorials || [];
	}

	/**
	 * Get generator URL for template generation
	 *
	 * @return {string} Template generator API URL
	 */
	getGeneratorUrl() {
		return this.data.generatorUrl || '';
	}

	/**
	 * Get the admin status badges that the Consent settings page renders
	 * above its sections.
	 *
	 * Populated server-side from the `gtmkit_consent_admin_badges` PHP
	 * filter; add-ons (e.g. the Premium WP Consent API integration)
	 * push entries that the React app turns into Notice banners. Each
	 * entry is shaped as
	 * `{ id: string, message: string, severity: 'info'|'warning'|'success'|'error' }`.
	 *
	 * @return {Array<{id: string, message: string, severity: string}>} The badge entries.
	 */
	getConsentAdminBadges() {
		return Array.isArray( this.data.consentAdminBadges )
			? this.data.consentAdminBadges
			: [];
	}

	/**
	 * Get raw data by key (discouraged - use specific methods instead)
	 *
	 * This method provides raw access to window.gtmkitSettings for edge cases.
	 * Prefer using specific getter methods above for type safety and documentation.
	 *
	 * @param {string} key - Key to retrieve from window.gtmkitSettings
	 * @return {*} Value at the specified key
	 *
	 * @deprecated Use specific getter methods instead
	 */
	getRaw( key ) {
		return this.data[ key ];
	}
}

// Export singleton instance
export default new SettingsService();
