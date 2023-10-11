function gtmkitLoad() {
	const datalayerName = window.gtmkit_settings.datalayer_name;

	let selectedProductVariationData;

	const productBlockIndex = {
		'wp-block-handpicked-products': 1,
		'wp-block-product-best-sellers': 1,
		'wp-block-product-category': 1,
		'wp-block-product-new': 1,
		'wp-block-product-on-sale': 1,
		'wp-block-products-by-attribute': 1,
		'wp-block-product-tag': 1,
		'wp-block-product-top-rated': 1,
	};

	// Set list name and position on product blocks
	document
		.querySelectorAll('.wc-block-grid .wc-block-grid__product')
		.forEach(function (gridItem) {
			const productGrid = gridItem.closest('.wc-block-grid');
			const productData = gridItem.querySelector('.gtmkit_product_data');

			if (productGrid && productData) {
				const productGridClasses = productGrid.classList;

				if (productGridClasses) {
					for (const i in productBlockIndex) {
						if (productGridClasses.contains(i)) {
							const itemData = JSON.parse(
								productData.getAttribute(
									'data-gtmkit_product_data'
								)
							);
							itemData.item_list_name =
								window.gtmkit_settings.wc.text[i];
							itemData.index = productBlockIndex[i];
							productData.setAttribute(
								'data-gtmkit_product_data',
								JSON.stringify(itemData)
							);
							productBlockIndex[i]++;
						}
					}
				}
			}
		});

	// view_item_list event in product lists
	const productDataElements = document.querySelectorAll(
		'.gtmkit_product_data'
	);

	if (productDataElements.length) {
		const items = [];
		let itemData;

		productDataElements.forEach(function (productData) {
			itemData = JSON.parse(
				productData.getAttribute('data-gtmkit_product_data')
			);
			items.push(itemData);
		});

		window[datalayerName].push({ ecommerce: null });
		window[datalayerName].push({
			event: 'view_item_list',
			ecommerce: {
				items,
			},
		});
	}

	// add_to_cart event for simple products in product lists
	document.addEventListener('click', function (e) {
		const eventTargetElement = e.target;
		let event;

		if (!eventTargetElement) {
			return true;
		}

		if (
			eventTargetElement.closest(
				'.add_to_cart_button:not(.single_add_to_cart_button)'
			)
		) {
			event = 'add_to_cart';
		} else if (
			(eventTargetElement.closest('.products') ||
				eventTargetElement.closest('.wc-block-grid__products')) &&
			eventTargetElement.closest(
				'.add_to_wishlist, .tinvwl_add_to_wishlist_button:not(.tinvwl-product-in-list)'
			)
		) {
			event = 'add_to_wishlist';
		} else {
			return true;
		}

		const productElement = eventTargetElement.closest(
			'.product,.wc-block-grid__product'
		);
		const productData =
			productElement &&
			productElement.querySelector('.gtmkit_product_data');
		if (!productData) {
			return true;
		}

		const itemData = JSON.parse(
			productData.getAttribute('data-gtmkit_product_data')
		);

		window[datalayerName].push({ ecommerce: null });
		window[datalayerName].push({
			event,
			ecommerce: {
				currency: window.gtmkit_data.wc.currency,
				value: itemData.price,
				items: [itemData],
			},
		});
	});

	// add_to_cart event on product page
	document.addEventListener('click', function (e) {
		const eventTargetElement = e.target;
		let event;

		if (!eventTargetElement) {
			return true;
		}

		let addToCart;
		addToCart = eventTargetElement.closest('form.cart');

		if (
			addToCart &&
			eventTargetElement.closest(
				'.single_add_to_cart_button:not(.disabled,.input-needed)'
			)
		) {
			event = 'add_to_cart';
		} else if (
			addToCart &&
			eventTargetElement.closest(
				'.tinvwl_add_to_wishlist_button:not(.tinvwl-product-in-list,.disabled-add-wishlist)'
			)
		) {
			event = 'add_to_wishlist';
		} else {
			const addToWishlist = eventTargetElement.closest(
				'.yith-wcwl-add-to-wishlist'
			);

			if (addToWishlist) {
				addToCart = addToWishlist.parentNode.querySelector('form.cart');
				if (addToCart) {
					event = 'add_to_wishlist';
				}
			}
		}

		if (!event) {
			return true;
		}

		const productVariantId = addToCart.querySelectorAll(
			'[name=variation_id]'
		);
		const productIsGrouped =
			addToCart.classList && addToCart.classList.contains('grouped_form');

		if (productVariantId.length) {
			let quantity = 1;
			let price;
			if (selectedProductVariationData) {
				const quantityElement =
					addToCart.querySelector('[name=quantity]');
				selectedProductVariationData.quantity =
					(quantityElement && quantityElement.value) || 1;
				quantity = selectedProductVariationData.quantity;
				price = selectedProductVariationData.price;
			}

			if (
				(selectedProductVariationData && event === 'add_to_cart') ||
				event === 'add_to_wishlist'
			) {
				window[datalayerName].push({ ecommerce: null });
				window[datalayerName].push({
					event,
					ecommerce: {
						currency: window.gtmkit_data.wc.currency,
						value: price * quantity,
						items: [selectedProductVariationData],
					},
				});
			}
		} else if (productIsGrouped) {
			const productsInGroup = document.querySelectorAll(
				'.grouped_form .gtmkit_product_data'
			);
			const products = [];
			let value = 0;

			productsInGroup.forEach(function (productData) {
				let productQuantity = document.querySelectorAll(
					'input[name=quantity\\[' +
						productData.getAttribute('data-gtmkit_product_id') +
						'\\]]'
				);
				productQuantity = parseInt(productQuantity[0].value);

				if (0 === productQuantity) {
					return true;
				}

				const itemData = JSON.parse(
					productData.getAttribute('data-gtmkit_product_data')
				);

				itemData.quantity = productQuantity;

				products.push(itemData);
				value += itemData.price * itemData.quantity;
			});

			if (0 === products.length) {
				return true;
			}

			window[datalayerName].push({ ecommerce: null });
			window[datalayerName].push({
				event,
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value,
					items: products,
				},
			});
		} else {
			const itemData = JSON.parse(
				addToCart.querySelector('[name=gtmkit_product_data]') &&
					addToCart.querySelector('[name=gtmkit_product_data]').value
			);

			itemData.quantity =
				addToCart.querySelector('[name=quantity]') &&
				addToCart.querySelector('[name=quantity]').value;

			window[datalayerName].push({ ecommerce: null });
			window[datalayerName].push({
				event,
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: itemData.price * itemData.quantity,
					items: [itemData],
				},
			});
		}
	});

	// remove_from_cart event on cart remove links
	document.addEventListener('click', function (e) {
		const productData = e.target;

		if (
			!productData ||
			!productData.closest(
				'.mini_cart_item a.remove,.product-remove a.remove'
			)
		) {
			return true;
		}

		const itemData = JSON.parse(
			productData.getAttribute('data-gtmkit_product_data')
		);

		if (!itemData) return true;

		window[datalayerName].push({
			event: 'remove_from_cart',
			ecommerce: {
				items: [itemData],
			},
		});
	});

	// select_item event on clicks in product lists
	const productListItemSelector =
		'.products li:not(.product-category) a:not(.add_to_cart_button,.add_to_wishlist,.tinvwl_add_to_wishlist_button),' +
		'.wc-block-grid__products li:not(.product-category) a:not(.add_to_cart_button,.add_to_wishlist,.tinvwl_add_to_wishlist_button),' +
		'.woocommerce-grouped-product-list-item__label a:not(.add_to_wishlist,.tinvwl_add_to_wishlist_button)';
	document.addEventListener('click', function (e) {
		const eventTargetElement = e.target;
		const linkElement = eventTargetElement.closest(productListItemSelector);
		if (!linkElement) return true;

		const product = eventTargetElement.closest(
			'.product,.wc-block-grid__product'
		);

		let productData;

		if (product) {
			productData = product.querySelector('.gtmkit_product_data');
			if (!productData) return true;
		} else {
			return true;
		}

		if (
			'undefined' ===
			typeof productData.getAttribute('data-gtmkit_product_data')
		) {
			return true;
		}

		const itemData = JSON.parse(
			productData.getAttribute('data-gtmkit_product_data')
		);

		if (!itemData) return true;

		window[datalayerName].push({
			event: 'select_item',
			ecommerce: {
				items: [itemData],
			},
		});
	});

	// track product variations on product page
	// eslint-disable-next-line no-undef
	jQuery(document).on('found_variation', function (event, productVariation) {
		if ('undefined' === typeof productVariation) return;

		const variationsForm = event.target;
		const gtmkitElement = variationsForm.querySelector('[name=gtmkit_product_data]');

		// Check if the gtmkit_product_data exists and bail early if it doesn't
		if (!gtmkitElement) return;

		const productVariationData = JSON.parse(
			variationsForm.querySelector('[name=gtmkit_product_data]') &&
				variationsForm.querySelector('[name=gtmkit_product_data]').value
		);

		productVariationData.id = productVariationData.item_id = productVariation.variation_id;
		if (
			window.gtmkit_settings.wc.use_sku &&
			productVariation.sku &&
			'' !== productVariation.sku
		) {
			productVariationData.id = productVariationData.item_id = productVariation.sku;
		}

		productVariationData.price = productVariation.display_price;

		const productAttributes = [];
		for (const attribKey in productVariation.attributes) {
			productAttributes.push(productVariation.attributes[attribKey]);
		}
		productVariationData.item_variant = productAttributes
			.filter((n) => n)
			.join('|');
		selectedProductVariationData = productVariationData;

		if (window.gtmkit_settings.wc.view_item.config !== 0) {
			window[datalayerName].push({ ecommerce: null });
			window[datalayerName].push({
				event: 'view_item',
				ecommerce: {
					currency: window.gtmkit_data.wc.currency,
					value: productVariationData.price,
					items: [productVariationData],
				},
			});
		}
	});
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', gtmkitLoad);
} else {
	gtmkitLoad();
}
