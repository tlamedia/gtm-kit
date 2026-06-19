/**
 * SupportContext - Support Ticket Management
 *
 * Responsibilities:
 * - Support ticket ID management
 * - System data sending to support
 *
 * @typedef {import('./types').SupportContextValue} SupportContextValue
 *
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */

/*WordPress*/
import { createContext, useReducer } from '@wordpress/element';

/*Inbuilt APIs*/
import { sendSystemData as apiSendSystemData } from '../api/settings';

/*Services*/
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
	 */
	const sendSystemData = async () => {
		dispatch( {
			type: ActionTypes.SEND_SUPPORT_DATA_BEFORE,
		} );

		try {
			const response = await apiSendSystemData( state.supportTicket );

			dispatch( {
				type: ActionTypes.SEND_SUPPORT_DATA,
				payload: {
					isSystemDataSent: response.success,
					systemDataMessage: response.data,
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

	const value = {
		// State
		supportTicket: state.supportTicket,
		isSendingSystemData: state.isSendingSystemData,
		isSystemDataSent: state.isSystemDataSent,
		systemDataMessage: state.systemDataMessage,

		// Methods
		updateSupportTicket,
		sendSystemData,

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
