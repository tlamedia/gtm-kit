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
 * Claim up to `units` of the units already emitted for an item.
 *
 * Only the units the caller can account for are taken; any surplus stays
 * recorded for a later claim. The cart confirms a burst of clicks one diff
 * at a time as often as it confirms them together, and a claim that took
 * the whole entry would leave the following diffs with nothing to subtract
 * and re-report the add.
 *
 * @param {string|number} itemId  The GA4 item id.
 * @param {number}        [units] Units being confirmed; defaults to all recorded.
 * @param {number}        [now]   Clock override for tests.
 * @return {number} Units a click handler already reported (0 when none).
 */
export const claimClickAdd = (
	itemId,
	units = Number.POSITIVE_INFINITY,
	now = Date.now()
) => {
	const key = keyFor( itemId );
	const entry = pending.get( key );
	if ( ! entry ) {
		return 0;
	}

	if ( now - entry.recordedAt > TTL_MS ) {
		pending.delete( key );
		return 0;
	}

	const claimed = Math.min( entry.quantity, Math.max( 0, units ) );
	const remaining = entry.quantity - claimed;

	if ( remaining > 0 ) {
		// Keep the original timestamp: the surplus expires on the clock of
		// the click that recorded it, not of the claim that left it behind.
		pending.set( key, {
			quantity: remaining,
			recordedAt: entry.recordedAt,
		} );
	} else {
		pending.delete( key );
	}

	return claimed;
};

/**
 * Drop all pending entries. Test seam.
 */
export const clearClickAdds = () => {
	pending.clear();
};
