/**
 * SettingsDataContext - Core Settings CRUD Operations
 *
 * Responsibilities:
 * - Settings fetch, update, and import
 * - State vs. fetched settings comparison
 * - canSave flag management
 * - isPending loading state
 *
 * @typedef {import('./types').SettingsDataContextValue} SettingsDataContextValue
 *
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */

/*WordPress*/
import {
	createContext,
	useContext,
	useReducer,
	useEffect,
	useRef,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/*Library*/
import { isEqual } from 'lodash';

/*Inbuilt APIs*/
import { updateSettings as apiUpdateSettings } from '../api/settings';

/*Services*/
import SettingsService from '../services/SettingsService';
import * as ActionTypes from '../constants/actionTypes';

/*Utils*/
import { getUserFriendlyMessage } from '../utils/errorHandler';

/*Context*/
import { ToastContext } from './ToastContext';

/**
 * @type {import('react').Context<SettingsDataContextValue>}
 */
export const SettingsDataContext = createContext();

const initialState = {
	fetchedSettings: {},
	stateSettings: {},
	isPending: true,
	canSave: false,
	notice: '',
	hasError: false,
};

/**
 * Normalize settings object structure
 * Ensures all expected groups exist to prevent comparison issues
 *
 * @param {Object} settings - Raw settings object
 * @return {Object} Normalized settings object
 */
const normalizeSettings = ( settings ) => {
	return {
		...settings,
		general: settings.general || {},
		integrations: settings.integrations || {},
		premium: settings.premium || {},
	};
};

const settingsReducer = ( state, action ) => {
	const newState = { ...state };

	switch ( action.type ) {
		case ActionTypes.FETCH_SETTINGS:
			newState.fetchedSettings = normalizeSettings(
				action.payload.fetchedSettings
			);
			newState.stateSettings = normalizeSettings(
				action.payload.stateSettings
			);
			newState.isPending = false;
			newState.canSave = false;

			if (
				action.payload.fetchedSettings.gtm_kit_api_fetch_settings_errors
			) {
				newState.notice = 'An error occurred.';
				newState.hasError = true;
			}
			break;

		case ActionTypes.UPDATE_SETTINGS_BEFORE:
			newState.isPending = true;
			newState.notice = '';
			break;

		case ActionTypes.UPDATE_SETTINGS:
			newState.fetchedSettings = normalizeSettings(
				action.payload.fetchedSettings
			);
			newState.stateSettings = normalizeSettings(
				action.payload.stateSettings
			);
			newState.isPending = false;
			newState.canSave = false;
			newState.notice = __( 'Settings saved successfully.', 'gtm-kit' );
			newState.hasError = false;
			break;

		case ActionTypes.UPDATE_STATE:
			if ( action.payload.fetchedSettings !== undefined ) {
				newState.fetchedSettings = action.payload.fetchedSettings;
			}
			if ( action.payload.stateSettings !== undefined ) {
				newState.stateSettings = action.payload.stateSettings;
			}
			if ( action.payload.canSave !== undefined ) {
				newState.canSave = action.payload.canSave;
			}
			if ( action.payload.isPending !== undefined ) {
				newState.isPending = action.payload.isPending;
			}
			if ( action.payload.notice !== undefined ) {
				newState.notice = action.payload.notice;
			}
			if ( action.payload.hasError !== undefined ) {
				newState.hasError = action.payload.hasError;
			}
			break;

		default:
			return state;
	}

	return newState;
};

export const SettingsDataProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( settingsReducer, initialState );
	const toast = useContext( ToastContext );

	/**
	 * Fetch settings from service on mount
	 */
	const fetchSettings = () => {
		const gotSettings = SettingsService.getSettings();

		dispatch( {
			type: ActionTypes.FETCH_SETTINGS,
			payload: {
				fetchedSettings: gotSettings,
				stateSettings: gotSettings,
			},
		} );
	};

	// The toast of the last failed save, while it is still on screen.
	const saveErrorToast = useRef( null );

	/**
	 * Update settings via API
	 */
	const updateSettings = async () => {
		// A new attempt answers whatever the last failure asked for, so that
		// message goes. Left up, it would contradict a save that now succeeds,
		// which is signalled only by the button, and failures would pile up.
		if ( saveErrorToast.current !== null ) {
			toast?.removeToast?.( saveErrorToast.current );
			saveErrorToast.current = null;
		}

		dispatch( {
			type: ActionTypes.UPDATE_SETTINGS_BEFORE,
		} );

		try {
			const updatedSettings = await apiUpdateSettings(
				state.stateSettings
			);

			dispatch( {
				type: ActionTypes.UPDATE_SETTINGS,
				payload: {
					fetchedSettings: updatedSettings,
					stateSettings: updatedSettings,
				},
			} );
		} catch ( error ) {
			// Get user-friendly error message
			const errorMessage = getUserFriendlyMessage( error );

			dispatch( {
				type: ActionTypes.UPDATE_STATE,
				payload: {
					isPending: false,
					hasError: true,
					notice: errorMessage,
				},
			} );

			// The settings screen shows the outcome of a save only through a
			// toast. A failed save stays up until dismissed, because it can
			// ask the user to do something before saving again.
			saveErrorToast.current = toast?.error( errorMessage, 0 ) ?? null;
		}
	};

	/**
	 * Update a specific setting in state
	 *
	 * @param {string} group - Settings group (e.g., 'general', 'integrations')
	 * @param {string} key   - Setting key within the group
	 * @param {*}      val   - New value for the setting
	 */
	const updateStateSettings = ( group, key, val ) => {
		// Create a deep copy to avoid mutating state
		const newSettings = {
			...state.stateSettings,
			[ group ]: {
				...( state.stateSettings[ group ] || {} ),
				[ key ]: val,
			},
		};

		// Check if settings have changed
		const canSave = ! isEqual( newSettings, state.fetchedSettings );

		dispatch( {
			type: ActionTypes.UPDATE_STATE,
			payload: {
				stateSettings: newSettings,
				canSave,
			},
		} );
	};

	/**
	 * Import settings from another plugin/source
	 *
	 * @param {Object} pluginSettings - Settings to import
	 */
	const importSettings = ( pluginSettings ) => {
		const newSettings = {
			...state.stateSettings,
			general: {
				...( state.stateSettings.general || {} ),
				...( pluginSettings.general || {} ),
			},
		};

		if ( 'integrations' in pluginSettings ) {
			newSettings.integrations = {
				...( state.stateSettings.integrations || {} ),
				...( pluginSettings.integrations || {} ),
			};
		}

		const canSave = ! isEqual( newSettings, state.fetchedSettings );

		dispatch( {
			type: ActionTypes.UPDATE_STATE,
			payload: {
				stateSettings: newSettings,
				canSave,
			},
		} );
	};

	// Fetch settings on mount
	useEffect( () => {
		fetchSettings();
	}, [] );

	const value = {
		// State
		settings: state.stateSettings,
		fetchedSettings: state.fetchedSettings,
		isPending: state.isPending,
		canSave: state.canSave,
		notice: state.notice,
		hasError: state.hasError,

		// Methods
		updateSettings,
		updateStateSettings,
		importSettings,
		fetchSettings,

		// Backward compatibility aliases (for gradual migration)
		useSettings: state.stateSettings,
	};

	return (
		<SettingsDataContext.Provider value={ value }>
			{ children }
		</SettingsDataContext.Provider>
	);
};

export default SettingsDataProvider;
