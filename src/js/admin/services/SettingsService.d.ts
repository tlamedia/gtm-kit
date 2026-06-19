/**
 * TypeScript definitions for SettingsService
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import type { Tier } from '../constants/tiers';

export interface SettingsServiceData {
	settings?: Settings;
	site_data?: SiteData;
	install_data?: InstallData;
	notifications?: Notifications;
	currentPage?: string;
	isPremium?: boolean;
	isPremiumPlugin?: boolean;
	hasValidLicense?: boolean;
	user_roles?: UserRole[];
	plugins?: Record<string, boolean>;
	taxonomyOptions?: TaxonomyOption[];
	templates?: Record<string, unknown>;
	root?: string;
	nonce?: string;
	rootId?: string;
	adminPageUrl?: string;
	pluginUrl?: string;
	opportunities?: Opportunity[];
	pluginInstallUrl?: string;
	current_page?: string;
	pageOptions?: PageOption[];
	tutorials?: Tutorial[];
	generatorUrl?: string;
}

export interface Settings {
	general?: GeneralSettings;
	integrations?: IntegrationSettings;
	premium?: PremiumSettings;
	[key: string]: unknown;
}

export interface GeneralSettings {
	gtm_id?: string;
	container_active?: boolean;
	sgtm_domain?: string;
	sgtm_container_identifier?: string;
	sgtm_cookie_keeper?: boolean;
	datalayer_name?: string;
	analytics_active?: boolean;
	just_the_container?: boolean;
	load_js_event?: boolean;
	gtm_auth?: string;
	gtm_preview?: string;
	exclude_user_roles?: string[];
	script_implementation?: number;
	noscript_implementation?: number;
	[key: string]: unknown;
}

export interface IntegrationSettings {
	woocommerce_integration?: boolean;
	woocommerce_brand?: string;
	woocommerce_use_sku?: boolean;
	woocommerce_exclude_tax?: boolean;
	woocommerce_exclude_shipping?: boolean;
	cf7_integration?: boolean;
	edd_integration?: boolean;
	[key: string]: unknown;
}

export interface PremiumSettings {
	woocommerce_webhooks?: boolean;
	woocommerce_purchase_webhook?: boolean;
	woocommerce_order_paid_webhook?: boolean;
	woocommerce_refund_webhook?: boolean;
	[key: string]: unknown;
}

export interface SiteData {
	ecommerce?: boolean;
	site_url?: string;
	admin_url?: string;
	[key: string]: unknown;
}

export interface InstallData {
	[key: string]: unknown;
}

export interface Notifications {
	metrics?: {
		total: number;
		problem?: number;
	};
	[key: string]: unknown;
}

export interface UserRole {
	value: string;
	label: string;
}

export interface TaxonomyOption {
	value: string;
	label: string;
}

export interface Opportunity {
	id: string;
	title: string;
	description: string;
	link?: string;
	[key: string]: unknown;
}

export interface PageOption {
	value: string | number;
	label: string;
}

export interface Tutorial {
	title: string;
	text: string[];
	link?: {
		external: boolean;
		url: string;
		text: string;
	};
	featured?: boolean;
}

/**
 * SettingsService singleton class
 */
declare class SettingsService {
	private data: SettingsServiceData;

	constructor();

	/**
	 * Get all settings data
	 */
	getSettings(): Settings;

	/**
	 * Get site data
	 */
	getSiteData(): SiteData;

	/**
	 * Get install data (wizard only)
	 */
	getInstallData(): InstallData;

	/**
	 * Get notifications data
	 */
	getNotifications(): Notifications;

	/**
	 * Get current page identifier
	 */
	getCurrentPage(): string;

	/**
	 * Check if premium version is active
	 */
	isPremium(): boolean;

	/**
	 * Check if the GTM Kit Premium plugin specifically is active
	 */
	isPremiumPlugin(): boolean;

	/**
	 * Resolve the active access tier (free | woo | premium)
	 */
	getActiveTier(): Tier;

	/**
	 * Check if user has valid license
	 */
	hasValidLicense(): boolean;

	/**
	 * Get user roles
	 */
	getUserRoles(): UserRole[];

	/**
	 * Get all plugin status data
	 */
	getPlugins(): Record<string, boolean>;

	/**
	 * Check if a specific plugin is active
	 */
	isPluginActive(pluginSlug: string): boolean;

	/**
	 * Get taxonomy options (WooCommerce)
	 */
	getTaxonomyOptions(): TaxonomyOption[];

	/**
	 * Get templates data
	 */
	getTemplates(): Record<string, unknown>;

	/**
	 * Get REST API root URL
	 */
	getRestRoot(): string;

	/**
	 * Get REST API nonce for authentication
	 */
	getNonce(): string;

	/**
	 * Get root element ID for React mount point
	 */
	getRootId(): string;

	/**
	 * Get admin page URL
	 */
	getAdminPageUrl(): string;

	/**
	 * Get plugin URL
	 */
	getPluginUrl(): string;

	/**
	 * Get opportunities data (upgrades/upsells)
	 */
	getOpportunities(): Opportunity[];

	/**
	 * Get plugin install URL
	 */
	getPluginInstallUrl(): string;

	/**
	 * Get current page (alias for getCurrentPage)
	 */
	getCurrentPageAlias(): string;

	/**
	 * Get page options for page select
	 */
	getPageOptions(): PageOption[];

	/**
	 * Get tutorials data
	 */
	getTutorials(): Tutorial[];

	/**
	 * Get generator URL for template generation
	 */
	getGeneratorUrl(): string;

	/**
	 * Get raw data by key (discouraged - use specific methods instead)
	 */
	getRaw(key: string): unknown;
}

declare const settingsService: SettingsService;
export default settingsService;
