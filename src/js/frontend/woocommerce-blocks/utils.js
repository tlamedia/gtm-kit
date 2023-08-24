/**
 * Push event to the datalayer
 *
 * @param eventName
 * @param eventParams
 */
export const pushEvent = ( eventName, eventParams ) => {
	window[ gtmkit_settings.datalayer_name ].push( { ecommerce: null } );
	window[ gtmkit_settings.datalayer_name ].push( {
		event: eventName,
		eventParams,
	} );

	if ( gtmkit_settings.console_log === 'on' )
		console.log( `Pushing event ${ eventName }` );
};

/**
 * Track shipping info
 */
export const shippingInfo = () => {
	if ( gtmkit_data.wc.add_shipping_info.fired === true ) return;

	const eventParams = {
		ecommerce: {
			currency: gtmkit_data.wc.currency,
			value: gtmkit_data.wc.cart_value,
			shipping_tier: gtmkit_data.wc.chosen_shipping_method,
			items: gtmkit_data.wc.cart_items,
		},
	};

	pushEvent( 'add_shipping_info', eventParams );

	gtmkit_data.wc.add_shipping_info.fired = true;
};

/**
 * Track payment info
 */
export const paymentInfo = () => {
	if ( gtmkit_data.wc.add_payment_info.fired === true ) return;

	const eventParams = {
		ecommerce: {
			currency: gtmkit_data.wc.currency,
			value: gtmkit_data.wc.cart_value,
			payment_type: gtmkit_data.wc.chosen_payment_method,
			items: gtmkit_data.wc.cart_items,
		},
	};

	pushEvent( 'add_payment_info', eventParams );

	gtmkit_data.wc.add_payment_info.fired = true;
};
