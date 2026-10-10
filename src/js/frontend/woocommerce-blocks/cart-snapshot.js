/**
 * Shared "last reported cart" snapshot.
 *
 * The cart subscriber reports the delta between two cart snapshots. Other
 * code can report Store API cart changes as well, for example an add-on
 * that is handed a Store API response by a theme that calls the API
 * directly. Those paths must agree on what has already been reported, so
 * the snapshot lives in one place: whichever path sees a change first
 * reports it and records the result, and the other then sees no delta.
 *
 * The handle is installed on `window.gtmkit.wc.cartSnapshot`, so an add-on
 * can share it without importing this bundle. Whichever script installs
 * the handle first owns the storage; a later installer adopts it.
 */

let snapshot = null;

/**
 * The last reported cart, as normalized cart items, or null before any
 * cart has been seen.
 *
 * @return {Array<Object>|null} The snapshot.
 */
export const getCartSnapshot = () => snapshot;

/**
 * Record the cart that has just been reported (or baselined).
 *
 * @param {Array<Object>|null} items Normalized cart items.
 */
export const setCartSnapshot = ( items ) => {
	snapshot = Array.isArray( items ) ? items : null;
};

/**
 * Forget the snapshot. Test seam.
 */
export const resetCartSnapshot = () => {
	snapshot = null;
};

/**
 * Install the shared handle on the gtmkit global, adopting one that is
 * already there.
 *
 * @param {Object} [target] The global to install on (defaults to window).
 * @return {{get: Function, set: Function}} The shared handle.
 */
export const installCartSnapshot = ( target = window ) => {
	target.gtmkit = target.gtmkit || {};
	target.gtmkit.wc = target.gtmkit.wc || {};

	const existing = target.gtmkit.wc.cartSnapshot;
	if (
		existing &&
		typeof existing.get === 'function' &&
		typeof existing.set === 'function'
	) {
		return existing;
	}

	target.gtmkit.wc.cartSnapshot = {
		get: getCartSnapshot,
		set: setCartSnapshot,
	};

	return target.gtmkit.wc.cartSnapshot;
};
