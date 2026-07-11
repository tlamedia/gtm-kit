/**
 * SupportContext - Support Ticket Management
 *
 * Responsibilities:
 * - Support ticket ID management
 * - System data sending to support
 * - Live support sync session state (indicator + stop-sharing action)
 *
 * @typedef {import('./types').SupportContextValue} SupportContextValue
 */

/*WordPress*/
import { createContext, useReducer } from '@wordpress/element';

/*Inbuilt APIs*/
import {
	sendSystemData as apiSendSystemData,
	stopSupportSync as apiStopSupportSync,
} from '../api/settings';

/*Services*/
import SettingsService from '../services/SettingsService';
import * as ActionTypes from '../constants/actionTypes';

/**
 * @type {import('react').Context<SupportContextValue>}
 */
export const SupportContext = createContext();

const initialState = {
	supportTicket: '',
	isSendingSystemData: false,
	isSystemDataSent: false,
	systemDataMessage: '',
	supportSync: SettingsService.getSupportSync(),
	isStoppingSupportSync: false,
};

const supportReducer = ( state, action ) => {
	const newState = { ...state };

	switch ( action.type ) {
		case ActionTypes.SEND_SUPPORT_DATA_BEFORE:
			newState.isSendingSystemData = true;
			break;

		case ActionTypes.SEND_SUPPORT_DATA:
			newState.isSendingSystemData = false;
			newState.isSystemDataSent =
				action.payload.isSystemDataSent || false;
			newState.systemDataMessage = action.payload.systemDataMessage || '';
			if ( action.payload.supportSync !== undefined ) {
				newState.supportSync = action.payload.supportSync;
			}
			break;

		case ActionTypes.STOP_SUPPORT_SYNC_BEFORE:
			newState.isStoppingSupportSync = true;
			break;

		case ActionTypes.STOP_SUPPORT_SYNC:
			newState.isStoppingSupportSync = false;
			if ( action.payload.supportSync !== undefined ) {
				newState.supportSync = action.payload.supportSync;
			}
			break;

		case ActionTypes.UPDATE_STATE:
			if ( action.payload.supportTicket !== undefined ) {
				newState.supportTicket = action.payload.supportTicket;
			}
			if ( action.payload.isSendingSystemData !== undefined ) {
				newState.isSendingSystemData =
					action.payload.isSendingSystemData;
			}
			break;

		default:
			return state;
	}

	return newState;
};

export const SupportProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( supportReducer, initialState );

	/**
	 * Update support ticket ID in state
	 *
	 * @param {string} val - Support ticket ID
	 */
	const updateSupportTicket = ( val ) => {
		dispatch( {
			type: ActionTypes.UPDATE_STATE,
			payload: {
				supportTicket: val,
			},
		} );
	};

	/**
	 * Send system data to support
	 *
	 * A successful share also starts the live sync session, which the
	 * response reports back through `supportSync`.
	 */
	const sendSystemData = async () => {
		dispatch( {
			type: ActionTypes.SEND_SUPPORT_DATA_BEFORE,
		} );

		try {
			const response = await apiSendSystemData( state.supportTicket );
			const data = response.data;
			const message =
				typeof data === 'string' ? data : data?.message || '';

			dispatch( {
				type: ActionTypes.SEND_SUPPORT_DATA,
				payload: {
					isSystemDataSent: response.success,
					systemDataMessage: message,
					supportSync:
						data && typeof data === 'object' && data.supportSync
							? data.supportSync
							: undefined,
				},
			} );
		} catch ( error ) {
			dispatch( {
				type: ActionTypes.SEND_SUPPORT_DATA,
				payload: {
					isSystemDataSent: false,
					systemDataMessage:
						error.message || 'Failed to send system data',
				},
			} );
		}
	};

	/**
	 * Stop the live support sync session
	 */
	const stopSupportSync = async () => {
		dispatch( {
			type: ActionTypes.STOP_SUPPORT_SYNC_BEFORE,
		} );

		try {
			const response = await apiStopSupportSync();

			dispatch( {
				type: ActionTypes.STOP_SUPPORT_SYNC,
				payload: {
					supportSync: response.success
						? response.data?.supportSync || { active: false }
						: state.supportSync,
				},
			} );
		} catch ( error ) {
			// The session may still be active server-side, so keep the
			// indicator visible; the customer can retry.
			dispatch( {
				type: ActionTypes.STOP_SUPPORT_SYNC,
				payload: {
					supportSync: state.supportSync,
				},
			} );
		}
	};

	const value = {
		// State
		supportTicket: state.supportTicket,
		isSendingSystemData: state.isSendingSystemData,
		isSystemDataSent: state.isSystemDataSent,
		systemDataMessage: state.systemDataMessage,
		supportSync: state.supportSync,
		isStoppingSupportSync: state.isStoppingSupportSync,

		// Methods
		updateSupportTicket,
		sendSystemData,
		stopSupportSync,

		// Backward compatibility aliases
		useSupportTicket: state.supportTicket,
		useIsSendingSystemData: state.isSendingSystemData,
		useIsSystemDataSent: state.isSystemDataSent,
		useSystemDataMessage: state.systemDataMessage,
	};

	return (
		<SupportContext.Provider value={ value }>
			{ children }
		</SupportContext.Provider>
	);
};

export default SupportProvider;
