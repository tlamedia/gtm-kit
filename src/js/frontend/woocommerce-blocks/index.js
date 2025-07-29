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
} from './utils';

/**
 * Track the shipping rate being set
 */
addAction(
	`${actionPrefix}-checkout-set-selected-shipping-rate`,
	namespace,
	({ shippingRateId }) => {
		window.gtmkit_data.wc.chosen_shipping_method = shippingRateId;

		if (
			window.gtmkit_settings.wc.add_shipping_info.config === 0 ||
			window.gtmkit_data.wc.is_checkout === false
		)
			return;

		if (window.gtmkit_settings.wc.add_shipping_info.config === 2) {
			shippingInfo();
		}
	}
);

/**
 * Track the payment method being set
 */
addAction(
	`${actionPrefix}-checkout-set-active-payment-method`,
	namespace,
	({ value }) => {
		window.gtmkit_data.wc.chosen_payment_method = value;

		if (window.gtmkit_settings.wc.add_payment_info.config === 0) return;

		if (window.gtmkit_settings.wc.add_payment_info.config === 2) {
			paymentInfo();
		}
	}
);

/**
 * Checkout submit
 *
 * Note, this is used to indicate checkout submission, not `purchase` which is triggered on the thanks page.
 */
addAction(`${actionPrefix}-checkout-submit`, namespace, () => {
	if (window.gtmkit_settings.wc.add_shipping_info.config !== 0)
		shippingInfo();
	if (window.gtmkit_settings.wc.add_payment_info.config !== 0) paymentInfo();
});

/**
 * Change cart item quantities
 *
 * @summary Custom change_cart_quantity event.
 */
addAction(
	`${actionPrefix}-cart-set-item-quantity`,
	namespace,
	({ product, quantity = 1 }) => {
		if (product.quantity < quantity) {
			// quantity increase

			const quantityAdded = quantity - product.quantity;
			const item = JSON.parse(product.extensions.gtmkit.item);
			item.quantity = quantityAdded;

			const eventParams = {
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: (product.prices.sale_price / 100) * quantityAdded,
					items: [item],
				},
			};

			pushEvent('add_to_cart', eventParams);
		} else {
			// quantity decrease

			const quantityRemoved = product.quantity - quantity;
			const item = JSON.parse(product.extensions.gtmkit.item);
			item.quantity = quantityRemoved;

			const eventParams = {
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: (product.prices.sale_price / 100) * quantityRemoved,
					items: [item],
				},
			};

			pushEvent('remove_from_cart', eventParams);
		}
	}
);

/**
 * remove_from_cart.
 */
addAction(
	`${actionPrefix}-cart-remove-item`,
	namespace,
	({ product, quantity }) => {
		const item = JSON.parse(product.extensions.gtmkit.item);

		const eventParams = {
			ecommerce: {
				currency: window.gtmkit_data.wc.currency,
				value: (product.prices.sale_price / 100) * quantity,
				items: [item],
			},
		};

		pushEvent('remove_from_cart', eventParams);
	}
);

/**
 * add_to_cart.
 */
addAction(
	`${actionPrefix}-cart-add-item`,
	namespace,
	({ product, quantity = 1 }) => {
		const item = product.extensions.gtmkit.item;

		const eventParams = {
			ecommerce: {
				currency: window.gtmkit_data.wc.currency,
				value: (product.prices.sale_price / 100) * quantity,
				items: [item],
			},
		};

		pushEvent('add_to_cart', eventParams);
	}
);

const lists = [];

/**
 * view_item_list.
 */
addAction(
	`${actionPrefix}-product-list-render`,
	namespace,
	({ products, listName = __('Product List', 'gtm-kit') }) => {
		if (products.length === 0 || window.gtmkit_data.wc.is_cart === true) {
			return;
		}

		if (
			window.gtmkit_settings.wc.view_item_list.config === 1 &&
			Object.values(window.gtmkit_data.wc.blocks).includes(
				'filter-wrapper'
			)
		) {
			if (lists.includes(listName)) return;
			lists.push(listName);
		}

		const eventParams = {
			ecommerce: {
				items: products.map((product, index) => ({
					...getProductImpressionObject(product, listName),
					index,
				})),
			},
		};

		pushEvent('view_item_list', eventParams);
	}
);

/**
 * select_item.
 */
addAction(
	`${actionPrefix}-product-view-link`,
	namespace,
	({ product, listName = '' }) => {
		const eventParams = {
			ecommerce: {
				item_list_name: listName,
				items: [getProductImpressionObject(product, listName)],
			},
		};

		pushEvent('select_item', eventParams);
	}
);

/**
 * Product Search
 */
addAction(`${actionPrefix}-product-search`, namespace, ({ searchTerm }) => {
	const eventParams = {
		search_term: searchTerm,
	};
	pushEvent('search', eventParams);
});
