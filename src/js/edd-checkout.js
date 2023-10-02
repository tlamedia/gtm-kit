// eslint-disable-next-line no-undef
jQuery(document).ready(function ($) {
	$(document.body).on(
		'change',
		'.edd-item-quantity',
		gtmkitEddUpdateItemQuantity
	);

	function gtmkitEddUpdateItemQuantity() {
		const $this = $(this),
			quantity = parseInt($this.val()),
			key = $this.data('key'),
			downloadId = $this.closest('.edd_cart_item').data('download-id'),
			options = JSON.parse(
				$this
					.parent()
					.find('input[name="edd-cart-download-' + key + '-options"]')
					.val()
			);

		const cartItems = Object.entries(window.gtmkit_data.edd.cart_items);
		cartItems.forEach((item) => {
			if (item[1].download.download_id === downloadId) {
				if (typeof item[1].download.price_id !== 'undefined') {
					if (item[1].download.price_id === options.price_id) {
						Object.assign(
							window.gtmkit_data.edd.cart_items[item[0]],
							{ quantity }
						);
					}
				} else {
					Object.assign(window.gtmkit_data.edd.cart_items[item[0]], {
						quantity,
					});
				}
			}
		});
	}
});
