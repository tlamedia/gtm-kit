/**
 * All Products block list tracking.
 *
 * The All Products block (`woocommerce/all-products`) is the legacy
 * client-rendered product grid: WooCommerce builds its items in the
 * browser from the Store API, so there is no server-rendered carrier to
 * read and no `wc/store/cart`-style state to diff for its list view.
 *
 * Its products are surfaced through the block's own render action,
 * `experimental__woocommerce_blocks-product-list-render` (and
 * `-product-view-link` for clicks), which still ships in WooCommerce
 * 10.3+. Each product carries `extensions.gtmkit.item`. These actions are
 * the documented, and only, integration point for this client-rendered
 * grid, so this module subscribes to them for `view_item_list` /
 * `select_item`. (Cart and checkout state is read from the stable data
 * stores elsewhere; this action is scoped to the legacy grid only.)
 *
 * `add_to_cart` from the All Products grid is not handled here: its
 * add-to-cart button drives the Store API cart, so the cart subscriber
 * already emits it.
 */

import { addAction } from '@wordpress/hooks';
import { EVENTS } from '../constants';
import { pushEvent, getProductImpressionObject, logError } from '../utils';

const NAMESPACE = 'gtmkit/woocommerce-blocks';
const ACTION_PREFIX = 'experimental__woocommerce_blocks';
// On a Cart/Checkout block page, list events (including cross-sells) are
// owned by the cross-sells subscriber reading the cart store, so the
// legacy render action must not also fire here.
const CART_CONTEXT =
	'.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout';

/**
 * Whether the current page is a Cart or Checkout block page.
 *
 * @return {boolean} True on a Cart or Checkout block page.
 */
const onCartOrCheckout = () => !! document.querySelector( CART_CONTEXT );

/**
 * A signature of the rendered product set, used to skip identical
 * re-renders while still re-firing when pagination or a filter changes
 * the set.
 *
 * @param {Array<Object>} items GA4 items.
 * @return {string} A join of the item ids.
 */
const signature = ( items ) =>
	items.map( ( i ) => `${ i.item_id ?? i.id ?? '' }` ).join( '|' );

/**
 * Subscribe to the All Products grid render and click actions.
 *
 * @return {void}
 */
export const initAllProducts = () => {
	const seen = {};

	addAction(
		`${ ACTION_PREFIX }-product-list-render`,
		NAMESPACE,
		( { products = [], listName = 'All products' } = {} ) => {
			try {
				if ( ! products.length || onCartOrCheckout() ) {
					return;
				}

				const items = products.map( ( product, index ) => ( {
					...getProductImpressionObject( product, listName ),
					index: index + 1,
				} ) );

				const sig = signature( items );
				if ( seen[ listName ] === sig ) {
					return;
				}
				seen[ listName ] = sig;

				pushEvent( EVENTS.VIEW_ITEM_LIST, { ecommerce: { items } } );
			} catch ( e ) {
				logError( 'all-products-list', e );
			}
		}
	);

	addAction(
		`${ ACTION_PREFIX }-product-view-link`,
		NAMESPACE,
		( { product, listName = '' } = {} ) => {
			try {
				if ( ! product || onCartOrCheckout() ) {
					return;
				}

				pushEvent( EVENTS.SELECT_ITEM, {
					ecommerce: {
						items: [
							getProductImpressionObject( product, listName ),
						],
					},
				} );
			} catch ( e ) {
				logError( 'all-products-select', e );
			}
		}
	);
};
