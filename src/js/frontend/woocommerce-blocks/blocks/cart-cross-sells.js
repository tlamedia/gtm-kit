/**
 * Cart block cross-sells ("You may be interested in…").
 *
 * The Cart block renders its cross-sell grid client-side with no
 * server carrier and no `cross-sell`-named container class, but the
 * products are present in the cart Store API response as
 * `getCartData().crossSells`, each carrying `permalink` and
 * `extensions.gtmkit.item`. This module therefore works from the store
 * data, not the DOM:
 *
 *   - `view_item_list` from the cross-sell products (de-duped by set),
 *   - `select_item` when a clicked link matches a cross-sell permalink.
 *
 * `add_to_cart` from a cross-sell flows through the Store API cart, so
 * the cart subscriber already emits it.
 */

import { CART_STORE, EVENTS } from '../constants';
import {
	pushEvent,
	getProductImpressionObject,
	microtaskQueue,
	logError,
} from '../utils';

const LIST_NAME = 'Cross-sells';

/**
 * Mount the cross-sells subscriber.
 *
 * @param {Object}           deps           Dependencies.
 * @param {Function}         deps.select    `wp.data.select`.
 * @param {Function}         deps.subscribe `wp.data.subscribe`.
 * @param {Document|Element} [deps.root]    Click root (defaults to document).
 * @return {Function} A detach handle.
 */
export const createCrossSellsSubscriber = ( {
	select,
	subscribe,
	root = document,
} ) => {
	let lastSignature = null;
	let crossSells = [];

	const read = () => {
		const store = select( CART_STORE );
		if ( ! store || typeof store.getCartData !== 'function' ) {
			return [];
		}
		const data = store.getCartData();
		return Array.isArray( data && data.crossSells ) ? data.crossSells : [];
	};

	const handle = () => {
		try {
			crossSells = read();
			if ( crossSells.length === 0 ) {
				return;
			}

			const items = crossSells.map( ( product, index ) => ( {
				...getProductImpressionObject( product, LIST_NAME ),
				index: index + 1,
			} ) );

			const signature = items
				.map( ( i ) => `${ i.item_id ?? i.id ?? '' }` )
				.join( '|' );
			if ( signature === lastSignature ) {
				return;
			}
			lastSignature = signature;

			pushEvent( EVENTS.VIEW_ITEM_LIST, { ecommerce: { items } } );
		} catch ( e ) {
			logError( 'cart-cross-sells', e );
		}
	};

	const unsubscribe = subscribe( microtaskQueue( handle ) );
	// Run once in case the cart data has already resolved.
	handle();

	const onClick = ( event ) => {
		try {
			const anchor = event.target.closest
				? event.target.closest( 'a[href]' )
				: null;
			if ( ! anchor ) {
				return;
			}

			const href = anchor.getAttribute( 'href' );
			if ( ! href ) {
				return;
			}

			const match = crossSells.find(
				( product ) => product.permalink && product.permalink === href
			);
			if ( ! match ) {
				return;
			}

			pushEvent( EVENTS.SELECT_ITEM, {
				ecommerce: {
					items: [ getProductImpressionObject( match, LIST_NAME ) ],
				},
			} );
		} catch ( e ) {
			logError( 'cart-cross-sells-select', e );
		}
	};

	root.addEventListener( 'click', onClick, true );

	return () => {
		if ( typeof unsubscribe === 'function' ) {
			unsubscribe();
		}
		root.removeEventListener( 'click', onClick, true );
	};
};
