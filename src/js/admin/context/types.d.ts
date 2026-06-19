/**
 * TypeScript definitions for Context types
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { Settings, Notifications, SiteData, UserRole } from '../services/SettingsService';
import type { Tier } from '../constants/tiers';

/**
 * SettingsDataContext Types
 */
export interface SettingsDataContextValue {
	// State
	settings: Settings;
	fetchedSettings: Settings;
	isPending: boolean;
	canSave: boolean;
	notice: string;
	hasError: boolean;

	// Methods
	updateSettings: () => Promise<void>;
	updateStateSettings: (group: string, key: string, val: unknown) => void;
	importSettings: (pluginSettings: Partial<Settings>) => void;
	fetchSettings: () => void;

	// Backward compatibility
	useSettings: Settings;
}

/**
 * NotificationContext Types
 */
export interface NotificationContextValue {
	// State
	notifications: Notifications;
	isUpdatingNotifications: boolean;
	notice: string;
	hasError: boolean;

	// Methods
	setNotificationStatus: (id: string, action: string) => Promise<void>;

	// Backward compatibility
	useNotifications: Notifications;
}

/**
 * LicenseContext Types
 */
export interface LicenseContextValue {
	// State
	licenseKey: string;
	isSendingLicenseKey: boolean;
	isLicenseKeySent: boolean;
	licenseKeyMessage: string;
	isPremium: boolean;
	hasValidLicense: boolean;
	activeTier: Tier;

	// Methods
	updateLicenseKey: (val: string) => void;
	sendLicenseKey: () => Promise<void>;
	deactivateLicense: () => Promise<unknown>;
}

/**
 * SupportContext Types
 */
export interface SupportContextValue {
	// State
	supportTicket: string;
	isSendingSystemData: boolean;
	isSystemDataSent: boolean;
	systemDataMessage: string;

	// Methods
	updateSupportTicket: (val: string) => void;
	sendSystemData: () => Promise<void>;

	// Backward compatibility
	useSupportTicket: string;
	useIsSendingSystemData: boolean;
	useIsSystemDataSent: boolean;
	useSystemDataMessage: string;
}

/**
 * SiteDataContext Types
 */
export interface SiteDataContextValue {
	siteData: SiteData;
	installData: Record<string, unknown>;
	userRoles: UserRole[];

	// Backward compatibility
	useSiteData: SiteData;
	useUserRoles: UserRole[];
}
