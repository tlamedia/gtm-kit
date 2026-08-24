/**
 * Add to Cart + Options block tracking.
 *
 * WooCommerce renders this block two ways. When a third party prints a
 * form element into the classic add-to-cart hooks, the block gives up on
 * the Interactivity API and falls back to a plain `form.cart` that posts
 * and reloads the page; the classic WooCommerce script reports that one,
 * because it finds the product by `form.cart`. Otherwise the block keeps
 * the in-place add and renders a form that carries no `cart` class at
 * all, which leaves the classic reader with nothing to match on.
 *
 * This module owns that second form. It keys on the Interactivity submit
 * binding rather than on a class, because that binding is exactly what
 * separates the two paths: present means the add happens in place and is
 * this module's to report, absent means the classic script has it.
 *
 * The in-place add also reaches `wc/store/cart`, so the cart subscriber
 * sees the same mutation in its diff. The units are recorded in the
 * click-add registry for it to subtract, the same arrangement the
 * Product Collection buttons use.
 */

import { EVENTS } from '../constants';
import { pushEvent, parseItem, getCurrency, logError } from '../utils';
import { recordClickAdd } from '../click-add-registry';

// The Interactivity path's own binding, and the discriminator against the
// legacy `form.cart` fallback the classic script reports.
const INTERACTIVE_FORM = 'form[data-wp-on--submit]';

// The carriers the classic single-product reader uses. The span is what
// the current release prints; the hidden input still sits in pages served
// from a full-page cache written before it, so both are read.
const CARRIER_SPAN = '.gtmkit_single_product_data';
const CARRIER_INPUT = 'input[name="gtmkit_product_data"]';

/**
 * Read the GA4 item a form carries.
 *
 * @param {HTMLFormElement} form The submitted form.
 * @return {Object|null} The GA4 item, or null when the page carries none.
 */
const itemFor = ( form ) => {
	const span = form.querySelector( CARRIER_SPAN );
	let raw = span ? span.getAttribute( 'data-gtmkit_product_data' ) : null;

	if ( ! raw ) {
		const input = form.querySelector( CARRIER_INPUT );
		raw = input ? input.value : null;
	}

	if ( ! raw ) {
		return null;
	}

	const item = parseItem( raw );

	return item && Object.keys( item ).length > 0 ? item : null;
};

/**
 * Read the quantity the shopper is adding.
 *
 * The stepper and the plain number field both render `name="quantity"`.
 * A form without one adds a single unit.
 *
 * @param {HTMLFormElement} form The submitted form.
 * @return {number} Units being added, at least one.
 */
const quantityFor = ( form ) => {
	const field = form.querySelector( '[name="quantity"]' );
	const parsed = field ? parseFloat( field.value ) : NaN;

	return Number.isFinite( parsed ) && parsed > 0 ? parsed : 1;
};

/**
 * Mount add_to_cart tracking for the block's in-place add.
 *
 * @param {Object}           deps        Dependencies.
 * @param {Document|Element} [deps.root] Scope to listen on.
 * @return {Function} A detach handle.
 */
export const createAddToCartWithOptionsSubscriber = ( {
	root = document,
} = {} ) => {
	const onSubmit = ( event ) => {
		try {
			const form = event.target;

			if (
				! form ||
				! form.matches ||
				! form.matches( INTERACTIVE_FORM )
			) {
				return;
			}

			const item = itemFor( form );

			if ( ! item ) {
				return;
			}

			const quantity = quantityFor( form );

			item.quantity = quantity;

			recordClickAdd( item.item_id ?? item.id, quantity );

			pushEvent( EVENTS.ADD_TO_CART, {
				ecommerce: {
					currency: getCurrency(),
					value: Number( item.price ?? 0 ) * quantity,
					items: [ item ],
				},
			} );
		} catch ( e ) {
			logError( 'add-to-cart-with-options', e );
		}
	};

	// Capture, so the report is made even if the block's own handler stops
	// the event from propagating any further.
	root.addEventListener( 'submit', onSubmit, true );

	return () => root.removeEventListener( 'submit', onSubmit, true );
};
