/**
 * Product Search block search event.
 *
 * Emits `search` with the entered term when a WooCommerce Product Search
 * block form is submitted.
 */

import { EVENTS } from '../constants';
import { pushEvent, logError } from '../utils';

const SEARCH_FORM = '.wc-block-product-search';
const SEARCH_FIELD =
	'.wc-block-product-search__field, input[type="search"], input[name="s"]';

/**
 * Mount the Product Search listener.
 *
 * @param {Object}           deps        Dependencies.
 * @param {Document|Element} [deps.root] Event root (defaults to document).
 * @return {Function} A detach handle.
 */
export const createProductSearchSubscriber = ( { root = document } = {} ) => {
	const onSubmit = ( event ) => {
		try {
			const form = event.target.closest
				? event.target.closest( SEARCH_FORM )
				: null;
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
