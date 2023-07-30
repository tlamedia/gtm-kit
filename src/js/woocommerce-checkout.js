if (document.readyState === 'loading') {
	document.addEventListener("DOMContentLoaded", gtmkit_load_checkout);
} else {
	gtmkit_load_checkout();
}

function gtmkit_load_checkout() {
	if (gtmkit.data.wc['is_cart']) gtmkit_cart();
	if (gtmkit.data.wc['is_checkout']) gtmkit_checkout();
}

function gtmkit_cart() {
	document.addEventListener('click', function (e) {
		let event_target_element = e.target;
		if (!event_target_element || !event_target_element.closest('[name=update_cart]')) return true;

		gtmkit_cart_quantity_change();
	});

	document.addEventListener('keypress', function (e) {
		let event_target_element = e.target;
		if (!event_target_element || !event_target_element.closest('.woocommerce-cart-form input[type=number]')) return true;

		gtmkit_cart_quantity_change();
	});
}

function gtmkit_cart_quantity_change() {
	document.querySelectorAll('.product-quantity input.qty').forEach(function (qty_element) {
		const default_value = qty_element.defaultValue;
		let current_value = parseInt(qty_element.value);
		if (isNaN(current_value)) current_value = default_value;

		if (default_value !== current_value) {
			const cart_item = qty_element.closest('.cart_item');
			const product_data = cart_item && cart_item.querySelector('.remove');
			if (!product_data) return;
			const item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));

			if (default_value < current_value) { // quantity increase
				item_data.quantity = current_value - default_value;

				window[datalayer_name].push({ 'ecommerce': null });
				window[datalayer_name].push({
					'event': 'add_to_cart',
					'ecommerce': {
						'currency': gtmkit.data.wc['currency'],
						'value': item_data.price * (current_value - default_value),
						'items': [item_data]
					}
				});
			} else { // quantity decrease
				item_data.quantity = default_value - current_value;

				window[datalayer_name].push({ 'ecommerce': null });
				window[datalayer_name].push({
					'event': 'remove_from_cart',
					'ecommerce': {
						'currency': gtmkit.data.wc['currency'],
						'value': item_data.price * (default_value - current_value),
						'items': [item_data]
					}
				});
			}
		}
	});
}

function gtmkit_checkout() {
	if (gtmkit.settings.wc['add_shipping_info']['config'] === 0 && gtmkit.settings.wc['add_payment_info']['config'] === 0) return;

	if (gtmkit.settings.wc['add_shipping_info']['config'] === 2) {
		document.addEventListener('change', function (e) {
			let event_target_element = e.target;
			if (!event_target_element || !event_target_element.closest('input[name^=shipping_method]') && !event_target_element.closest('.wc-block-components-shipping-rates-control')) return true;

			gtmkit_shipping_event();
		});
	}

	if (gtmkit.settings.wc['add_payment_info']['config'] === 2) {
		document.addEventListener('change', function (e) {
			let event_target_element = e.target;
			if (!event_target_element || !event_target_element.closest('input[name=payment_method]') && !event_target_element.closest('.wc-block-checkout__payment-method')) return true;

			gtmkit_payment_event();
		});
	}

	document.addEventListener('click', function (e) {
		let event_target_element = e.target.closest('button');

		if (!event_target_element) {
			return true;
		}

		if (event_target_element.classList.contains('wc-block-components-checkout-place-order-button') || event_target_element.closest('button[name=woocommerce_checkout_place_order]')) {
			gtmkit_shipping_event();
			gtmkit_payment_event();
		} else {
			return true;
		}
	});
}


function gtmkit_shipping_event() {

	if (gtmkit.data.wc['add_shipping_info']['fired'] === true) return;

	let shipping_element;

	if (gtmkit.data.wc['block'] === true) {
		shipping_element = document.querySelector('.wc-block-components-shipping-rates-control input[type^=radio]:checked');
		if (!shipping_element) {
			shipping_element = document.querySelector('.wc-block-components-shipping-rates-control input[type^=radio]'); // select the first shipping method
		}
	} else {
		shipping_element = document.querySelector('input[name^=shipping_method]:checked');
		if (!shipping_element) {
			shipping_element = document.querySelector('input[name^=shipping_method]'); // select the first shipping method
		}
	}

	let shipping_tier = (shipping_element) ? shipping_element.value : gtmkit.settings.wc['text']['shipping-tier-not-found'];

	window[datalayer_name].push({ 'ecommerce': null });
	window[datalayer_name].push({
		'event': 'add_shipping_info',
		'ecommerce': {
			'currency': gtmkit.data.wc['currency'],
			'value': gtmkit.data.wc['cart_value'],
			'shipping_tier': shipping_tier,
			'items': gtmkit.data.wc['cart_items']
		}
	});

	gtmkit.data.wc['add_shipping_info']['fired'] = true;
}

function gtmkit_payment_event() {

	if (gtmkit.data.wc['add_payment_info']['fired'] === true) return;

	let payment_element;

	if (gtmkit.data.wc['block'] === true) {
		payment_element = document.querySelector('.wc-block-checkout__payment-method input[type^=radio]:checked');
		if (!payment_element) {
			payment_element = document.querySelector('.wc-block-checkout__payment-method input[type^=radio]'); // select the first shipping method
		}
	} else {
		payment_element = document.querySelector('.payment_methods input:checked');
		if (!payment_element) {
			payment_element = document.querySelector('input[name^=payment_method]'); // select the first payment method
		}
	}

	let payment_type = (payment_element) ? payment_element.value : gtmkit.settings.wc['text']['payment-method-not-found'];

	window[datalayer_name].push({ 'ecommerce': null });
	window[datalayer_name].push({
		'event': 'add_payment_info',
		'ecommerce': {
			'currency': gtmkit.data.wc['currency'],
			'value': gtmkit.data.wc['cart_value'],
			'payment_type': payment_type,
			'items': gtmkit.data.wc['cart_items']
		}
	});

	gtmkit.data.wc['add_payment_info']['fired'] = true;
}

