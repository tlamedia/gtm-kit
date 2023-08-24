/**
 * Push event to the datalayer
 *
 * @param {string} eventName
 * @param {Object} eventParams
 */
export const pushEvent = ( eventName, eventParams ) => {
	window[ window.gtmkit_settings.datalayer_name ].push( { ecommerce: null } );
	window[ window.gtmkit_settings.datalayer_name ].push( {
		event: eventName,
		eventParams,
	} );

	if ( window.gtmkit_settings.console_log === 'on' )
		// eslint-disable-next-line no-console
		console.log( `Pushing event ${ eventName }` );
};

/**
 * Track shipping info
 */
export const shippingInfo = () => {
	if ( window.gtmkit_data.wc.add_shipping_info.fired === true ) return;

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
 * Track payment info
 */
export const paymentInfo = () => {
	if ( window.gtmkit_data.wc.add_payment_info.fired === true ) return;

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
