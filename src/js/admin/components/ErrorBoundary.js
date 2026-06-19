/**
 * Error Boundary Component
 *
 * Catches React errors and displays fallback UI instead of crashing
 * the entire application. Provides better UX during errors.
 *
 * @example
 * <ErrorBoundary>
 *   <MyComponent />
 * </ErrorBoundary>
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = {
			hasError: false,
			error: null,
			errorInfo: null,
		};
	}

	// eslint-disable-next-line no-unused-vars
	static getDerivedStateFromError( error ) {
		// Update state so the next render will show the fallback UI
		return { hasError: true };
	}

	componentDidCatch( error, errorInfo ) {
		// Log error details to state
		this.setState( {
			error,
			errorInfo,
		} );

		// Log error to console in development
		if ( process.env.NODE_ENV === 'development' ) {
			// eslint-disable-next-line no-console
			console.error( 'ErrorBoundary caught error:', error, errorInfo );
		}

		// TODO: Send to error reporting service in production
		// this.logErrorToService(error, errorInfo);
	}

	handleReset = () => {
		this.setState( {
			hasError: false,
			error: null,
			errorInfo: null,
		} );

		// Optionally reload the page
		if ( this.props.reloadOnReset ) {
			window.location.reload();
		}
	};

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="gtmkit-error-boundary gtmkit-p-8 gtmkit-bg-red-50 gtmkit-border gtmkit-border-red-200 gtmkit-rounded-lg gtmkit-max-w-4xl gtmkit-mx-auto gtmkit-my-8">
					<h2 className="gtmkit-text-xl gtmkit-font-bold gtmkit-text-red-600 gtmkit-mb-4">
						{ this.props.title ||
							__( 'Something went wrong', 'gtm-kit' ) }
					</h2>

					<p className="gtmkit-mb-4 gtmkit-text-gray-700">
						{ this.props.message ||
							__(
								'An unexpected error occurred. Please try refreshing the page.',
								'gtm-kit'
							) }
					</p>

					{ process.env.NODE_ENV === 'development' &&
						this.state.error && (
							<details className="gtmkit-mb-4 gtmkit-bg-white gtmkit-p-4 gtmkit-rounded gtmkit-border gtmkit-border-gray-300">
								<summary className="gtmkit-cursor-pointer gtmkit-font-semibold gtmkit-text-gray-800">
									{ __(
										'Error Details (Development Only)',
										'gtm-kit'
									) }
								</summary>
								<pre className="gtmkit-mt-2 gtmkit-text-xs gtmkit-overflow-auto gtmkit-max-h-96 gtmkit-p-2 gtmkit-bg-gray-50 gtmkit-rounded">
									{ this.state.error.toString() }
									{ '\n\n' }
									{ this.state.errorInfo?.componentStack }
								</pre>
							</details>
						) }

					<div className="gtmkit-flex gtmkit-gap-4">
						<button
							onClick={ this.handleReset }
							className="gtmkit-px-4 gtmkit-py-2 gtmkit-bg-red-600 gtmkit-text-white gtmkit-rounded hover:gtmkit-bg-red-700 gtmkit-transition-colors"
						>
							{ __( 'Try Again', 'gtm-kit' ) }
						</button>

						<button
							onClick={ () => window.location.reload() }
							className="gtmkit-px-4 gtmkit-py-2 gtmkit-bg-gray-600 gtmkit-text-white gtmkit-rounded hover:gtmkit-bg-gray-700 gtmkit-transition-colors"
						>
							{ __( 'Reload Page', 'gtm-kit' ) }
						</button>
					</div>
				</div>
			);
		}

		return this.props.children;
	}
}

export default ErrorBoundary;
