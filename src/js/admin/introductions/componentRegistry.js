/**
 * Registry that maps an introduction id to the React component used to
 * render it when the intro's render mode is 'component'.
 *
 * Sibling plugins (gtm-kit-premium, gtm-kit-woo) register their own
 * components via the public window API exposed in `introductions.js`.
 * Unknown ids fall back to a generic placeholder.
 */

import UntitledIntroFallback from './components/UntitledIntroFallback.jsx';

const registry = new Map();

/**
 * Register a component for an intro id.
 *
 * @param {string}                id        Intro id.
 * @param {import('react').ComponentType} Component A React component.
 */
export function registerComponent( id, Component ) {
	if ( typeof id !== 'string' || id === '' ) {
		return;
	}
	if ( Component === null || Component === undefined ) {
		return;
	}
	if ( typeof Component !== 'function' && typeof Component !== 'object' ) {
		return;
	}
	registry.set( id, Component );
}

/**
 * Resolve a component for an intro id. Unknown ids resolve to the
 * fallback so the user still sees something rather than a blank modal.
 *
 * @param {string} id Intro id.
 * @return {import('react').ComponentType} A React component.
 */
export function resolveComponent( id ) {
	if ( registry.has( id ) ) {
		return registry.get( id );
	}
	return UntitledIntroFallback;
}

/**
 * Test affordance: clear the registry between runs.
 *
 * @internal
 */
export function _resetRegistryForTests() {
	registry.clear();
}
