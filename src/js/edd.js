// eslint-disable-next-line no-undef
jQuery(document).ready(function ($) {
	const datalayerName = window.gtmkit_settings.datalayer_name;

	$(document.body).on('click.eddAddToCart', '.edd-add-to-cart', function (e) {
		e.preventDefault();

		const $this = $(this);

		const form = $this.parents('form').last();
		const download = $this.data('download-id');
		const variablePrice = $this.data('variable-price');
		const itemPriceIds = [];

		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const itemData = JSON.parse(form.find('.gtmkit_product_data').val());

		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		let quantity = parseInt(form.find('.edd-item-quantity').val());

		if (variablePrice === 'yes') {
			if (
				!form.find('.edd_price_option_' + download + ':checked', form)
					.length
			) {
				return false;
			}

			const priceMode = $this.data('price-mode');

			form.find('.edd_price_option_' + download + ':checked', form).each(
				function (index) {
					itemPriceIds[index] = $(this).val();

					const itemPrice = $(this).data('price');
					if (itemPrice && itemPrice > 0) {
						itemData.price = parseFloat(itemPrice);
					}

					if (priceMode === 'multi') {
						quantity = parseInt(
							form
								.find(
									'.edd-item-quantity' +
										'[name="edd_download_quantity_' +
										$(this).val() +
										'"]'
								)
								.val()
						);
						itemData.quantity = quantity;
					} else {
						itemData.quantity = quantity;
					}

					pushDatalayer(itemData);
				}
			);
		} else {
			itemData.quantity = quantity;
			pushDatalayer(itemData);
		}
	});

	function pushDatalayer(itemData) {
		window[datalayerName].push({ ecommerce: null });
		window[datalayerName].push({
			event: 'add_to_cart',
			ecommerce: {
				currency: window.gtmkit_data.edd.currency,
				value: itemData.price * itemData.quantity,
				items: [itemData],
			},
		});
	}
});
