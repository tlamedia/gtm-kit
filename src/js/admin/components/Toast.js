/**
 * Toast Notification Component
 *
 * Provides temporary, non-intrusive feedback for user actions.
 * Used for progress indicators and success/error messages.
 *
 * @since Phase 3 Optimization (2026-01-27)
 */

import { memo } from '@wordpress/element';
import { Icon, check, info, warning, closeSmall } from '@wordpress/icons';
import { Spinner } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Toast notification component
 *
 * @param {Object}   props          Component props
 * @param {string}   props.message  Message to display
 * @param {string}   props.type     Type of toast ('info', 'success', 'error', 'loading')
 * @param {Function} props.onClose  Optional close handler
 * @param {number}   props.duration Auto-close duration in ms (0 = no auto-close)
 * @return {JSX.Element} Toast component
 */
export const Toast = memo(
	( { message, type = 'info', onClose = null, duration = 0 } ) => {
		// Auto-close after duration
		if ( duration > 0 && onClose ) {
			setTimeout( onClose, duration );
		}

		const getIcon = () => {
			switch ( type ) {
				case 'success':
					return <Icon icon={ check } />;
				case 'error':
					return <Icon icon={ warning } />;
				case 'loading':
					return <Spinner />;
				default:
					return <Icon icon={ info } />;
			}
		};

		const baseClasses =
			'gtmkit-fixed gtmkit-bottom-8 gtmkit-right-8 gtmkit-z-50 gtmkit-flex gtmkit-items-center gtmkit-gap-3 gtmkit-px-4 gtmkit-py-3 gtmkit-rounded-lg gtmkit-shadow-lg gtmkit-min-w-[300px] gtmkit-max-w-md gtmkit-animate-slide-up';

		const typeClasses = {
			info: 'gtmkit-bg-blue-50 gtmkit-text-blue-900 gtmkit-border gtmkit-border-blue-200',
			success:
				'gtmkit-bg-green-50 gtmkit-text-green-900 gtmkit-border gtmkit-border-green-200',
			error: 'gtmkit-bg-red-50 gtmkit-text-red-900 gtmkit-border gtmkit-border-red-200',
			loading:
				'gtmkit-bg-gray-50 gtmkit-text-gray-900 gtmkit-border gtmkit-border-gray-200',
		};

		return (
			<div className={ classNames( baseClasses, typeClasses[ type ] ) }>
				<div className="gtmkit-flex-shrink-0">{ getIcon() }</div>
				<div className="gtmkit-flex-grow gtmkit-text-sm gtmkit-font-medium">
					{ message }
				</div>
				{ onClose && (
					<button
						onClick={ onClose }
						className="gtmkit-flex-shrink-0 gtmkit-p-1 gtmkit-rounded gtmkit-hover:bg-black gtmkit-hover:bg-opacity-10 gtmkit-transition-colors"
						aria-label="Close"
					>
						<Icon icon={ closeSmall } size={ 20 } />
					</button>
				) }
			</div>
		);
	}
);

Toast.displayName = 'Toast';

/**
 * Toast container component
 *
 * Manages multiple toast notifications with stacking.
 *
 * @param {Object} props        Component props
 * @param {Array}  props.toasts Array of toast objects
 * @return {JSX.Element} Toast container
 */
export const ToastContainer = memo( ( { toasts = [] } ) => {
	if ( ! toasts.length ) {
		return null;
	}

	return (
		<div className="gtmkit-fixed gtmkit-bottom-0 gtmkit-right-0 gtmkit-p-8 gtmkit-z-50 gtmkit-pointer-events-none">
			<div className="gtmkit-space-y-2">
				{ toasts.map( ( toast ) => (
					<div
						key={ toast.id }
						className="gtmkit-pointer-events-auto"
					>
						<Toast { ...toast } />
					</div>
				) ) }
			</div>
		</div>
	);
} );

ToastContainer.displayName = 'ToastContainer';

export default Toast;
