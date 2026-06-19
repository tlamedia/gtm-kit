/**
 * Update WordPress Admin Menu Counter
 *
 * This utility function updates the notification counter in the WordPress admin menu.
 *
 * WHY DOM MANIPULATION IS NECESSARY:
 * The WordPress admin menu exists outside of React's control (rendered by PHP).
 * We need to update the counter badge when notifications change within the React app.
 * This is a pragmatic approach for Phase 1 - a cleaner solution using WordPress
 * Heartbeat API or server-side updates could be implemented in Phase 2.
 *
 * @param {number} count - The notification count to display
 *
 * @example
 * updateAdminMenuCounter(5); // Sets counter to 5
 * updateAdminMenuCounter(0); // Sets counter to 0
 *
 * @since Phase 1 Refactoring (2026-01-27)
 */
export const updateAdminMenuCounter = ( count ) => {
	// Only run in browser context (not during SSR)
	if ( typeof document === 'undefined' ) {
		return;
	}

	// Find all GTM Kit menu counter elements
	const menuCounterElements = document.querySelectorAll(
		'li.toplevel_page_gtmkit_general span.menu-counter'
	);

	if ( ! menuCounterElements || menuCounterElements.length === 0 ) {
		return;
	}

	// Update each counter element
	menuCounterElements.forEach( ( counter ) => {
		const countSpan = counter.querySelector( 'span.count' );

		// Update the text content
		if ( countSpan ) {
			countSpan.textContent = count;
		}

		// Update the CSS class (WordPress uses count-N pattern)
		counter.className = counter.className.replace(
			/count-\d+/,
			`count-${ count }`
		);
	} );
};

export default updateAdminMenuCounter;
