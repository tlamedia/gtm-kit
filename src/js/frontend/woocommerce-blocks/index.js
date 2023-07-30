/**
 * External dependencies
 */
import {addAction} from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {actionPrefix, namespace} from './constants';
import {
	shippingInfo,
	paymentInfo,
	pushEvent,
} from './utils';

/**
 * Track the shipping rate being set
 */
addAction(
	`${ actionPrefix }-checkout-set-selected-shipping-rate`,
	namespace,
	( { shippingRateId } ) => {
		gtmkit_data.wc.chosen_shipping_method = shippingRateId;

		if (gtmkit_settings.wc['add_shipping_info']['config'] === 0) return;

		if (gtmkit_settings.wc['add_shipping_info']['config'] === 2) {
			shippingInfo();
		}
	}
);

/**
 * Track the payment method being set
 */
addAction(
	`${ actionPrefix }-checkout-set-active-payment-method`,
	namespace,
	( { value } ) => {
		gtmkit_data.wc.chosen_payment_method = value;

		if (gtmkit_settings.wc['add_payment_info']['config'] === 0) return;

		if (gtmkit_settings.wc['add_payment_info']['config'] === 2) {
			paymentInfo();
		}
	}
);

/**
 * Checkout submit
 *
 * Note, this is used to indicate checkout submission, not `purchase` which is triggered on the thanks page.
 */
addAction( `${ actionPrefix }-checkout-submit`, namespace, () => {
	if (gtmkit_settings.wc['add_shipping_info']['config'] !== 0) shippingInfo();
	if (gtmkit_settings.wc['add_payment_info']['config'] !== 0) paymentInfo();
} );

/**
 * Change cart item quantities
 *
 * @summary Custom change_cart_quantity event.
 */
addAction(
	`${ actionPrefix }-cart-set-item-quantity`,
	namespace,
	( {
		  product,
		  quantity = 1,
	  } ) => {
		if (product.quantity < quantity) { // quantity increase

			let quantityAdded = quantity - product.quantity;
			let item = JSON.parse( product.extensions.gtmkit.item );
			item.quantity = quantityAdded;

			let eventParams = {
				'ecommerce': {
					'currency': gtmkit_data.wc['currency'],
					'value': product.prices['sale_price'] / 100  * quantityAdded,
					'items': [ item ]
				}
			};

			pushEvent( 'add_to_cart', eventParams );

		} else { // quantity decrease

			let quantityRemoved = product.quantity - quantity;
			let item = JSON.parse( product.extensions.gtmkit.item );
			item.quantity = quantityRemoved;

			let eventParams = {
				'ecommerce': {
					'currency': gtmkit_data.wc['currency'],
					'value': product.prices['sale_price'] / 100 * quantityRemoved,
					'items': [ item ]
				}
			};

			pushEvent( 'remove_from_cart', eventParams );
		}
	}
);

addAction(
	`${ actionPrefix }-cart-remove-item`,
	namespace,
	( {
		  product,
		  quantity,
	  } ) => {

		let item = JSON.parse( product.extensions.gtmkit.item );

		let eventParams = {
			'ecommerce': {
				'currency': gtmkit_data.wc['currency'],
				'value': product.prices['sale_price'] / 100  * quantity,
				'items': [ item ]
			}
		};

		pushEvent( 'remove_from_cart', eventParams );
	}
);

addAction(
	`${ actionPrefix }-cart-add-item`,
	namespace,
	( {
		  product,
		  quantity = 1,
	  } ) => {

		let item = JSON.parse( product.extensions.gtmkit.item );

		let eventParams = {
			'ecommerce': {
				'currency': gtmkit_data.wc['currency'],
				'value': product.prices['sale_price'] / 100  * quantity,
				'items': [ item ]
			}
		};

		pushEvent( 'add_to_cart', eventParams );
	}
);
