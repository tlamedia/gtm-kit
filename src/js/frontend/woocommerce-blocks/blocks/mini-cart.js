/**
 * Mini Cart view_cart.
 *
 * The Cart block on /cart and the Checkout block on /checkout already
 * emit `view_cart` / `begin_checkout` from the PHP-rendered initial
 * dataLayer. The Mini Cart can live anywhere (typically a header
 * template part) and opens a drawer without navigation, so its
 * `view_cart` has no server-side trigger.
 *
 * The WC 10.x Mini Cart is an Interactivity API block and does not
 * register the `wc/store/cart` data store, so this module reads the cart
 * over the Store API (using the nonce localized on the bundle) when the
 * drawer opens and emits `view_cart` from the per-item
 * `extensions.gtmkit.item` payload, de-duplicated by cart signature so
 * re-opening an unchanged cart does not fire again.
 */

import { EVENTS } from '../constants';
import {
	pushEvent,
	getCurrency,
	parseItem,
	cartSignature,
	logError,
} from '../utils';

const MINI_CART_BUTTON = '.wc-block-mini-cart__button';

/**
 * Fetch the current cart from the Store API.
 *
 * @return {Promise<Object|null>} The cart response, or null on failure.
 */
const fetchCart = async () => {
	const build = window.gtmkitWooCommerceBlocksBuild;
	if ( ! build || ! build.root ) {
		return null;
	}

	const response = await fetch( `${ build.root }wc/store/v1/cart`, {
		credentials: 'include',
		headers: { 'X-WP-Nonce': build.nonce },
	} );

	if ( ! response.ok ) {
		return null;
	}
	return response.json();
};

/**
 * Build normalized view_cart items from a Store API cart response.
 *
 * @param {Object} cart The Store API cart.
 * @return {{items: Array<Object>, value: number, signature: string}} The view_cart payload parts.
 */
const buildPayload = ( cart ) => {
	const rawItems = Array.isArray( cart?.items ) ? cart.items : [];

	const items = rawItems.map( ( cartItem ) => {
		const item = parseItem( cartItem?.extensions?.gtmkit?.item );
		item.quantity = cartItem.quantity;
		return item;
	} );

	const signature = cartSignature(
		rawItems.map( ( i ) => ( { key: i.key, quantity: i.quantity } ) )
	);

	const total = Number( cart?.totals?.total_price ?? 0 );

	return {
		items,
		value: Number.isFinite( total ) ? total / 100 : 0,
		signature,
	};
};

/**
 * Mount the Mini Cart subscriber.
 *
 * @param {Object}   [deps]      Dependencies.
 * @param {Document} [deps.root] Event root (defaults to document).
 * @return {Function} A detach handle.
 */
export const createMiniCartSubscriber = ( { root = document } = {} ) => {
	let lastSignature = null;

	const onOpen = async () => {
		try {
			const cart = await fetchCart();
			if ( ! cart ) {
				return;
			}

			const { items, value, signature } = buildPayload( cart );
			if ( items.length === 0 ) {
				return;
			}
			if ( signature === lastSignature ) {
				return;
			}
			lastSignature = signature;

			pushEvent( EVENTS.VIEW_CART, {
				ecommerce: {
					currency: getCurrency(),
					value,
					items,
				},
			} );
		} catch ( e ) {
			logError( 'mini-cart', e );
		}
	};

	const handler = ( event ) => {
		const target = event.target;
		if ( target && target.closest && target.closest( MINI_CART_BUTTON ) ) {
			onOpen();
		}
	};

	root.addEventListener( 'click', handler, true );

	return () => root.removeEventListener( 'click', handler, true );
};
