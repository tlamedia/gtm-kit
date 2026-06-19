/**
 * SiteDataContext - Read-Only Site and User Data
 *
 * Responsibilities:
 * - Site data (read-only, from PHP)
 * - Install data (wizard only)
 * - User roles
 *
 * This context provides data that doesn't change during the session.
 *
 * @typedef {import('./types').SiteDataContextValue} SiteDataContextValue
 *
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */

/*WordPress*/
import { createContext } from '@wordpress/element';

/*Services*/
import SettingsService from '../services/SettingsService';

/**
 * @type {import('react').Context<SiteDataContextValue>}
 */
export const SiteDataContext = createContext();

export const SiteDataProvider = ( { children } ) => {
	const value = {
		// Site data
		siteData: SettingsService.getSiteData(),

		// Install data (wizard only)
		installData:
			SettingsService.getCurrentPage() === 'wizard'
				? SettingsService.getInstallData()
				: {},

		// User roles
		userRoles: SettingsService.getUserRoles(),

		// Backward compatibility aliases
		useSiteData: SettingsService.getSiteData(),
		useInstallData:
			SettingsService.getCurrentPage() === 'wizard'
				? SettingsService.getInstallData()
				: {},
		useUserRoles: SettingsService.getUserRoles(),
	};

	return (
		<SiteDataContext.Provider value={ value }>
			{ children }
		</SiteDataContext.Provider>
	);
};

export default SiteDataProvider;
