/**
 * Product Search block search event.
 *
 * Emits `search` with the entered term when a WooCommerce Product Search
 * form is submitted, unless the results page owns the event. That is the
 * legacy Product Search block, or the WordPress Search block scoped to
 * products, which WooCommerce now inserts as Product Search. A Search block
 * without that scope searches the whole site and is not claimed. The
 * block submits to the search results page, so while the engagement search
 * event is on it reports the search there, or on the product page a search
 * with a single match redirects to, and this listener stays silent.
 */

import { EVENTS } from '../constants';
import { pushEvent, logError } from '../utils';

const SEARCH_FORM = '.wc-block-product-search';
const SEARCH_BLOCK_FORM = 'form.wp-block-search';
const PRODUCT_SCOPE = 'input[type="hidden"][name="post_type"][value="product"]';
const SEARCH_FIELD =
	'.wc-block-product-search__field, input[type="search"], input[name="s"]';

/**
 * Whether the search results page reports the `search` event.
 *
 * @return {boolean} True when the engagement search event is on.
 */
const resultsPageOwnsSearch = () =>
	Boolean( window.gtmkitWooCommerceBlocksBuild?.searchOnResultsPage );

/**
 * The Product Search form an event came from, or null.
 *
 * @param {EventTarget} target The submitted element.
 * @return {Element|null} The form.
 */
const productSearchForm = ( target ) => {
	if ( ! target || ! target.closest ) {
		return null;
	}

	const legacy = target.closest( SEARCH_FORM );
	if ( legacy ) {
		return legacy;
	}

	const form = target.closest( SEARCH_BLOCK_FORM );
	return form && form.querySelector( PRODUCT_SCOPE ) ? form : null;
};

/**
 * Whether the page has a Product Search form, in either form.
 *
 * @param {Document|Element} [root] Where to look (defaults to document).
 * @return {boolean} True when one is present.
 */
export const hasProductSearch = ( root = document ) =>
	Boolean(
		root.querySelector(
			`${ SEARCH_FORM }, ${ SEARCH_BLOCK_FORM } ${ PRODUCT_SCOPE }`
		)
	);

/**
 * Mount the Product Search listener.
 *
 * @param {Object}           deps        Dependencies.
 * @param {Document|Element} [deps.root] Event root (defaults to document).
 * @return {Function} A detach handle.
 */
export const createProductSearchSubscriber = ( { root = document } = {} ) => {
	if ( resultsPageOwnsSearch() ) {
		return () => {};
	}

	const onSubmit = ( event ) => {
		try {
			const form = productSearchForm( event.target );
			if ( ! form ) {
				return;
			}

			const field = form.querySelector( SEARCH_FIELD );
			const term = field && field.value ? field.value.trim() : '';
			if ( ! term ) {
				return;
			}

			pushEvent( EVENTS.SEARCH, { search_term: term } );
		} catch ( e ) {
			logError( 'product-search', e );
		}
	};

	root.addEventListener( 'submit', onSubmit, true );

	return () => root.removeEventListener( 'submit', onSubmit, true );
};
