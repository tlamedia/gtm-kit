/**
 * Related Products list tracking.
 *
 * The Related Products block renders a static product grid, so it reuses
 * the Product Collection engine with a fixed `Related products` list name
 * for parity with the classic template's
 * `set_list_name_in_woocommerce_loop()` output. PHP injects the same
 * `.gtmkit_block_product_data` carrier into each product item.
 */

import { createProductCollectionSubscriber } from './product-collection';

const RELATED_SELECTOR = '.wp-block-woocommerce-related-products';
const LIST_NAME = 'Related products';

/**
 * Mount Related Products list tracking.
 *
 * @param {Object}           deps                   Dependencies.
 * @param {Document|Element} [deps.root]            Scope to search within.
 * @param {Function}         [deps.observerFactory] `(cb) => MutationObserver`.
 * @return {Function} A detach handle.
 */
export const createRelatedProductsSubscriber = ( {
	root = document,
	observerFactory,
} = {} ) =>
	createProductCollectionSubscriber( {
		root,
		selector: RELATED_SELECTOR,
		listNameResolver: () => LIST_NAME,
		...( observerFactory ? { observerFactory } : {} ),
	} );
