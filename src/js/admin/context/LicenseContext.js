/**
 * LicenseContext - License Management
 *
 * Responsibilities:
 * - License key management
 * - License activation/deactivation
 * - Premium feature flags
 *
 * @typedef {import('./types').LicenseContextValue} LicenseContextValue
 *
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */

/*WordPress*/
import { createContext, useReducer } from '@wordpress/element';

/*Inbuilt APIs*/
import {
	sendLicenseKey as apiSendLicenseKey,
	deactivateLicense as apiDeactivateLicense,
} from '../api/settings';

/*Services*/
import SettingsService from '../services/SettingsService';
import * as ActionTypes from '../constants/actionTypes';
import { TIERS } from '../constants/tiers';

/*Utils*/
import { getUserFriendlyMessage } from '../utils/errorHandler';

/**
 * @type {import('react').Context<LicenseContextValue>}
 */
export const LicenseContext = createContext();

const initialState = {
	licenseKey: '',
	isSendingLicenseKey: false,
	isLicenseKeySent: false,
	licenseKeyMessage: '',
	deactivateLicenseMessage: '',
	isPremium: false,
	hasValidLicense: false,
	activeTier: TIERS.FREE,
};

const licenseReducer = ( state, action ) => {
	const newState = { ...state };

	switch ( action.type ) {
		case ActionTypes.SEND_LICENSE_KEY_BEFORE:
			newState.isSendingLicenseKey = true;
			break;

		case ActionTypes.SEND_LICENSE_KEY:
			newState.isSendingLicenseKey = false;
			newState.isLicenseKeySent =
				action.payload.isLicenseKeySent || false;
			newState.licenseKeyMessage = action.payload.licenseKeyMessage || '';
			break;

		case ActionTypes.UPDATE_STATE:
			if ( action.payload.licenseKey !== undefined ) {
				newState.licenseKey = action.payload.licenseKey;
			}
			if ( action.payload.isSendingLicenseKey !== undefined ) {
				newState.isSendingLicenseKey =
					action.payload.isSendingLicenseKey;
			}
			if ( action.payload.deactivateLicenseMessage !== undefined ) {
				newState.deactivateLicenseMessage =
					action.payload.deactivateLicenseMessage;
			}
			break;

		default:
			return state;
	}

	return newState;
};

export const LicenseProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( licenseReducer, {
		...initialState,
		isPremium: SettingsService.isPremium(),
		hasValidLicense: SettingsService.hasValidLicense(),
		activeTier: SettingsService.getActiveTier(),
	} );

	/**
	 * Update license key in state
	 *
	 * @param {string} val - New license key value
	 */
	const updateLicenseKey = ( val ) => {
		dispatch( {
			type: ActionTypes.UPDATE_STATE,
			payload: {
				licenseKey: val,
			},
		} );
	};

	/**
	 * Send license key to API for activation
	 */
	const sendLicenseKey = async () => {
		dispatch( {
			type: ActionTypes.SEND_LICENSE_KEY_BEFORE,
		} );

		try {
			const response = await apiSendLicenseKey( state.licenseKey );

			dispatch( {
				type: ActionTypes.SEND_LICENSE_KEY,
				payload: {
					isLicenseKeySent: response.success,
					licenseKeyMessage: response.data,
				},
			} );
		} catch ( error ) {
			// A rejected key arrives as a `success: false` response above, so
			// anything thrown is a failed request, not a problem with the key.
			dispatch( {
				type: ActionTypes.SEND_LICENSE_KEY,
				payload: {
					isLicenseKeySent: false,
					licenseKeyMessage: getUserFriendlyMessage( error ),
				},
			} );
		}
	};

	/**
	 * Deactivate current license
	 *
	 * A failed request leaves its message in `deactivateLicenseMessage`.
	 *
	 * @return {Promise<boolean>} Whether the request completed.
	 */
	const deactivateLicense = async () => {
		dispatch( {
			type: ActionTypes.UPDATE_STATE,
			payload: { deactivateLicenseMessage: '' },
		} );

		try {
			await apiDeactivateLicense();
			return true;
		} catch ( error ) {
			dispatch( {
				type: ActionTypes.UPDATE_STATE,
				payload: {
					deactivateLicenseMessage: getUserFriendlyMessage( error ),
				},
			} );
			return false;
		}
	};

	const value = {
		// State
		licenseKey: state.licenseKey,
		isSendingLicenseKey: state.isSendingLicenseKey,
		isLicenseKeySent: state.isLicenseKeySent,
		licenseKeyMessage: state.licenseKeyMessage,
		deactivateLicenseMessage: state.deactivateLicenseMessage,
		isPremium: state.isPremium,
		hasValidLicense: state.hasValidLicense,
		activeTier: state.activeTier,

		// Methods
		updateLicenseKey,
		sendLicenseKey,
		deactivateLicense,

		// Backward compatibility aliases
		useLicenseKey: state.licenseKey,
		useIsSendingLicenseKey: state.isSendingLicenseKey,
		useIsLicenseKeySent: state.isLicenseKeySent,
		useLicenseKeyMessage: state.licenseKeyMessage,
	};

	return (
		<LicenseContext.Provider value={ value }>
			{ children }
		</LicenseContext.Provider>
	);
};

export default LicenseProvider;
