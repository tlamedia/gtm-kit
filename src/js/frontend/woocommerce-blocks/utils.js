/**
 * Shared helpers for the WooCommerce block subscribers.
 *
 * Every dataLayer write goes through `window.gtmkit.events.push()` — the
 * runtime seam shipped in 2.13.0 — so the consent gate and the Premium
 * event-deferral queue apply automatically. Nothing here calls
 * `dataLayer.push()` directly.
 */

/**
 * Push an event to the dataLayer through the gtmkit runtime seam.
 *
 * @param {string} eventName   GA4 event name.
 * @param {Object} eventParams Event payload (typically `{ ecommerce: {...} }`).
 */
export const pushEvent = ( eventName, eventParams ) => {
	const datalayerName = window.gtmkit_settings.datalayer_name;
	window.gtmkit.events.push( { ecommerce: null }, datalayerName );
	window.gtmkit.events.push(
		{
			event: eventName,
			...eventParams,
		},
		datalayerName
	);

	if ( window.gtmkit_settings.console_log === true ) {
		// eslint-disable-next-line no-console
		console.log( `Pushing event ${ eventName }` );
	}
};

/**
 * Track shipping info. Honors the once-per-page `fired` guard so the
 * rate-set and submit paths can both call it without duplicating.
 */
export const shippingInfo = () => {
	if ( window.gtmkit_data.wc.add_shipping_info.fired === true ) {
		return;
	}

	const eventParams = {
		ecommerce: {
			currency: window.gtmkit_data.wc.currency,
			value: window.gtmkit_data.wc.cart_value,
			shipping_tier: window.gtmkit_data.wc.chosen_shipping_method,
			items: window.gtmkit_data.wc.cart_items,
		},
	};

	pushEvent( 'add_shipping_info', eventParams );

	window.gtmkit_data.wc.add_shipping_info.fired = true;
};

/**
 * Track payment info. Honors the once-per-page `fired` guard.
 */
export const paymentInfo = () => {
	if ( window.gtmkit_data.wc.add_payment_info.fired === true ) {
		return;
	}

	const eventParams = {
		ecommerce: {
			currency: window.gtmkit_data.wc.currency,
			value: window.gtmkit_data.wc.cart_value,
			payment_type: window.gtmkit_data.wc.chosen_payment_method,
			items: window.gtmkit_data.wc.cart_items,
		},
	};

	pushEvent( 'add_payment_info', eventParams );

	window.gtmkit_data.wc.add_payment_info.fired = true;
};

/**
 * The configured store currency.
 *
 * @return {string} ISO currency code.
 */
export const getCurrency = () => window.gtmkit_data?.wc?.currency ?? '';

/**
 * Normalize the GTM Kit item payload carried on a Store API entity.
 *
 * The ProductSchema extension exposes `extensions.gtmkit.item` as an
 * object; the CartItemSchema extension exposes it as a JSON string.
 * Returns a fresh object either way so callers can mutate `quantity`
 * without leaking back into the store state.
 *
 * @param {Object|string|undefined} raw The raw extension value.
 * @return {Object} A GA4 item object (empty object when unparseable).
 */
export const parseItem = ( raw ) => {
	if ( ! raw ) {
		return {};
	}
	if ( typeof raw === 'string' ) {
		try {
			return JSON.parse( raw );
		} catch ( e ) {
			return {};
		}
	}
	// Shallow clone so quantity mutations don't touch the store object.
	return { ...raw };
};

/**
 * Format a product entity from a list block into a GA4 item.
 *
 * @param {Object} product  Store API product or block product entity.
 * @param {string} listName Optional list name to stamp on the item.
 * @return {Object} GA4 item object.
 */
export const getProductImpressionObject = ( product, listName = '' ) => {
	const item = parseItem( product?.extensions?.gtmkit?.item );

	if ( listName ) {
		item.item_list_name = listName;
	}

	return item;
};

/**
 * Read the StoreAPI cart items into a normalized shape for diffing and
 * for the ecommerce payloads.
 *
 * @param {Object} cartData The `getCartData()` result.
 * @return {Array<{key: string, quantity: number, unitPrice: number, item: Object}>} Normalized cart items.
 */
export const normalizeCartItems = ( cartData ) => {
	if ( ! cartData || ! Array.isArray( cartData.items ) ) {
		return [];
	}

	return cartData.items.map( ( cartItem ) => {
		const item = parseItem( cartItem?.extensions?.gtmkit?.item );
		item.quantity = cartItem.quantity;

		const salePrice = Number( cartItem?.prices?.sale_price ?? 0 );

		return {
			key: cartItem.key,
			quantity: cartItem.quantity,
			// Store API prices are in minor units (e.g. cents).
			unitPrice: Number.isFinite( salePrice ) ? salePrice / 100 : 0,
			item,
		};
	} );
};

/**
 * A stable signature of the cart contents, used to suppress duplicate
 * `view_cart` emissions when the Mini Cart is re-opened unchanged.
 *
 * @param {Array<{key: string, quantity: number}>} items Normalized cart items.
 * @return {string} A stable signature string.
 */
export const cartSignature = ( items ) =>
	items
		.map( ( i ) => `${ i.key }:${ i.quantity }` )
		.sort()
		.join( '|' );

/**
 * Run a callback on the next microtask, collapsing a burst of
 * synchronous invocations into a single run against the latest state.
 * Used so rapid store notifications for one logical update don't emit
 * intermediate events.
 *
 * @param {Function} callback The work to run.
 * @return {Function} A scheduler that queues at most one pending run.
 */
export const microtaskQueue = ( callback ) => {
	let queued = false;
	return () => {
		if ( queued ) {
			return;
		}
		queued = true;
		Promise.resolve().then( () => {
			queued = false;
			callback();
		} );
	};
};

/**
 * Log an error to the console when console logging is enabled.
 *
 * @param {string} handler The handler name.
 * @param {Error}  error   The caught error.
 */
export const logError = ( handler, error ) => {
	if ( window.gtmkit_settings?.console_log === true ) {
		// eslint-disable-next-line no-console
		console.error( `GTM Kit: Error in ${ handler } handler`, error );
	}
};
