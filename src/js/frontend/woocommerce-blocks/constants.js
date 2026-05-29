/**
 * Block tracking constants.
 *
 * Store keys for the WooCommerce block data stores and the GA4 event
 * names emitted by the subscribers. Kept in one place so a future
 * WooCommerce store rename is a one-line change (see the architecture
 * doc's "Accepted risk" note).
 */

export const CART_STORE = 'wc/store/cart';
export const CHECKOUT_STORE = 'wc/store/checkout';
export const PAYMENT_STORE = 'wc/store/payment';

export const EVENTS = {
	ADD_TO_CART: 'add_to_cart',
	REMOVE_FROM_CART: 'remove_from_cart',
	VIEW_CART: 'view_cart',
	ADD_SHIPPING_INFO: 'add_shipping_info',
	ADD_PAYMENT_INFO: 'add_payment_info',
	VIEW_ITEM_LIST: 'view_item_list',
	SELECT_ITEM: 'select_item',
	VIEW_ITEM: 'view_item',
	SEARCH: 'search',
};
