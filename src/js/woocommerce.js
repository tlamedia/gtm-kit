const datalayer_name = gtmkit.settings.datalayer_name;

function gtmkit_load() {

	let selected_product_variation_data;

	let product_block_index = {
		'wp-block-handpicked-products': 1,
		'wp-block-product-best-sellers': 1,
		'wp-block-product-category': 1,
		'wp-block-product-new': 1,
		'wp-block-product-on-sale': 1,
		'wp-block-products-by-attribute': 1,
		'wp-block-product-tag': 1,
		'wp-block-product-top-rated': 1,
	}

	// Set list name and position on product blocks
	document.querySelectorAll('.wc-block-grid .wc-block-grid__product').forEach(function (grid_item) {

		const product_grid = grid_item.closest('.wc-block-grid');
		const product_data = grid_item.querySelector('.gtmkit_product_data');

		if (product_grid && product_data) {

			const product_grid_classes = product_grid.classList;

			if (product_grid_classes) {

				for (let i in product_block_index) {
					if (product_grid_classes.contains(i)) {
						let item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));
						item_data.item_list_name = gtmkit.settings.wc.text[i];
						item_data.index = product_block_index[i];
						product_data.setAttribute("data-gtmkit_product_data", JSON.stringify(item_data));
						product_block_index[i]++;
					}
				}
			}
		}
	});

	// view_item_list event in product lists
	let product_data_elements = document.querySelectorAll('.gtmkit_product_data');

	if (product_data_elements.length) {
		let items = [];
		let item_data;

		product_data_elements.forEach(function (product_data) {
			item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));
			items.push(item_data);
		});

		window[datalayer_name].push({ 'ecommerce': null });
		window[datalayer_name].push({
			'event': 'view_item_list',
			'ecommerce': {
				'items': items
			}
		});
	}

	// add_to_cart event for simple products in product lists
	document.addEventListener('click', function (e) {
		let event_target_element = e.target;
		let event;

		if (!event_target_element) {
			return true;
		}

		if (event_target_element.closest('.add_to_cart_button:not(.single_add_to_cart_button)')) {
			event = 'add_to_cart';
		} else if ((event_target_element.closest('.products') || event_target_element.closest('.wc-block-grid__products')) && event_target_element.closest('.add_to_wishlist, .tinvwl_add_to_wishlist_button:not(.tinvwl-product-in-list)')) {
			event = 'add_to_wishlist';
		} else {
			return true;
		}

		const product_element = event_target_element.closest('.product,.wc-block-grid__product');
		const product_data = product_element && product_element.querySelector('.gtmkit_product_data');
		if (!product_data) {
			return true;
		}

		const item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));

		window[datalayer_name].push({ 'ecommerce': null });
		window[datalayer_name].push({
			'event': event,
			'ecommerce': {
				'currency': gtmkit.data.wc.currency,
				'value': item_data.price,
				'items': [item_data]
			}
		});
	});

	// add_to_cart event on product page
	document.addEventListener('click', function (e) {
		let event_target_element = e.target;
		let event;

		if (!event_target_element) {
			return true;
		}

		let add_to_cart;
		add_to_cart = event_target_element.closest('form.cart');

		if (add_to_cart && event_target_element.closest('.single_add_to_cart_button:not(.disabled,.input-needed)')) {
			event = 'add_to_cart';
		} else {
			if (add_to_cart && event_target_element.closest('.tinvwl_add_to_wishlist_button:not(.tinvwl-product-in-list,.disabled-add-wishlist)')) {
				event = 'add_to_wishlist';
			} else {
				const add_to_wishlist = event_target_element.closest('.yith-wcwl-add-to-wishlist');

				if (add_to_wishlist) {
					add_to_cart = add_to_wishlist.parentNode.querySelector('form.cart');
					if (add_to_cart) {
						event = 'add_to_wishlist';
					}
				}
			}
		}

		if (! event) {
			return true;
		}

		let product_variant_id = add_to_cart.querySelectorAll('[name=variation_id]');
		let product_is_grouped = add_to_cart.classList && add_to_cart.classList.contains('grouped_form');

		if (product_variant_id.length) {
			let quantity = 1;
			let price;
			if (selected_product_variation_data) {
				const quantity_element = add_to_cart.querySelector('[name=quantity]');
				selected_product_variation_data.quantity = (quantity_element && quantity_element.value) || 1;
				quantity = selected_product_variation_data.quantity;
				price = selected_product_variation_data.price;
			}

			if ((selected_product_variation_data && event == 'add_to_cart') || event == 'add_to_wishlist') {
				window[datalayer_name].push({'ecommerce': null});
				window[datalayer_name].push({
					'event': event,
					'ecommerce': {
						'currency': gtmkit.data.wc.currency,
						'value': price * quantity,
						'items': [selected_product_variation_data]
					}
				});
			}
		} else if (product_is_grouped) {
			const products_in_group = document.querySelectorAll('.grouped_form .gtmkit_product_data');
			let products = [];
			let value = 0;

			products_in_group.forEach(function (product_data) {

				let product_quantity = document.querySelectorAll('input[name=quantity\\[' + product_data.getAttribute('data-gtmkit_product_id') + '\\]]');
				product_quantity = parseInt(product_quantity[0].value);

				if (0 === product_quantity) {
					return true;
				}

				let item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));

				item_data.quantity = product_quantity;

				products.push(item_data);
				value += item_data.price * item_data.quantity;
			});

			if (0 === products.length) {
				return true;
			}

			window[datalayer_name].push({ 'ecommerce': null });
			window[datalayer_name].push({
				'event': event,
				'ecommerce': {
					'currency': gtmkit.data.wc.currency,
					'value': value,
					'items': products
				}
			});
		} else {

			let item_data = JSON.parse(add_to_cart.querySelector('[name=gtmkit_product_data]') && add_to_cart.querySelector('[name=gtmkit_product_data]').value);

			item_data.quantity = add_to_cart.querySelector('[name=quantity]') && add_to_cart.querySelector('[name=quantity]').value;

			window[datalayer_name].push({ 'ecommerce': null });
			window[datalayer_name].push({
				'event': event,
				'ecommerce': {
					'currency': gtmkit.data.wc.currency,
					'value': item_data.price * item_data.quantity,
					'items': [item_data]
				}
			});
		}
	});

	// remove_from_cart event on cart remove links
	document.addEventListener('click', function (e) {
		const product_data = e.target;

		if (!product_data || !product_data.closest('.mini_cart_item a.remove,.product-remove a.remove')) {
			return true;
		}

		const item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));

		if (!item_data) return true;

		window[datalayer_name].push({
			'event': 'remove_from_cart',
			'ecommerce': {
				'items': [item_data]
			}
		});
	});

	// select_item event on clicks in product lists
	let product_list_item_selector = '.products li:not(.product-category) a:not(.add_to_cart_button,.add_to_wishlist,.tinvwl_add_to_wishlist_button),'
		+ '.wc-block-grid__products li:not(.product-category) a:not(.add_to_cart_button,.add_to_wishlist,.tinvwl_add_to_wishlist_button),'
		+ '.woocommerce-grouped-product-list-item__label a:not(.add_to_wishlist,.tinvwl_add_to_wishlist_button)'
	document.addEventListener('click', function (e) {

		const event_target_element = e.target;
		const link_element = event_target_element.closest(product_list_item_selector);
		if (!link_element) return true;

		let product = event_target_element.closest('.product,.wc-block-grid__product');

		let product_data

		if (product) {
			product_data = product.querySelector('.gtmkit_product_data');
		} else {
			return true;
		}

		if (('undefined' == typeof product_data.getAttribute('data-gtmkit_product_data'))) {
			return true;
		}

		const item_data = JSON.parse(product_data.getAttribute('data-gtmkit_product_data'));

		if (!item_data) return true;

		window[datalayer_name].push({
			'event': 'select_item',
			'ecommerce': {
				'items': [item_data],
			}
		});
	});

	// track product variations on product page
	jQuery(document).on('found_variation', function (event, product_variation) {
		if ("undefined" == typeof product_variation) return;

		const variations_form = event.target;
		let product_variation_data = JSON.parse(variations_form.querySelector('[name=gtmkit_product_data]') && variations_form.querySelector('[name=gtmkit_product_data]').value);

		product_variation_data.item_id = product_variation.variation_id;
		if (gtmkit.settings.wc['use_sku'] && product_variation.sku && ('' !== product_variation.sku)) {
			product_variation_data.item_id = product_variation.sku;
		}

		product_variation_data.price = product_variation.display_price;

		let product_attributes = [];
		for (let attrib_key in product_variation.attributes) {
			product_attributes.push(product_variation.attributes[attrib_key]);
		}
		product_variation_data.item_variant = product_attributes.filter(n => n).join('|');
		selected_product_variation_data = product_variation_data;

		if (gtmkit.settings.wc['view_item']['config'] !== 0) {
			window[datalayer_name].push({ 'ecommerce': null });
			window[datalayer_name].push({
				'event': 'view_item',
				'ecommerce': {
					'currency': gtmkit.data.wc.currency,
					'value': product_variation_data.price,
					'items': [product_variation_data]
				}
			});
		}

	});
}

if (document.readyState === 'loading') {
	document.addEventListener("DOMContentLoaded", gtmkit_load);
} else {
	gtmkit_load();
}
