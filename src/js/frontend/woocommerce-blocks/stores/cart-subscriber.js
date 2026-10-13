/**
 * Cart store subscriber.
 *
 * Watches the `wc/store/cart` data store and emits `add_to_cart` /
 * `remove_from_cart` from the net delta between cart snapshots. This is
 * the single source of truth for cart mutations driven by block UIs
 * (Mini Cart, Cart block quantity steppers, Product Collection
 * add-to-cart buttons) which all flow through the Store API rather than
 * the legacy AJAX path the classic script listens to.
 *
 * The first resolved snapshot establishes a baseline and emits nothing,
 * so items already in the cart on page load are not reported as adds.
 *
 * The "last reported cart" lives in the shared snapshot (see
 * cart-snapshot.js) rather than in this module, so a change another path
 * has already reported against that snapshot shows no delta here.
 *
 * The store can lag behind that snapshot: a theme that edits the cart
 * through the Store API directly hands the response to the other path and
 * leaves the store as it was. The store's cart is therefore compared with
 * the snapshot only when the store's own cart has changed since it was
 * last read, so a lagging store never reports the difference as a change.
 * A read that changes no quantity still carries its item data, such as a
 * new price, into the snapshot.
 */

import { CART_STORE } from '../constants';
import { diffCartItems } from '../diff/cart-items-diff';
import { claimClickAdd } from '../click-add-registry';
import { installCartSnapshot } from '../cart-snapshot';
import {
	pushEvent,
	getCurrency,
	normalizeCartItems,
	microtaskQueue,
	logError,
} from '../utils';

/**
 * Emit one ecommerce event per diff entry.
 *
 * Adds subtract any units a block UI's click handler already reported
 * for the item (see click-add-registry.js): a block product button's
 * add reaches the cart diff too, and re-reporting it here would double
 * the event. Units the click handler did not report (quantity steppers,
 * cross-sells, a concurrent add from another surface) still emit.
 *
 * @param {Array<{item: Object, quantity: number}>} entries Diff entries.
 * @param {string}                                  event   GA4 event name.
 */
const emit = ( entries, event ) => {
	for ( const entry of entries ) {
		let quantity = entry.quantity;

		if ( event === 'add_to_cart' ) {
			const alreadyReported = claimClickAdd(
				entry.item.item_id ?? entry.item.id,
				quantity
			);
			quantity -= alreadyReported;
			if ( quantity <= 0 ) {
				continue;
			}
			entry.item.quantity = quantity;
		}

		const value = Number( entry.item.price ?? 0 ) * quantity;
		pushEvent( event, {
			ecommerce: {
				currency: getCurrency(),
				value,
				items: [ entry.item ],
			},
		} );
	}
};

/**
 * The fields of a cart line's item that differ between two store reads.
 *
 * @param {Object} lastItem The item from the previous store read.
 * @param {Object} nextItem The item from the current store read.
 * @return {Object<string, *>} Changed fields with their new values; a field
 *     the store dropped maps to undefined.
 */
const changedFields = ( lastItem, nextItem ) => {
	const fields = {};
	const names = new Set( [
		...Object.keys( lastItem ),
		...Object.keys( nextItem ),
	] );

	for ( const name of names ) {
		if (
			JSON.stringify( lastItem[ name ] ) !==
			JSON.stringify( nextItem[ name ] )
		) {
			fields[ name ] = nextItem[ name ];
		}
	}

	return fields;
};

/**
 * Carry the item data the store changed since its last read into the shared
 * snapshot, field by field, keeping everything else the snapshot holds.
 *
 * A full removal is reported with the item data the snapshot holds, so a
 * price change must reach it even when no quantity changed. Only the fields
 * the store itself changed between two reads are written: another path may
 * have recorded a newer cart the store has not received yet, with a newer
 * quantity or price, and a lagging store must not overwrite what it did not
 * change.
 *
 * @param {Object}        snapshot  Shared snapshot handle.
 * @param {Array<Object>} lastItems Normalized cart items from the previous store read.
 * @param {Array<Object>} nextItems Normalized cart items from the store.
 */
