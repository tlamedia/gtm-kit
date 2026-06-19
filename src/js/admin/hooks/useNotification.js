/**
 * Custom hook for notification management
 *
 * Simplifies working with notifications by providing convenient
 * methods and computed values.
 *
 * @return {Object} Notification state and methods
 *
 * @example
 * const {
 *   dismissNotification,
 *   removeNotification,
 *   totalNotifications,
 *   hasProblems
 * } = useNotification();
 *
 * {totalNotifications > 0 && (
 *   <Badge count={totalNotifications} important={hasProblems} />
 * )}
 *
 * <NotificationItem onDismiss={() => dismissNotification(id)} />
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { useContext, useCallback } from '@wordpress/element';
import { NotificationContext } from '../context/NotificationContext';

export const useNotification = () => {
	const { notifications, setNotificationStatus, isUpdatingNotifications } =
		useContext( NotificationContext );

	/**
	 * Dismiss a notification (mark as read, but keep visible)
	 *
	 * @param {string} id - Notification ID
	 * @return {Promise} Promise that resolves when dismissed
	 */
	const dismissNotification = useCallback(
		( id ) => {
			return setNotificationStatus( id, 'dismiss' );
		},
		[ setNotificationStatus ]
	);

	/**
	 * Remove a notification completely (hide it)
	 *
	 * @param {string} id - Notification ID
	 * @return {Promise} Promise that resolves when removed
	 */
	const removeNotification = useCallback(
		( id ) => {
			return setNotificationStatus( id, 'remove' );
		},
		[ setNotificationStatus ]
	);

	/**
	 * Restore a dismissed notification back to the active list
	 *
	 * @param {string} id - Notification ID
	 * @return {Promise} Promise that resolves when restored
	 */
	const restoreNotification = useCallback(
		( id ) => {
			return setNotificationStatus( id, 'restore' );
		},
		[ setNotificationStatus ]
	);

	/**
	 * Get total notification count
	 */
	const totalNotifications = notifications?.metrics?.total ?? 0;

	/**
	 * Get problem notification count
	 */
	const problemNotifications = notifications?.metrics?.problem ?? 0;

	/**
	 * Check if there are any problem notifications
	 */
	const hasProblems = problemNotifications > 0;

	/**
	 * Check if there are any notifications at all
	 */
	const hasNotifications = totalNotifications > 0;

	return {
		/**
		 * Raw notifications object
		 * @type {Object}
		 */
		notifications,

		/**
		 * Dismiss a notification
		 * @type {Function}
		 */
		dismissNotification,

		/**
		 * Remove a notification
		 * @type {Function}
		 */
		removeNotification,

		/**
		 * Restore a dismissed notification
		 * @type {Function}
		 */
		restoreNotification,

		/**
		 * Whether notifications are being updated
		 * @type {boolean}
		 */
		isUpdatingNotifications,

		/**
		 * Total notification count
		 * @type {number}
		 */
		totalNotifications,

		/**
		 * Problem notification count
		 * @type {number}
		 */
		problemNotifications,

		/**
		 * Whether there are problem notifications
		 * @type {boolean}
		 */
		hasProblems,

		/**
		 * Whether there are any notifications
		 * @type {boolean}
		 */
		hasNotifications,
	};
};

export default useNotification;
