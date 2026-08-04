/**
 * Click-emitted add registry.
 *
 * A block product button click and the cart-store subscriber can both
 * report the same add: the click handler emits immediately (it is the
 * only party that knows the list context, and on pages where the cart
 * store is absent or registers late it is the only party that fires at
 * all), and the subscriber later sees the same mutation in the cart
 * diff. This module lets the click handler mark the units it already
 * reported so the subscriber can subtract them instead of re-reporting.
 *
 * Entries accumulate per item, are claimed (removed) in one shot, and
 * expire after a TTL so an add that never reached the cart cannot
 * swallow an unrelated later add of the same product.
 */

// Generous on purpose: the diff can arrive long after the click on a slow
// site, and an expired entry recreates the duplicate this module exists to
// prevent. A lingering entry can at worst suppress one later add of the
// same product after an add that never reached the cart.
const TTL_MS = 300000;

/**
 * Units already emitted per item id.
 *
 * @type {Map<string, {quantity: number, recordedAt: number}>}
 */
const pending = new Map();

/**
 * Normalize an item id for keying.
 *
 * @param {*} itemId The item id.
 * @return {string} The key, or an empty string when unusable.
 */
const keyFor = ( itemId ) =>
	itemId === undefined || itemId === null ? '' : String( itemId );

/**
 * Record units of an item as already emitted by a click handler.
 *
 * @param {string|number} itemId   The GA4 item id.
 * @param {number}        quantity Units emitted.
 * @param {number}        [now]    Clock override for tests.
 */
export const recordClickAdd = ( itemId, quantity = 1, now = Date.now() ) => {
	const key = keyFor( itemId );
	if ( ! key || ! ( quantity > 0 ) ) {
		return;
	}

	const entry = pending.get( key );
	const carried =
		entry && now - entry.recordedAt <= TTL_MS ? entry.quantity : 0;

	pending.set( key, { quantity: carried + quantity, recordedAt: now } );
};

/**
 * Claim the units already emitted for an item, removing the entry.
 *
 * @param {string|number} itemId The GA4 item id.
 * @param {number}        [now]  Clock override for tests.
 * @return {number} Units a click handler already reported (0 when none).
 */
export const claimClickAdd = ( itemId, now = Date.now() ) => {
	const key = keyFor( itemId );
	const entry = pending.get( key );
	if ( ! entry ) {
		return 0;
	}

	pending.delete( key );

	if ( now - entry.recordedAt > TTL_MS ) {
		return 0;
	}

	return entry.quantity;
};

/**
 * Drop all pending entries. Test seam.
 */
export const clearClickAdds = () => {
	pending.clear();
};
