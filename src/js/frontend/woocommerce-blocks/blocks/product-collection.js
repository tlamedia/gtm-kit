/**
 * Product Collection list tracking.
 *
 * Product Collection is server-rendered via the Interactivity API and
 * does not fire the legacy product-grid hooks the classic script relies
 * on, so the block bundle owns its list events end to end. PHP injects a
 * `.gtmkit_block_product_data` span into each product item (a carrier
 * distinct from the classic `.gtmkit_product_data` so the two paths never
 * double-fire). This module:
 *
 *   - emits one `view_item_list` per collection on first render,
 *   - re-emits when a filter or pagination control re-renders the
 *     collection client-side (de-duplicated by product signature so an
 *     unchanged re-render is silent),
 *   - emits `select_item` on clicks of a product within a collection.
 */

import { EVENTS } from '../constants';
import { pushEvent, parseItem, getCurrency, logError } from '../utils';

const BLOCK_PRODUCT_DATA = '.gtmkit_block_product_data';
const PRODUCT_ANCHOR = 'a';
const ADD_TO_CART_BUTTON =
	'.wc-block-components-product-button__button, .add_to_cart_button';
// Links that are not product links: the post-add "View cart" forward link.
const NON_PRODUCT_LINK = '.added_to_cart, .wc-forward, .wc_forward';
// Newer WooCommerce renders the Cart/Checkout cross-sells as a Product
// Collection nested in the Cart block. Those are owned by the cross-sells
// subscriber (which reads the cart store's crossSells), so skip any
// collection inside a cart/checkout block to avoid a double view_item_list.
const CART_CONTEXT =
	'.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout';

/**
 * Find the GA4 item for a product element a click landed in.
 *
 * @param {Element} el       The clicked element.
 * @param {string}  selector The collection root selector.
 * @return {{item: Object, root: Element}|null} The item and its collection root, or null.
 */
const productFor = ( el, selector ) => {
	const root = el.closest( selector );
	if ( ! root || root.closest( CART_CONTEXT ) ) {
		return null;
	}

	const container = el.closest( 'li, .wc-block-product, .wp-block-post' );
	const span = container
		? container.querySelector( BLOCK_PRODUCT_DATA )
		: null;
	if ( ! span ) {
		return null;
	}

	const item = parseItem( span.getAttribute( 'data-gtmkit_product_data' ) );
	if ( ! item || Object.keys( item ).length === 0 ) {
		return null;
	}

	return { item, root };
};

/**
 * Read the GA4 items rendered inside a collection root.
 *
 * @param {Element} root     The collection root element.
 * @param {string}  listName List name to stamp on each item.
 * @return {Array<Object>} GA4 items.
 */
const readItems = ( root, listName ) => {
	const items = [];

	root.querySelectorAll( BLOCK_PRODUCT_DATA ).forEach( ( span, index ) => {
		const item = parseItem(
			span.getAttribute( 'data-gtmkit_product_data' )
		);
		if ( ! item || Object.keys( item ).length === 0 ) {
			return;
		}
		if ( listName ) {
			item.item_list_name = listName;
		}
		item.index = index + 1;
		items.push( item );
	} );

	return items;
};

/**
 * A signature of the rendered product set, used to skip unchanged re-renders.
 *
 * @param {Array<Object>} items GA4 items.
 * @return {string} A join of the item ids.
 */
const itemsSignature = ( items ) =>
	items.map( ( i ) => `${ i.item_id ?? i.id ?? '' }` ).join( '|' );

/**
 * Resolve the list name for a collection root: a server-stamped
 * `data-gtmkit-list-name`, else a generic default.
 *
 * @param {Element} root The collection root.
 * @return {string} The list name.
 */
const resolveListName = ( root ) =>
	root.getAttribute( 'data-gtmkit-list-name' ) || 'Product Collection';

/**
 * Mount list tracking on every matching collection root.
 *
 * @param {Object}           deps                    Dependencies.
 * @param {Document|Element} [deps.root]             Scope to search within.
 * @param {string}           [deps.selector]         Collection root selector.
 * @param {Function}         [deps.listNameResolver] `(rootEl) => string` list-name resolver.
 * @param {Function}         [deps.observerFactory]  `(cb) => MutationObserver`.
 * @return {Function} A detach handle.
 */
export const createProductCollectionSubscriber = ( {
	root = document,
	selector = '.wp-block-woocommerce-product-collection',
	listNameResolver = resolveListName,
	observerFactory = ( cb ) => new window.MutationObserver( cb ),
} ) => {
	const observers = [];
	const collections = Array.from( root.querySelectorAll( selector ) ).filter(
		( collectionRoot ) => ! collectionRoot.closest( CART_CONTEXT )
	);

	collections.forEach( ( collectionRoot ) => {
		const listName = listNameResolver( collectionRoot );
		let lastSignature = null;

		const fire = () => {
			const items = readItems( collectionRoot, listName );
			if ( items.length === 0 ) {
				return;
			}

			const signature = itemsSignature( items );
			if ( signature === lastSignature ) {
				return;
			}
			lastSignature = signature;

			pushEvent( EVENTS.VIEW_ITEM_LIST, { ecommerce: { items } } );
		};

		const onMutate = () => {
			try {
				fire();
			} catch ( e ) {
				logError( 'product-collection', e );
			}
		};

		// Initial fire for the server-rendered set.
		onMutate();

		const observer = observerFactory( onMutate );
		observer.observe( collectionRoot, { childList: true, subtree: true } );
		observers.push( observer );
	} );

	// Delegated select_item for product-link clicks within a collection.
	const onClick = ( event ) => {
		try {
			if ( ! event.target.closest ) {
				return;
			}

			const anchor = event.target.closest( PRODUCT_ANCHOR );
			if ( ! anchor || anchor.closest( NON_PRODUCT_LINK ) ) {
				return;
			}

			const found = productFor( anchor, selector );
			if ( ! found ) {
				return;
			}

			const listName = listNameResolver( found.root );
			if ( listName ) {
				found.item.item_list_name = listName;
			}

			pushEvent( EVENTS.SELECT_ITEM, {
				ecommerce: { items: [ found.item ] },
			} );
		} catch ( e ) {
			logError( 'product-collection-select', e );
		}
	};

	// Delegated add_to_cart for the collection's (legacy AJAX) add-to-cart
	// button. The button drives a classic AJAX add that does not touch the
	// block cart store, so the event is built from the rendered item data.
	const onAddToCart = ( event ) => {
		try {
			if ( ! event.target.closest ) {
				return;
			}

			const button = event.target.closest( ADD_TO_CART_BUTTON );
			if ( ! button || button.matches( NON_PRODUCT_LINK ) ) {
				return;
			}

			const found = productFor( button, selector );
			if ( ! found ) {
				return;
			}

			const listName = listNameResolver( found.root );
			if ( listName ) {
				found.item.item_list_name = listName;
			}
			found.item.quantity = 1;

			pushEvent( EVENTS.ADD_TO_CART, {
				ecommerce: {
					currency: getCurrency(),
					value: Number( found.item.price ?? 0 ),
					items: [ found.item ],
				},
			} );
		} catch ( e ) {
			logError( 'product-collection-add', e );
		}
	};

	const clickRoot = root === document ? document : root;
	clickRoot.addEventListener( 'click', onClick, true );
	clickRoot.addEventListener( 'click', onAddToCart, true );

	return () => {
		observers.forEach( ( o ) => o.disconnect() );
		clickRoot.removeEventListener( 'click', onClick, true );
		clickRoot.removeEventListener( 'click', onAddToCart, true );
	};
};
