/**
 * Checkout store subscriber.
 *
 * Emits `add_shipping_info` and `add_payment_info` from the block
 * Checkout. The selected shipping rate lives in the cart store, the
 * active payment method in the payment store, and the submit signal in
 * the checkout store, so this subscriber reads all three and reacts to
 * transitions.
 *
 * Behavior is parity with the previous experimental-hook implementation,
 * driven by the 0/1/2 admin toggles:
 *   - config 2: emit when the shipping rate / payment method is selected.
 *   - config 1: emit only on checkout submit.
 *   - config 0: never emit.
 * The once-per-page `fired` guard in shippingInfo()/paymentInfo() keeps
 * the rate-set and submit paths from duplicating.
 */

import { CART_STORE, CHECKOUT_STORE, PAYMENT_STORE } from '../constants';
import { shippingInfo, paymentInfo, microtaskQueue, logError } from '../utils';

const SUBMIT_STATUSES = [ 'before_processing', 'processing', 'complete' ];

/**
 * The currently selected shipping rate id, or '' when none is selected.
 *
 * @param {Object} cartStore The cart store selector bundle.
 * @return {string} The selected shipping rate id.
 */
const selectedShippingRate = ( cartStore ) => {
	if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
		return '';
	}

	const data = cartStore.getCartData();
	const packages =
		data && Array.isArray( data.shippingRates ) ? data.shippingRates : [];

	for ( const shippingPackage of packages ) {
		const rates = Array.isArray( shippingPackage.shipping_rates )
			? shippingPackage.shipping_rates
			: [];
		const selected = rates.find( ( rate ) => rate.selected === true );
		if ( selected ) {
			return selected.rate_id ?? '';
		}
	}

	return '';
};

/**
 * The active payment method id, or '' when none is selected.
 *
 * @param {Object} paymentStore The payment store selector bundle.
 * @return {string} The active payment method id.
 */
const activePaymentMethod = ( paymentStore ) => {
	if (
		! paymentStore ||
		typeof paymentStore.getActivePaymentMethod !== 'function'
	) {
		return '';
	}
	return paymentStore.getActivePaymentMethod() ?? '';
};

/**
 * The checkout status string.
 *
 * @param {Object} checkoutStore The checkout store selector bundle.
 * @return {string} The checkout status.
 */
const checkoutStatus = ( checkoutStore ) => {
	if (
		! checkoutStore ||
		typeof checkoutStore.getCheckoutStatus !== 'function'
	) {
		return '';
	}
	return checkoutStore.getCheckoutStatus() ?? '';
};

/**
 * Create and mount the checkout subscriber.
 *
 * @param {Object}   deps           Injected wp.data accessors.
 * @param {Function} deps.select    `wp.data.select`.
 * @param {Function} deps.subscribe `wp.data.subscribe`.
 * @return {Function} The unsubscribe handle.
 */
export const createCheckoutSubscriber = ( { select, subscribe } ) => {
	let previousRate = selectedShippingRate( select( CART_STORE ) );
	let previousMethod = activePaymentMethod( select( PAYMENT_STORE ) );
	let submitted = false;

	const shippingConfig = () =>
		window.gtmkit_settings.wc.add_shipping_info.config;
	const paymentConfig = () =>
		window.gtmkit_settings.wc.add_payment_info.config;
	const onCheckout = () => window.gtmkit_data?.wc?.is_checkout === true;

	const handle = () => {
		try {
			if ( ! onCheckout() ) {
				return;
			}

			const rate = selectedShippingRate( select( CART_STORE ) );
			if ( rate && rate !== previousRate ) {
				previousRate = rate;
				window.gtmkit_data.wc.chosen_shipping_method = rate;
				if ( shippingConfig() === 2 ) {
					shippingInfo();
				}
			}

			const method = activePaymentMethod( select( PAYMENT_STORE ) );
			if ( method && method !== previousMethod ) {
				previousMethod = method;
				window.gtmkit_data.wc.chosen_payment_method = method;
				if ( paymentConfig() === 2 ) {
					paymentInfo();
				}
			}

			const status = checkoutStatus( select( CHECKOUT_STORE ) );
			if ( ! submitted && SUBMIT_STATUSES.includes( status ) ) {
				submitted = true;
				if ( shippingConfig() !== 0 ) {
					shippingInfo();
				}
				if ( paymentConfig() !== 0 ) {
					paymentInfo();
				}
			}
		} catch ( e ) {
			logError( 'checkout-subscriber', e );
		}
	};

	return subscribe( microtaskQueue( handle ) );
};
