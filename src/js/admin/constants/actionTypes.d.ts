/**
 * TypeScript definitions for action type constants
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

export declare const FETCH_SETTINGS: 'FETCH_SETTINGS';
export declare const UPDATE_SETTINGS_BEFORE: 'UPDATE_SETTINGS_BEFORE';
export declare const UPDATE_SETTINGS: 'UPDATE_SETTINGS';
export declare const UPDATE_STATE: 'UPDATE_STATE';
export declare const SEND_SUPPORT_DATA_BEFORE: 'SEND_SUPPORT_DATA_BEFORE';
export declare const SEND_SUPPORT_DATA: 'SEND_SUPPORT_DATA';
export declare const SEND_LICENSE_KEY_BEFORE: 'SEND_LICENSE_KEY_BEFORE';
export declare const SEND_LICENSE_KEY: 'SEND_LICENSE_KEY';
export declare const SEND_NOTIFICATION_STATUS_BEFORE: 'SEND_NOTIFICATION_STATUS_BEFORE';
export declare const SEND_NOTIFICATION_STATUS: 'SEND_NOTIFICATION_STATUS';

export type ActionType =
	| typeof FETCH_SETTINGS
	| typeof UPDATE_SETTINGS_BEFORE
	| typeof UPDATE_SETTINGS
	| typeof UPDATE_STATE
	| typeof SEND_SUPPORT_DATA_BEFORE
	| typeof SEND_SUPPORT_DATA
	| typeof SEND_LICENSE_KEY_BEFORE
	| typeof SEND_LICENSE_KEY
	| typeof SEND_NOTIFICATION_STATUS_BEFORE
	| typeof SEND_NOTIFICATION_STATUS;
