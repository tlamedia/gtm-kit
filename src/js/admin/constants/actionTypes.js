/**
 * Redux-style action type constants for settings reducer
 *
 * These constants replace magic strings throughout the reducer and context,
 * providing type safety and making refactoring easier.
 *
 * @since Phase 1 Refactoring (2026-01-27)
 */

// Settings actions
export const FETCH_SETTINGS = 'FETCH_SETTINGS';
export const UPDATE_SETTINGS_BEFORE = 'UPDATE_SETTINGS_BEFORE';
export const UPDATE_SETTINGS = 'UPDATE_SETTINGS';
export const UPDATE_STATE = 'UPDATE_STATE';

// Support data actions
export const SEND_SUPPORT_DATA_BEFORE = 'SEND_SUPPORT_DATA_BEFORE';
export const SEND_SUPPORT_DATA = 'SEND_SUPPORT_DATA';

// License actions
export const SEND_LICENSE_KEY_BEFORE = 'SEND_LICENSE_KEY_BEFORE';
export const SEND_LICENSE_KEY = 'SEND_LICENSE_KEY';

// Notification actions
export const SEND_NOTIFICATION_STATUS_BEFORE =
	'SEND_NOTIFICATION_STATUS_BEFORE';
export const SEND_NOTIFICATION_STATUS = 'SEND_NOTIFICATION_STATUS';
export const OPTIMISTIC_NOTIFICATION_UPDATE = 'OPTIMISTIC_NOTIFICATION_UPDATE';
export const NOTIFICATION_UPDATE_ROLLBACK = 'NOTIFICATION_UPDATE_ROLLBACK';
