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
 */

import { CART_STORE } from '../constants';
import { diffCartItems } from '../diff/cart-items-diff';
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
 * @param {Array<{item: Object, quantity: number}>} entries Diff entries.
 * @param {string}                                  event   GA4 event name.
 */
const emit = ( entries, event ) => {
	for ( const entry of entries ) {
		const value = Number( entry.item.price ?? 0 ) * entry.quantity;
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
 * Create and mount the cart subscriber.
 *
 * @param {Object}   deps           Injected wp.data accessors (for testability).
 * @param {Function} deps.select    `wp.data.select`.
 * @param {Function} deps.subscribe `wp.data.subscribe`.
 * @return {Function|undefined} The unsubscribe handle, or undefined when the store is absent.
 */
export const createCartSubscriber = ( { select, subscribe } ) => {
	let previousItems = null;

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

			if ( previousItems === null ) {
				previousItems = nextItems;
				return;
			}

			const { added, removed } = diffCartItems(
				previousItems,
				nextItems
			);
			previousItems = nextItems;

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
