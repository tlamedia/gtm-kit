/**
 * WooCommerce block tracking entry point.
 *
 * Detects which block data stores and block UIs are present on the page
 * and mounts only the subscribers that apply. Over-enqueueing the bundle
 * is harmless: each subscriber no-ops when its store or block is absent.
 *
 * Every event flows through `window.gtmkit.events.push()` (see utils.js),
 * so consent gating and the Premium deferral queue apply automatically.
 */

import { select, subscribe } from '@wordpress/data';

import { CHECKOUT_STORE } from './constants';
import { createCartSubscriber } from './stores/cart-subscriber';
import { createCheckoutSubscriber } from './stores/checkout-subscriber';
import { createMiniCartSubscriber } from './blocks/mini-cart';
import { createCrossSellsSubscriber } from './blocks/cart-cross-sells';
import { initAllProducts } from './blocks/all-products';
import { createProductCollectionSubscriber } from './blocks/product-collection';
import { createRelatedProductsSubscriber } from './blocks/related-products';
import { initSingleProductBlock } from './blocks/single-product-block';
import { createProductSearchSubscriber } from './blocks/product-search';
import { logError } from './utils';

/**
 * Whether a data store is registered and resolvable.
 *
 * @param {string} storeName Store key.
 * @return {boolean} True when the store resolves.
 */
const hasStore = ( storeName ) => {
	try {
		return Boolean( select( storeName ) );
	} catch ( e ) {
		return false;
	}
};

/**
 * Detect available stores/blocks and mount the matching subscribers.
 */
export const boot = () => {
	try {
		const deps = { select, subscribe };

		// The cart subscriber uses a global subscription and guards on the
		// store internally, so it can mount before the Cart/Checkout block
		// registers wc/store/cart (which it does lazily). Mounting it
		// unconditionally is harmless: it no-ops until the store appears.
		createCartSubscriber( deps );

		// The Mini Cart block (WC 10.x) is an Interactivity API block and
		// does not register wc/store/cart, so mount on DOM presence and read
		// the cart over the Store API on open.
		if ( document.querySelector( '.wc-block-mini-cart' ) ) {
			createMiniCartSubscriber();
		}

		if (
			hasStore( CHECKOUT_STORE ) ||
			document.querySelector( '.wp-block-woocommerce-checkout' )
		) {
			createCheckoutSubscriber( deps );
		}

		// Cart block cross-sells read from the cart store's crossSells.
		if ( document.querySelector( '.wp-block-woocommerce-cart' ) ) {
			createCrossSellsSubscriber( deps );
		}

		// The All Products grid surfaces its products through a render
		// action, not the DOM, so subscribe unconditionally: the listener is
		// inert until the (legacy client-rendered) block dispatches.
		initAllProducts();

		if (
			document.querySelector( '.wp-block-woocommerce-product-collection' )
		) {
			createProductCollectionSubscriber( {} );
		}

		if (
			document.querySelector( '.wp-block-woocommerce-related-products' )
		) {
			createRelatedProductsSubscriber();
		}

		if (
			document.querySelector( '.wp-block-woocommerce-single-product' )
		) {
			initSingleProductBlock();
		}

		if ( document.querySelector( '.wc-block-product-search' ) ) {
			createProductSearchSubscriber();
		}
	} catch ( e ) {
		logError( 'boot', e );
	}
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
