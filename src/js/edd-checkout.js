jQuery(document).ready(function ($) {

	const edd = window["gtmkit_settings"].edd;
	const datalayer_name = window["gtmkit_settings"].datalayer_name;

	$(document.body).on('change', '.edd-item-quantity', gtmkit_edd_update_item_quantity);

	function gtmkit_edd_update_item_quantity(event) {
		const $this = $(this),
			quantity = parseInt($this.val()),
			key = $this.data('key'),
			download_id = $this.closest('.edd_cart_item').data('download-id'),
			options = JSON.parse($this.parent().find('input[name="edd-cart-download-' + key + '-options"]').val());

		const cart_items = Object.entries(edd.cart_items);
		cart_items.forEach(item => {
			if (item[1].download.download_id == download_id) {
				if (typeof item[1].download.price_id !== 'undefined') {
					if (item[1].download.price_id == options.price_id) {
						Object.assign(edd.cart_items[item[0]], {quantity: quantity});
					}
				} else {
					Object.assign(edd.cart_items[item[0]], {quantity: quantity});
				}
			}
		});
	}
});