const refreshItemData = ( snapshot, lastItems, nextItems ) => {
	const previousItems = snapshot.get();
	if ( previousItems === null ) {
		return;
	}

	const lastByKey = {};
	for ( const entry of lastItems ) {
		lastByKey[ entry.key ] = entry;
	}

	const changes = {};
	for ( const entry of nextItems ) {
		const last = lastByKey[ entry.key ];
		if ( ! last ) {
			continue;
		}

		const fields = changedFields( last.item, entry.item );
		const priceChanged = last.unitPrice !== entry.unitPrice;
		if ( priceChanged || Object.keys( fields ).length > 0 ) {
			changes[ entry.key ] = {
				fields,
				priceChanged,
				unitPrice: entry.unitPrice,
			};
		}
	}

	if ( ! previousItems.some( ( entry ) => changes[ entry.key ] ) ) {
		return;
	}

	snapshot.set(
		previousItems.map( ( entry ) => {
			const change = changes[ entry.key ];
			if ( ! change ) {
				return entry;
			}

			const item = { ...entry.item };
			for ( const [ name, value ] of Object.entries( change.fields ) ) {
				if ( value === undefined ) {
					delete item[ name ];
				} else {
					item[ name ] = value;
				}
			}

			return {
				...entry,
				unitPrice: change.priceChanged
					? change.unitPrice
					: entry.unitPrice,
				item,
			};
		} )
	);
};

/**
 * Create and mount the cart subscriber.
 *
 * @param {Object}   deps            Injected wp.data accessors (for testability).
 * @param {Function} deps.select     `wp.data.select`.
 * @param {Function} deps.subscribe  `wp.data.subscribe`.
 * @param {Object}   [deps.snapshot] Shared snapshot handle (defaults to the installed one).
 * @return {Function|undefined} The unsubscribe handle, or undefined when the store is absent.
 */
export const createCartSubscriber = ( {
	select,
	subscribe,
	snapshot = installCartSnapshot(),
} ) => {
	let lastStoreItems = null;

	const readItems = () => {
		const store = select( CART_STORE );
		if ( ! store || typeof store.getCartData !== 'function' ) {
			return null;
		}

		// Only act on resolved cart data so we don't baseline against an
		// empty placeholder and then report the real contents as adds.
		if (
			typeof store.hasFinishedResolution === 'function' &&
			! store.hasFinishedResolution( 'getCartData' )
		) {
			return null;
		}

		const data = store.getCartData();
		if ( ! data || ! Array.isArray( data.items ) ) {
			return null;
		}

		return normalizeCartItems( data );
	};

	const handle = () => {
		try {
			const nextItems = readItems();
			if ( nextItems === null ) {
				return;
			}

			if ( lastStoreItems !== null ) {
				const own = diffCartItems( lastStoreItems, nextItems );
				if ( ! own.added.length && ! own.removed.length ) {
					refreshItemData( snapshot, lastStoreItems, nextItems );
					lastStoreItems = nextItems;
					return;
				}
			}
			lastStoreItems = nextItems;

			const previousItems = snapshot.get();
			if ( previousItems === null ) {
				snapshot.set( nextItems );
				return;
			}

			const { added, removed } = diffCartItems(
				previousItems,
				nextItems
			);
			snapshot.set( nextItems );

			emit( added, 'add_to_cart' );
			emit( removed, 'remove_from_cart' );
		} catch ( e ) {
			logError( 'cart-subscriber', e );
		}
	};

	// Subscribe globally rather than scoped to the cart store: on a page
	// where the Cart or Checkout block registers the store lazily, a
	// store-scoped subscription can miss the registration. The global
	// subscription fires on any store change (including the cart store's
	// first appearance) and readItems() guards when the store is absent.
	return subscribe( microtaskQueue( handle ) );
};
