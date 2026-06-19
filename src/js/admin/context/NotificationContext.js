/**
 * NotificationContext - Notification Management
 *
 * Responsibilities:
 * - Notifications state
 * - Notification status updates (dismiss, mark as read, etc.)
 * - UI feedback (notices, errors)
 *
 * @typedef {import('./types').NotificationContextValue} NotificationContextValue
 *
 * @since Phase 1 Refactoring (2026-01-27)
 * @since Phase 2 Enhancement - Added TypeScript definitions (2026-01-27)
 */

/*WordPress*/
import { createContext, useReducer, useEffect } from '@wordpress/element';

/*Inbuilt APIs*/
import { sendNotificationStatus as apiSendNotificationStatus } from '../api/settings';

/*Services*/
import SettingsService from '../services/SettingsService';
import * as ActionTypes from '../constants/actionTypes';
import updateAdminMenuCounter from '../utils/updateAdminMenuCounter';

/**
 * @type {import('react').Context<NotificationContextValue>}
 */
export const NotificationContext = createContext();

const initialState = {
	notifications: { metrics: { total: 0, problem: 0 } },
	isUpdatingNotifications: false,
	previousNotifications: null, // For rollback on error
};

const notificationReducer = ( state, action ) => {
	const newState = { ...state };

	switch ( action.type ) {
		case ActionTypes.FETCH_SETTINGS:
			newState.notifications = action.payload.notifications;
			break;

		case ActionTypes.SEND_NOTIFICATION_STATUS_BEFORE:
			newState.isUpdatingNotifications = true;
			break;

		case ActionTypes.SEND_NOTIFICATION_STATUS:
			newState.isUpdatingNotifications = false;
			newState.notifications =
				action.payload.notifications || state.notifications;
			newState.previousNotifications = null; // Clear backup after successful update
			break;

		case ActionTypes.OPTIMISTIC_NOTIFICATION_UPDATE:
			// Store current state for potential rollback
			newState.previousNotifications = state.notifications;
			// Apply optimistic update immediately
			newState.notifications = action.payload.notifications;
			break;

		case ActionTypes.NOTIFICATION_UPDATE_ROLLBACK:
			// Rollback to previous state on error
			newState.notifications =
				state.previousNotifications || state.notifications;
			newState.previousNotifications = null;
			newState.isUpdatingNotifications = false;
			break;

		default:
			return state;
	}

	return newState;
};

export const NotificationProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( notificationReducer, initialState );

	/**
	 * Fetch notifications from service on mount
	 */
	const fetchNotifications = () => {
		const notifications = SettingsService.getNotifications();

		dispatch( {
			type: ActionTypes.FETCH_SETTINGS,
			payload: {
				notifications,
			},
		} );
	};

	/**
	 * Calculate optimistic notification update
	 *
	 * @param {Object} notifications - Current notifications state
	 * @param {string} id            - Notification ID
	 * @param {string} action        - Action to perform
	 * @return {Object} Updated notifications state
	 */
	const calculateOptimisticUpdate = ( notifications, id, action ) => {
		const updated = JSON.parse( JSON.stringify( notifications ) ); // Deep clone

		// Find which type contains this notification
		let notificationType = null;
		let notification = null;

		for ( const type in updated ) {
			if ( type === 'metrics' ) {
				continue;
			}

			if ( updated[ type ].active?.[ id ] ) {
				notificationType = type;
				notification = updated[ type ].active[ id ];
				break;
			}
		}

		if ( ! notificationType || ! notification ) {
			return updated; // Notification not found, return unchanged
		}

		// Apply action
		if ( action === 'dismiss' ) {
			// Move from active to dismissed
			delete updated[ notificationType ].active[ id ];
			updated[ notificationType ].dismissed[ id ] = notification;

			// Update total count
			if ( updated.metrics?.total ) {
				updated.metrics.total = Math.max(
					0,
					updated.metrics.total - 1
				);
			}
		} else if ( action === 'remove' ) {
			// Remove from dismissed
			delete updated[ notificationType ].dismissed[ id ];
		}

		return updated;
	};

	/**
	 * Set notification status (dismiss, mark as read, etc.)
	 *
	 * Implements optimistic UI updates for instant feedback.
	 *
	 * @param {string} id     - Notification ID
	 * @param {string} action - Action to perform ('dismiss', 'read', 'remove')
	 */
	const setNotificationStatus = async ( id, action ) => {
		// Calculate optimistic update
		const optimisticNotifications = calculateOptimisticUpdate(
			state.notifications,
			id,
			action
		);

		// Apply optimistic update immediately for instant UI feedback
		dispatch( {
			type: ActionTypes.OPTIMISTIC_NOTIFICATION_UPDATE,
			payload: {
				notifications: optimisticNotifications,
			},
		} );

		// Mark as updating
		dispatch( {
			type: ActionTypes.SEND_NOTIFICATION_STATUS_BEFORE,
		} );

		try {
			const data = {
				'notification-id': id,
				action,
			};

			const response = await apiSendNotificationStatus( data );

			// Confirm with server response
			dispatch( {
				type: ActionTypes.SEND_NOTIFICATION_STATUS,
				payload: {
					notifications: response.data,
				},
			} );
		} catch ( error ) {
			// Rollback optimistic update on error
			dispatch( {
				type: ActionTypes.NOTIFICATION_UPDATE_ROLLBACK,
			} );
		}
	};

	// Fetch notifications on mount
	useEffect( () => {
		fetchNotifications();
	}, [] );

	// Update WordPress admin menu counter when notification count changes
	useEffect( () => {
		if ( state.notifications?.metrics?.total !== undefined ) {
			updateAdminMenuCounter( state.notifications.metrics.total );
		}
	}, [ state.notifications?.metrics?.total ] );

	const value = {
		// State
		notifications: state.notifications,
		isUpdatingNotifications: state.isUpdatingNotifications,

		// Methods
		setNotificationStatus,

		// Backward compatibility aliases
		useNotifications: state.notifications,
		useIsUpdatingNotifications: state.isUpdatingNotifications,
	};

	return (
		<NotificationContext.Provider value={ value }>
			{ children }
		</NotificationContext.Provider>
	);
};

export default NotificationProvider;
