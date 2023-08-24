jQuery( document ).ready( function ( $ ) {
	const datalayer_name = gtmkit_settings.datalayer_name;

	$( document.body ).on(
		'click.eddAddToCart',
		'.edd-add-to-cart',
		function ( e ) {
			e.preventDefault();

			const $this = $( this );

			const form = $this.parents( 'form' ).last();
			const download = $this.data( 'download-id' );
			const variable_price = $this.data( 'variable-price' );
			const price_mode = $this.data( 'price-mode' );
			const item_price_ids = [];
			const item_data = JSON.parse(
				form.find( '.gtmkit_product_data' ).val()
			);

			let quantity = parseInt( form.find( '.edd-item-quantity' ).val() );

			if ( variable_price === 'yes' ) {
				if (
					! form.find(
						'.edd_price_option_' + download + ':checked',
						form
					).length
				) {
					return false;
				}

				form.find(
					'.edd_price_option_' + download + ':checked',
					form
				).each( function ( index ) {
					item_price_ids[ index ] = $( this ).val();

					const item_price = $( this ).data( 'price' );
					if ( item_price && item_price > 0 ) {
						item_data.price = parseFloat( item_price );
					}

					if ( price_mode === 'multi' ) {
						quantity = parseInt(
							form
								.find(
									'.edd-item-quantity' +
										'[name="edd_download_quantity_' +
										$( this ).val() +
										'"]'
								)
								.val()
						);
						item_data.quantity = quantity;
					} else {
						item_data.quantity = quantity;
					}

					push_datalayer( item_data );
				} );
			} else {
				item_data.quantity = quantity;
				push_datalayer( item_data );
			}
		}
	);

	function push_datalayer( item_data ) {
		window[ datalayer_name ].push( { ecommerce: null } );
		window[ datalayer_name ].push( {
			event: 'add_to_cart',
			ecommerce: {
				currency: gtmkit_data.edd.currency,
				value: item_data.price * item_data.quantity,
				items: [ item_data ],
			},
		} );
	}
} );
