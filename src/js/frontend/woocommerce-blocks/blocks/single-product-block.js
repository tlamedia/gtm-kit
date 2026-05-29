/**
 * Single Product block view_item fallback.
 *
 * On a normal product URL the PHP `is_product()` branch already emits
 * `view_item`, so this module must not duplicate it. But a Single Product
 * block placed on a non-product page (e.g. a custom landing page) gets no
 * server-side `view_item`. This module emits `view_item` once from the
 * block's rendered product data, but only when no `view_item` is already
 * present on the dataLayer for this page.
 */

import { EVENTS } from '../constants';
import { pushEvent, parseItem, getCurrency, logError } from '../utils';

const SINGLE_PRODUCT_SELECTOR = '.wp-block-woocommerce-single-product';
const BLOCK_PRODUCT_DATA = '.gtmkit_block_product_data';

/**
 * Whether a `view_item` event is already on the dataLayer.
 *
 * @return {boolean} True when a view_item is already present.
 */
const viewItemAlreadyFired = () => {
	const name = window.gtmkit_settings?.datalayer_name || 'dataLayer';
	const layer = window[ name ];
	if ( ! Array.isArray( layer ) ) {
		return false;
	}
	return layer.some(
		( entry ) =>
			entry &&
			typeof entry === 'object' &&
			entry.event === EVENTS.VIEW_ITEM
	);
};

/**
 * Mount the Single Product block view_item fallback.
 *
 * @param {Object}           deps        Dependencies.
 * @param {Document|Element} [deps.root] Scope to search within.
 * @return {void}
 */
export const initSingleProductBlock = ( { root = document } = {} ) => {
	try {
		const block = root.querySelector( SINGLE_PRODUCT_SELECTOR );
		if ( ! block ) {
			return;
		}

		if ( viewItemAlreadyFired() ) {
			return;
		}

		const span = block.querySelector( BLOCK_PRODUCT_DATA );
		if ( ! span ) {
			return;
		}

		const item = parseItem(
			span.getAttribute( 'data-gtmkit_product_data' )
		);
		if ( ! item || Object.keys( item ).length === 0 ) {
			return;
		}

		pushEvent( EVENTS.VIEW_ITEM, {
			ecommerce: {
				currency: getCurrency(),
				value: Number( item.price ?? 0 ),
				items: [ item ],
			},
		} );
	} catch ( e ) {
		logError( 'single-product-block', e );
	}
};
