/**
 * ToastContext - Toast Notification Management
 *
 * Provides global toast notifications for progress indicators,
 * success messages, and error feedback.
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

import { createContext, useState, useCallback } from '@wordpress/element';

export const ToastContext = createContext();

let nextToastId = 1;

/**
 * Toast Provider Component
 *
 * @param {Object} props          Component props
 * @param {*}      props.children Child components
 * @return {JSX.Element} Provider component
 */
export const ToastProvider = ( { children } ) => {
	const [ toasts, setToasts ] = useState( [] );

	/**
	 * Remove a toast notification
	 *
	 * @param {number} id Toast ID
	 */
	const removeToast = useCallback( ( id ) => {
		setToasts( ( prev ) => prev.filter( ( toast ) => toast.id !== id ) );
	}, [] );

	/**
	 * Add a toast notification
	 *
	 * @param {string} message  Toast message
	 * @param {string} type     Toast type ('info', 'success', 'error', 'loading')
	 * @param {number} duration Auto-close duration in ms (0 = no auto-close)
	 * @return {number} Toast ID
	 */
	const addToast = useCallback(
		( message, type = 'info', duration = 3000 ) => {
			const id = nextToastId++;
			const toast = { id, message, type, duration };

			setToasts( ( prev ) => [ ...prev, toast ] );

			// Auto-remove after duration
			if ( duration > 0 ) {
				setTimeout( () => {
					removeToast( id );
				}, duration );
			}

			return id;
		},
		[ removeToast ]
	);

	/**
	 * Show success toast
	 *
	 * @param {string} message  Success message
	 * @param {number} duration Auto-close duration
	 * @return {number} Toast ID
	 */
	const success = useCallback(
		( message, duration = 3000 ) => {
			return addToast( message, 'success', duration );
		},
		[ addToast ]
	);

	/**
	 * Show error toast
	 *
	 * @param {string} message  Error message
	 * @param {number} duration Auto-close duration
	 * @return {number} Toast ID
	 */
	const error = useCallback(
		( message, duration = 5000 ) => {
			return addToast( message, 'error', duration );
		},
		[ addToast ]
	);

	/**
	 * Show loading toast
	 *
	 * @param {string} message Loading message
	 * @return {number} Toast ID (should be removed manually)
	 */
	const loading = useCallback(
		( message ) => {
			return addToast( message, 'loading', 0 ); // No auto-close
		},
		[ addToast ]
	);

	/**
	 * Show info toast
	 *
	 * @param {string} message  Info message
	 * @param {number} duration Auto-close duration
	 * @return {number} Toast ID
	 */
	const info = useCallback(
		( message, duration = 3000 ) => {
			return addToast( message, 'info', duration );
		},
		[ addToast ]
	);

	const value = {
		toasts,
		addToast,
		removeToast,
		success,
		error,
		loading,
		info,
	};

	return (
		<ToastContext.Provider value={ value }>
			{ children }
		</ToastContext.Provider>
	);
};

export default ToastProvider;
