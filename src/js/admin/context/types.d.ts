/**
 * TypeScript definitions for Context types
 */

import {
	Settings,
	Notifications,
	SiteData,
	SupportSyncState,
	UserRole,
} from '../services/SettingsService';
import type { Tier } from '../constants/tiers';

/**
 * SettingsDataContext Types
 */
/**
 * What the server reports about the loader Stape issues. `status` and
 * `reason` are present after a save, refresh or paste.
 */
export interface SgtmLoaderState {
	source: 'api' | 'pasted' | 'standard';
	region: string;
	fetchedAt: number;
	status?: string;
	reason?: string;
}

export interface SettingsDataContextValue {
	// State
	settings: Settings;
	fetchedSettings: Settings;
	isPending: boolean;
	canSave: boolean;
	notice: string;
	hasError: boolean;
	sgtmLoader: SgtmLoaderState | null;

	// Methods
	updateSettings: () => Promise<void>;
	updateStateSettings: (group: string, key: string, val: unknown) => void;
	importSettings: (pluginSettings: Partial<Settings>) => void;
	fetchSettings: () => void;
	setSgtmLoader: (state: SgtmLoaderState) => void;

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
	/** Why the last deactivation request failed, or empty */
	deactivateLicenseMessage: string;
	isPremium: boolean;
	hasValidLicense: boolean;
	activeTier: Tier;

	// Methods
	updateLicenseKey: (val: string) => void;
	sendLicenseKey: () => Promise<void>;
	/** Resolves false when the request failed */
	deactivateLicense: () => Promise<boolean>;
}

/**
 * SupportContext Types
 */
export interface SupportContextValue {
	// State
	supportTicket: string;
	isSendingSystemData: boolean;
	isSystemDataSent: boolean;
	/** Sending did not work: the request failed, or the support server was unreachable */
	isSystemDataFailed: boolean;
	systemDataMessage: string;
	supportSync: SupportSyncState;
	isStoppingSupportSync: boolean;

	// Methods
	updateSupportTicket: (val: string) => void;
	sendSystemData: () => Promise<void>;
	stopSupportSync: () => Promise<void>;

	// Backward compatibility
	useSupportTicket: string;
	useIsSendingSystemData: boolean;
	useIsSystemDataSent: boolean;
	useSystemDataMessage: string;
	useIsSystemDataFailed: boolean;
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
