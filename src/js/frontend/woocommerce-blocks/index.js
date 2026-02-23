/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { actionPrefix, namespace } from './constants';
import {
	getProductImpressionObject,
	shippingInfo,
	paymentInfo,
	pushEvent,
	logError,
} from './utils';

/**
 * Track the shipping rate being set
 */
addAction(
	`${ actionPrefix }-checkout-set-selected-shipping-rate`,
	namespace,
	( { shippingRateId } ) => {
		try {
			window.gtmkit_data.wc.chosen_shipping_method = shippingRateId;

			if (
				window.gtmkit_settings.wc.add_shipping_info.config === 0 ||
				window.gtmkit_data.wc.is_checkout === false
			)
				return;

			if ( window.gtmkit_settings.wc.add_shipping_info.config === 2 ) {
				shippingInfo();
			}
		} catch ( e ) {
			logError( 'set-selected-shipping-rate', e );
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
		try {
			window.gtmkit_data.wc.chosen_payment_method = value;

			if ( window.gtmkit_settings.wc.add_payment_info.config === 0 )
				return;

			if ( window.gtmkit_settings.wc.add_payment_info.config === 2 ) {
				paymentInfo();
			}
		} catch ( e ) {
			logError( 'set-active-payment-method', e );
		}
	}
);

/**
 * Checkout submit
 *
 * Note, this is used to indicate checkout submission, not `purchase` which is triggered on the thanks page.
 */
addAction( `${ actionPrefix }-checkout-submit`, namespace, () => {
	try {
		if ( window.gtmkit_settings.wc.add_shipping_info.config !== 0 )
			shippingInfo();
		if ( window.gtmkit_settings.wc.add_payment_info.config !== 0 )
			paymentInfo();
	} catch ( e ) {
		logError( 'checkout-submit', e );
	}
} );

/**
 * Change cart item quantities
 *
 * @summary Custom change_cart_quantity event.
 */
addAction(
	`${ actionPrefix }-cart-set-item-quantity`,
	namespace,
	( { product, quantity = 1 } ) => {
		try {
			if ( product.quantity < quantity ) {
				// quantity increase

				const quantityAdded = quantity - product.quantity;
				const item = JSON.parse( product.extensions.gtmkit.item );
				item.quantity = quantityAdded;

				const eventParams = {
					ecommerce: {
						currency: window.gtmkit_data.wc.currency,
						value:
							( product.prices.sale_price / 100 ) * quantityAdded,
						items: [ item ],
					},
				};

				pushEvent( 'add_to_cart', eventParams );
			} else {
				// quantity decrease

				const quantityRemoved = product.quantity - quantity;
				const item = JSON.parse( product.extensions.gtmkit.item );
				item.quantity = quantityRemoved;

				const eventParams = {
					ecommerce: {
						currency: window.gtmkit_data.wc.currency,
						value:
							( product.prices.sale_price / 100 ) *
							quantityRemoved,
						items: [ item ],
					},
				};

				pushEvent( 'remove_from_cart', eventParams );
			}
		} catch ( e ) {
			logError( 'cart-set-item-quantity', e );
		}
	}
);

/**
 * remove_from_cart.
 */
addAction(
	`${ actionPrefix }-cart-remove-item`,
	namespace,
	( { product, quantity } ) => {
		try {
			const item = JSON.parse( product.extensions.gtmkit.item );

			const eventParams = {
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: ( product.prices.sale_price / 100 ) * quantity,
					items: [ item ],
				},
			};

			pushEvent( 'remove_from_cart', eventParams );
		} catch ( e ) {
			logError( 'cart-remove-item', e );
		}
	}
);

/**
 * add_to_cart.
 */
addAction(
	`${ actionPrefix }-cart-add-item`,
	namespace,
	( { product, quantity = 1 } ) => {
		try {
			const item = product.extensions.gtmkit.item;

			const eventParams = {
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: ( product.prices.sale_price / 100 ) * quantity,
					items: [ item ],
				},
			};

			pushEvent( 'add_to_cart', eventParams );
		} catch ( e ) {
			logError( 'cart-add-item', e );
		}
	}
);

const lists = [];

/**
 * view_item_list.
 */
addAction(
	`${ actionPrefix }-product-list-render`,
	namespace,
	( { products, listName = __( 'Product List', 'gtm-kit' ) } ) => {
		try {
			if (
				products.length === 0 ||
				window.gtmkit_data.wc.is_cart === true
			) {
				return;
			}

			if (
				window.gtmkit_settings.wc.view_item_list.config === 1 &&
				Object.values( window.gtmkit_data.wc.blocks ).includes(
					'filter-wrapper'
				)
			) {
				if ( lists.includes( listName ) ) return;
				lists.push( listName );
			}

			const eventParams = {
				ecommerce: {
					items: products.map( ( product, index ) => ( {
						...getProductImpressionObject( product, listName ),
						index,
					} ) ),
				},
			};

			pushEvent( 'view_item_list', eventParams );
		} catch ( e ) {
			logError( 'product-list-render', e );
		}
	}
);

/**
 * select_item.
 */
addAction(
	`${ actionPrefix }-product-view-link`,
	namespace,
	( { product, listName = '' } ) => {
		try {
			const eventParams = {
				ecommerce: {
					item_list_name: listName,
					items: [ getProductImpressionObject( product, listName ) ],
				},
			};

			pushEvent( 'select_item', eventParams );
		} catch ( e ) {
			logError( 'product-view-link', e );
		}
	}
);

/**
 * Product Search
 */
addAction(
	`${ actionPrefix }-product-search`,
	namespace,
	( { searchTerm } ) => {
		try {
			const eventParams = {
				search_term: searchTerm,
			};
			pushEvent( 'search', eventParams );
		} catch ( e ) {
			logError( 'product-search', e );
		}
	}
);
