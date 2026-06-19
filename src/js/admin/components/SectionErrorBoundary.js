/**
 * Section Error Boundary
 *
 * Lighter weight error boundary for individual sections.
 * Allows rest of page to function if one section fails.
 *
 * @example
 * <SectionErrorBoundary sectionName="WooCommerce Settings">
 *   <WooCommerceSettings />
 * </SectionErrorBoundary>
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import ErrorBoundary from './ErrorBoundary';
import { __ } from '@wordpress/i18n';

const SectionErrorBoundary = ( { children, sectionName } ) => {
	const title = sectionName
		? `${ sectionName } - ${ __( 'Section Error', 'gtm-kit' ) }`
		: __( 'Section Error', 'gtm-kit' );

	const message = sectionName
		? `${ __( 'The', 'gtm-kit' ) } "${ sectionName }" ${ __(
				'section encountered an error. Other sections may still work normally.',
				'gtm-kit'
		  ) }`
		: __(
				'This section encountered an error. Other sections may still work normally.',
				'gtm-kit'
		  );

	return (
		<ErrorBoundary title={ title } message={ message }>
			{ children }
		</ErrorBoundary>
	);
};

export default SectionErrorBoundary;
