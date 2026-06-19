/**
 * Toast Notification Component Type Definitions
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

/**
 * Toast type
 */
export type ToastType = 'info' | 'success' | 'error' | 'loading';

/**
 * Toast props
 */
export interface ToastProps {
	/** Message to display */
	message: string;
	/** Type of toast */
	type?: ToastType;
	/** Optional close handler */
	onClose?: () => void;
	/** Auto-close duration in ms (0 = no auto-close) */
	duration?: number;
}

/**
 * Toast object for container
 */
export interface ToastObject extends ToastProps {
	/** Unique ID */
	id: string | number;
}

/**
 * Toast container props
 */
export interface ToastContainerProps {
	/** Array of toast objects */
	toasts?: ToastObject[];
}

/**
 * Toast component
 *
 * Provides temporary, non-intrusive feedback for user actions.
 *
 * @example
 * <Toast
 *   message="Settings saved successfully"
 *   type="success"
 *   duration={3000}
 * />
 */
export const Toast: React.FC< ToastProps >;

/**
 * Toast container component
 *
 * Manages multiple toast notifications with stacking.
 *
 * @example
 * <ToastContainer toasts={[
 *   { id: 1, message: "Saving...", type: "loading" },
 *   { id: 2, message: "Saved!", type: "success" }
 * ]} />
 */
export const ToastContainer: React.FC< ToastContainerProps >;

export default Toast;
