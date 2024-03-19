if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', gtmkitLoadCheckout);
} else {
	gtmkitLoadCheckout();
}

function gtmkitLoadCheckout() {
	if (window.gtmkit_data.wc.is_cart) gtmkitCart();
	if (window.gtmkit_data.wc.is_checkout) gtmkitCheckout();
}

function gtmkitCart() {
	document.addEventListener('click', function (e) {
		const eventTargetElement = e.target;
		if (
			!eventTargetElement ||
			!eventTargetElement.closest('[name=update_cart]')
		)
			return true;

		gtmkitCartQuantityChange();
	});

	document.addEventListener('keypress', function (e) {
		const eventTargetElement = e.target;
		if (
			!eventTargetElement ||
			!eventTargetElement.closest(
				'.woocommerce-cart-form input[type=number]'
			)
		)
			return true;

		gtmkitCartQuantityChange();
	});
}

function gtmkitCartQuantityChange() {
	const datalayerName = window.gtmkit_settings.datalayer_name;

	document
		.querySelectorAll('.product-quantity input.qty')
		.forEach(function (qtyElement) {
			const defaultValue = qtyElement.defaultValue;
			let currentValue = parseInt(qtyElement.value);
			if (isNaN(currentValue)) currentValue = defaultValue;

			if (defaultValue !== currentValue) {
				const cartItem = qtyElement.closest('.cart_item');
				const productData =
					cartItem && cartItem.querySelector('.remove');
				if (!productData) return;
				const itemData = JSON.parse(
					productData.getAttribute('data-gtmkit_product_data')
				);

				if (defaultValue < currentValue) {
					// quantity increase
					itemData.quantity = currentValue - defaultValue;

					window[datalayerName].push({ ecommerce: null });
					window[datalayerName].push({
						event: 'add_to_cart',
						ecommerce: {
							currency: window.gtmkit_data.wc.currency,
							value:
								itemData.price * (currentValue - defaultValue),
							items: [itemData],
						},
					});
				} else {
					// quantity decrease
					itemData.quantity = defaultValue - currentValue;

					window[datalayerName].push({ ecommerce: null });
					window[datalayerName].push({
						event: 'remove_from_cart',
						ecommerce: {
							currency: window.gtmkit_data.wc.currency,
							value:
								itemData.price * (defaultValue - currentValue),
							items: [itemData],
						},
					});
				}
			}
		});
}

function gtmkitCheckout() {
	if (
		window.gtmkit_settings.wc.add_shipping_info.config === 0 &&
		window.gtmkit_settings.wc.add_payment_info.config === 0
	)
		return;

	if (window.gtmkit_settings.wc.add_shipping_info.config === 2) {
		document.addEventListener('change', function (e) {
			const eventTargetElement = e.target;
			if (
				!eventTargetElement ||
				(!eventTargetElement.closest('input[name^=shipping_method]') &&
					!eventTargetElement.closest(
						'.wc-block-components-shipping-rates-control'
					))
			)
				return true;

			gtmkitShippingEvent();
		});
	}

	if (window.gtmkit_settings.wc.add_payment_info.config === 2) {
		document.addEventListener('change', function (e) {
			const eventTargetElement = e.target;
			if (
				!eventTargetElement ||
				(!eventTargetElement.closest('input[name=payment_method]') &&
					!eventTargetElement.closest(
						'.wc-block-checkout__payment-method'
					))
			)
				return true;

			gtmkitPaymentEvent();
		});
	}

	document.addEventListener('click', function (e) {
		const eventTargetElement = e.target.closest('button');

		if (!eventTargetElement) {
			return true;
		}

		if (
			eventTargetElement.classList.contains(
				'wc-block-components-checkout-place-order-button'
			) ||
			eventTargetElement.closest(
				'button[name=woocommerce_checkout_place_order]'
			)
		) {
			gtmkitShippingEvent();
			gtmkitPaymentEvent();
		} else {
			return true;
		}
	});
}

function gtmkitShippingEvent() {
	if (window.gtmkit_data.wc.add_shipping_info.fired === true) return;

	const datalayerName = window.gtmkit_settings.datalayer_name;

	let shippingElement;

	shippingElement = document.querySelector(
		'input[name^=shipping_method]:checked'
	);
	if (!shippingElement) {
		shippingElement = document.querySelector(
			'input[name^=shipping_method]'
		); // select the first shipping method
	}

	const shippingTier = shippingElement
		? shippingElement.value
		: window.gtmkit_settings.wc.text['shipping-tier-not-found'];

	window[datalayerName].push({ ecommerce: null });
	window[datalayerName].push({
		event: 'add_shipping_info',
		ecommerce: {
			currency: window.gtmkit_data.wc.currency,
			value: window.gtmkit_data.wc.cart_value,
			shippingTier,
			items: window.gtmkit_data.wc.cart_items,
		},
	});

	window.gtmkit_data.wc.add_shipping_info.fired = true;
}

function gtmkitPaymentEvent() {
	if (window.gtmkit_data.wc.add_payment_info.fired === true) return;

	let paymentElement;
	const datalayerName = window.gtmkit_settings.datalayer_name;

	paymentElement = document.querySelector('.payment_methods input:checked');
	if (!paymentElement) {
		paymentElement = document.querySelector('input[name^=payment_method]'); // select the first payment method
	}

	const paymentType = paymentElement
		? paymentElement.value
		: window.gtmkit_settings.wc.text['payment-method-not-found'];

	window[datalayerName].push({ ecommerce: null });
	window[datalayerName].push({
		event: 'add_payment_info',
		ecommerce: {
			currency: window.gtmkit_data.wc.currency,
			value: window.gtmkit_data.wc.cart_value,
			paymentType,
			payment_type: paymentType,
			items: window.gtmkit_data.wc.cart_items,
		},
	});

	window.gtmkit_data.wc.add_payment_info.fired = true;
}
