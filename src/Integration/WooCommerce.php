<?php
/**
 * WooCommerce.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Options;
use WC_Order;
use WC_Product;

/**
 * WooCommerce integration
 */
class WooCommerce  extends AbstractEcommerce {

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->store_currency = get_woocommerce_currency();

		// Call parent constructor.
		parent::__construct( $options );
	}

	/**
	 * Get instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			$options        = new Options();
			self::$instance = new self( $options );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {

		self::$instance = new self( $options );

		add_filter( 'gtmkit_header_script_settings', [ self::$instance, 'set_global_settings' ] );
		add_filter( 'gtmkit_datalayer_content', [ self::$instance, 'get_datalayer_content' ] );
		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );

		// Add-to-cart tracking
		add_action( 'woocommerce_after_add_to_cart_button', [
			self::$instance,
			'single_product_add_to_cart_tracking'
		] );
		add_filter( 'woocommerce_grouped_product_list_column_label', [
			self::$instance,
			'grouped_product_add_to_cart_tracking'
		], 10, 2 );
		add_filter( 'woocommerce_blocks_product_grid_item_html', [
			self::$instance,
			'product_block_add_to_cart_tracking'
		], 10, 3 );
		add_action( 'woocommerce_after_shop_loop_item', [ self::$instance, 'product_list_loop_add_to_cart_tracking' ] );
		add_filter( 'woocommerce_cart_item_remove_link', [ self::$instance, 'cart_item_remove_link' ], 10, 2 );

		// Set list name in WooCommerce loop
		add_filter( 'woocommerce_product_loop_start', [ self::$instance, 'set_list_name_on_category_and_tag' ] );
		add_filter( 'woocommerce_related_products_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop' ] );
		add_filter( 'woocommerce_cross_sells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop' ] );
		add_filter( 'woocommerce_upsells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop' ] );
		add_action( 'woocommerce_shortcode_before_best_selling_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_featured_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_recent_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_related_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_sale_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_top_rated_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_action( 'woocommerce_shortcode_before_product_category_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'woocommerce_dequeue_script' ) ) {
			return;
		}

		if ( wp_get_environment_type() == 'local' ) {
			$version = time();
		} else {
			$version = GTMKIT_VERSION;
		}

		wp_enqueue_script(
			'gtmkit-woocommerce',
			GTMKIT_URL . 'assets/js/woocommerce.js',
			[],
			$version,
			true
		);


		if ( is_cart() || is_checkout() ) {
			wp_enqueue_script(
				'gtmkit-woocommerce-checkout',
				GTMKIT_URL . 'assets/js/woocommerce-checkout.js',
				[ 'gtmkit-woocommerce' ],
				$version,
				true
			);
		}
	}

	/**
	 * Set the global script settings
	 *
	 * @param array $global_settings Plugin settings
	 *
	 * @return array
	 */
	public function set_global_settings( array $global_settings ): array {

		$global_settings['wc']['currency']                    = $this->store_currency;
		$global_settings['wc']['is_cart']                     = is_cart();
		$global_settings['wc']['is_checkout']                 = ( is_checkout() && ! is_order_received_page() );
		$global_settings['wc']['use_sku']                     = (bool) $this->options->get( 'integrations', 'woocommerce_use_sku' );
		$global_settings['wc']['add_shipping_info']['config'] = (int) Options::init()->get( 'integrations', 'woocommerce_shipping_info' );
		$global_settings['wc']['add_shipping_info']['fired']  = false;
		$global_settings['wc']['add_payment_info']['config']  = (int) Options::init()->get( 'integrations', 'woocommerce_payment_info' );
		$global_settings['wc']['add_payment_info']['fired']   = false;
		$global_settings['wc']['view_item']['config']         = (int) Options::init()->get( 'integrations', 'woocommerce_variable_product_tracking' );
		$global_settings['wc']['text']                        = [
			'wp-block-handpicked-products'   => __( 'Handpicked Products', 'gtm-kit' ),
			'wp-block-product-best-sellers'  => __( 'Best Sellers', 'gtm-kit' ),
			'wp-block-product-category'      => __( 'Product Category', 'gtm-kit' ),
			'wp-block-product-new'           => __( 'New Products', 'gtm-kit' ),
			'wp-block-product-on-sale'       => __( 'Products On Sale', 'gtm-kit' ),
			'wp-block-products-by-attribute' => __( 'Products By Attribute', 'gtm-kit' ),
			'wp-block-product-tag'           => __( 'Product Tag', 'gtm-kit' ),
			'wp-block-product-top-rated'     => __( 'Top Rated Products', 'gtm-kit' ),
			'shipping tier not found'        => __( 'Shipping tier not found', 'gtm-kit' ),
			'payment method not found'       => __( 'Payment method not found', 'gtm-kit' ),
		];

		if ( is_checkout() && ! is_order_received_page() ) {
			$global_settings['wc']['cart_items'] = $this->get_cart_items( 'begin_checkout' );
			$global_settings['wc']['cart_value'] = WC()->cart->cart_contents_total;
			$global_settings['wc']['block'] = has_block('woocommerce/checkout');
		}

		$this->global_settings = $global_settings;

		return $global_settings;
	}

	/**
	 * Get the WooCommerce dataLayer content
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content( array $data_layer ): array {

		if ( is_product() ) {
			$data_layer = $this->get_datalayer_content_product_page( $data_layer );
		} elseif ( is_product_category() ) {
			$data_layer = $this->get_datalayer_content_product_category( $data_layer );
		} elseif ( is_product_tag() ) {
			$data_layer = $this->get_datalayer_content_product_tag( $data_layer );
		} elseif ( is_cart() ) {
			$data_layer = $this->get_datalayer_content_cart( $data_layer );
		} elseif ( is_order_received_page() ) {
			$data_layer = $this->get_datalayer_content_order_received( $data_layer );
		} elseif ( is_checkout() ) {
			$data_layer = $this->get_datalayer_content_checkout( $data_layer );
		}

		if ( $this->options->get( 'integrations', 'woocommerce_include_permalink_structure' ) ) {
			$wc_permalink_structure           = wc_get_permalink_structure();
			$data_layer['permalinkStructure'] = [
				'productBase'   => $wc_permalink_structure['product_base'],
				'categoryBase'  => $wc_permalink_structure['category_base'],
				'tagBase'       => $wc_permalink_structure['tag_base'],
				'attributeBase' => $wc_permalink_structure['attribute_base'],
			];
		}

		if ( $this->options->get( 'integrations', 'woocommerce_include_pages' ) ) {
			$data_layer['pages'] = [
				'cart'          => str_replace( home_url(), '', wc_get_cart_url() ),
				'checkout'      => str_replace( home_url(), '', wc_get_checkout_url() ),
				'orderReceived' => str_replace( home_url(), '', wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() ) ),
				'myAccount'     => str_replace( home_url(), '', get_permalink( wc_get_page_id( 'myaccount' ) ) ),
			];
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product pages
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_page( array $data_layer ): array {

		$product = wc_get_product( get_the_ID() );

		if ( ! ( $product instanceof WC_Product ) ) {
			return $data_layer;
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-page';
		}

		$item = $this->get_item_data( $product );

		$data_layer['productType'] = $product->get_type();
		$data_layer['event']       = 'view_item';
		$data_layer['ecommerce']   = [
			'items' => [ $item ],
			'value' => (float) $item['price']
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for category pages
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_category( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-category';
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product tag pages
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_product_tag( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-tag';
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for cart page
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_cart( array $data_layer ): array {

		if ( wc_prices_include_tax() ) {
			$cart_value = WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();
		} else {
			$cart_value = WC()->cart->get_cart_contents_total();
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'cart';
		}

		$data_layer['event']     = 'view_cart';
		$data_layer['ecommerce'] = [
			'currency' => $this->store_currency,
			'value'    => (float) $cart_value,
			'items'    => [ $this->get_cart_items( 'view_cart' ) ]
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for checkout page
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_checkout( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'checkout';
		}

		$data_layer['event']     = 'begin_checkout';
		$data_layer['ecommerce'] = [
			'items' => $this->global_settings['wc']['cart_items']
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for order_received page
	 *
	 * @param array $data_layer The datalayer content
	 *
	 * @return array The datalayer content
	 */
	public function get_datalayer_content_order_received( array $data_layer ): array {

		global $wp;

		$order_id = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );

		if ( ! $order_id ) {
			return $data_layer;
		}

		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {

			$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ) );

			if ( $order->get_order_key() !== $order_key ) {
				return $data_layer;
			}

			if ( ( 'failed' === $order->get_status() ) ) {
				return $data_layer;
			}

			if ( ( 1 === (int) $order->get_meta('_gtmkit_order_tracked') ) ) {
				return $data_layer;
			}
		} else {
			return $data_layer;
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'order-received';
		}

		if ( $this->options->get( 'integrations', 'woocommerce_exclude_tax' ) ) {
			$order_value = $order->get_total() - $order->get_total_tax();
		} else {
			$order_value = $order->get_total();
		}

		$shipping_total = $order->get_shipping_total();
		if ( $this->options->get( 'integrations', 'woocommerce_exclude_shipping' ) ) {
			$order_value -= $shipping_total;
		}

		$order_items = [];

		if ( $items = $order->get_items() ) {
			foreach ( $items as $item ) {

				$product       = $item->get_product();
				$inc_tax       = ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) );
				$product_price = round( $order->get_item_total( $item, $inc_tax ), 2 );

				$order_items[] = $this->get_item_data(
					$product,
					[
						'quantity' => $item->get_quantity(),
						'price'    => $product_price
					],
					'purchase'
				);
			}
		}

		$data_layer['event']     = 'purchase';
		$data_layer['ecommerce'] = [
			'transaction_id' => (int) $order->get_order_number(),
			'value'          => (float) $order_value,
			'tax'            => (float) $order->get_total_tax(),
			'shipping'       => (float) $shipping_total,
			'currency'       => $order->get_currency(),
			'items'          => $order_items
		];

		$order->add_meta_data( '_gtmkit_order_tracked', 1 );
		$order->save();

		return apply_filters( 'gtmkit_datalayer_content_order_received', $data_layer );
	}

	/**
	 * Get cart items.
	 *
	 * @param string $event_context The event context of the item data.
	 *
	 * @return array The cart items.
	 */
	function get_cart_items( string $event_context ): array {
		$cart_items = [];

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product      = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$cart_items[] = $this->get_item_data( $product, [ 'quantity' => $cart_item['quantity'] ], $event_context );
		}

		return $cart_items;
	}

	/**
	 * Get item data.
	 *
	 * @param WC_Product $product An instance of WP_Product
	 * @param array $additional_item_attributes Any key-value pair that needs to be added to the item data.
	 * @param string $event_context The event context of the item data.
	 *
	 * @return array The item data.
	 */
	function get_item_data( WC_Product $product, array $additional_item_attributes = [], string $event_context = '' ): array {

		$product_id_to_query = ( $product->get_type() === 'variation' ) ? $product->get_parent_id() : $product->get_id();

		if ( $this->options->get( 'integrations', 'woocommerce_use_sku' ) ) {
			$item_id = $product->get_sku() ?: $product->get_id();
		} else {
			$item_id = $product->get_id();
		}

		$item_data = [
			'id'   => $this->prefix_item_id( $item_id ),
			'item_id'   => $this->prefix_item_id( $item_id ),
			'item_name' => $product->get_title(),
			'currency'  => $this->store_currency,
			'price'     => round( (float) wc_get_price_to_display( $product ), 2 ),
		];

		if ( $this->options->get( 'integrations', 'woocommerce_brand' ) ) {
			$item_data['item_brand'] = $this->get_product_term(
				$product_id_to_query,
				$this->options->get( 'integrations', 'woocommerce_brand' )
			);
		}

		if ( $this->options->get( 'integrations', 'woocommerce_google_business_vertical' ) ) {
			$item_data['google_business_vertical'] = $this->options->get( 'integrations', 'woocommerce_google_business_vertical' );
		}

		$item_category_elements = $this->get_primary_product_category( $product_id_to_query, 'product_cat' );

		$number_of_elements = count( $item_category_elements );

		if ( $number_of_elements ) {

			for ( $element = 0; $element < $number_of_elements; $element ++ ) {
				$designator                                 = ( $element == 0 ) ? '' : $element + 1;
				$item_data[ 'item_category' . $designator ] = $item_category_elements[ $element ];
			}

		}

		$item_data = array_merge( $item_data, $additional_item_attributes );

		return apply_filters( 'gtmkit_datalayer_item_data', $item_data, $product, $event_context );
	}


	/**
	 * Add-to-cart tracing on single product.
	 *
	 * @hook woocommerce_after_add_to_cart_button
	 *
	 * @return void
	 */
	function single_product_add_to_cart_tracking(): void {
		global $product;

		$item_data = $this->get_item_data( $product );

		echo '<input type="hidden" name="gtmkit_product_data' . '" value="' . esc_attr( json_encode( $item_data ) ) . '" />' . "\n";
	}

	/**
	 * Add-to-cart tracking on grouped product.
	 *
	 * @hook woocommerce_grouped_product_list_column_label
	 *
	 * @param string $label_value Product label.
	 * @param WC_Product $product The product.
	 *
	 * @return string The product label string.
	 */
	function grouped_product_add_to_cart_tracking( string $label_value, WC_Product $product ): string {

		$label_value .= $this->get_item_data_tag( $product, __( 'Grouped Product', 'gtm-kit' ), $this->grouped_product_position ++ );

		return $label_value;
	}

	/**
	 * Add-to-cart tracking on product blocks
	 *
	 * @hook woocommerce_blocks_product_grid_item_html.
	 *
	 * @param string $html Product grid item HTML.
	 * @param object $data Product data passed to the template.
	 * @param WC_Product $product Product object.
	 *
	 * @return string Updated product grid item HTML.
	 */
	function product_block_add_to_cart_tracking( string $html, object $data, WC_Product $product ): string {
		$item_data_tag = $this->get_item_data_tag( $product, '', 0 );

		return preg_replace( '/<li.+class=("|"[^"]+)wc-block-grid__product("|[^"]+")[^<]*>/i', '$0' . $item_data_tag, $html );
	}

	/**
	 * Generates a hidden <span> element that contains the item data.
	 *
	 * @param WC_Product $product Product object.
	 * @param string $item_list_name Name of the list associated with the event.
	 * @param int $index The index of the product in the product list. The first product should have the index no. 1.
	 *
	 * @return string A hidden <span> element that contains the item data.
	 */
	function get_item_data_tag( WC_Product $product, string $item_list_name, int $index ): string {

		if ( empty( $item_list_name ) ) {
			$item_list_name = ( is_search() ) ? __( 'Search Results', 'gtm-kit' ) : __( 'General Product List', 'gtm-kit' );
		}

		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$posts_per_page = get_query_var( 'posts_per_page' );
		if ( $posts_per_page < 1 ) {
			$posts_per_page = 1;
		}

		$index = $index + ( $posts_per_page * ( $paged - 1 ) );

		$item_data = $this->get_item_data(
			$product,
			[
				'item_list_name' => $item_list_name,
				'index'          => $index,
			],
			'product_list'
		);

		return sprintf(
			'<span class="gtmkit_product_data" style="display:none; visibility:hidden;" data-gtmkit_product_id="%s" data-gtmkit_product_data="%s"></span>',
			esc_attr( $this->prefix_item_id( $product->get_id() ) ),
			esc_attr( json_encode( $item_data ) )
		);
	}

	/**
	 * Add-to-cart tracking in product list loop
	 *
	 * @hook woocommerce_after_shop_loop_item.
	 *
	 * @return void
	 */
	function product_list_loop_add_to_cart_tracking(): void {
		global $product, $woocommerce_loop;

		if ( isset( $woocommerce_loop['gtmkit_list_name'] ) && ! empty( $woocommerce_loop['gtmkit_list_name'] ) ) {
			$list_name = $woocommerce_loop['gtmkit_list_name'];
		} else {
			$list_name = __( 'General Product List', 'gtm-kit' );
		}

		echo wp_kses(
			$this->get_item_data_tag(
				$product,
				$list_name,
				$woocommerce_loop['loop']
			),
			[
				'span' => [
					'class'                    => [],
					'style'                    => [],
					'data-gtmkit_product_id'   => [],
					'data-gtmkit_product_data' => [],
				],
			]
		);
	}

	/**
	 * Set list name in WooCommerce loop
	 *
	 * @hook woocommerce_after_shop_loop_item.
	 *
	 * @return void
	 */
	public function set_list_name_in_woocommerce_loop() {
		global $woocommerce_loop;

		if ( isset( $woocommerce_loop['name'] ) && ! empty( $woocommerce_loop['name'] ) ) {
			$woocommerce_loop['gtmkit_list_name'] = ucwords( str_replace( '_', ' ', $woocommerce_loop['name'] ) );
		} else {
			$woocommerce_loop['gtmkit_list_name'] = __( 'General Product List', 'gtm-kit' );
		}
	}

	public function set_list_name_on_category_and_tag( $value ) {
		global $woocommerce_loop;

		if ( isset( $woocommerce_loop['name'] ) && empty( $woocommerce_loop['name'] ) ) {
			if ( is_product_category() ) {
				$woocommerce_loop['gtmkit_list_name'] = __( 'Product Category', 'gtm-kit' );
			} elseif ( is_product_tag() ) {
				$woocommerce_loop['gtmkit_list_name'] = __( 'Product Tag', 'gtm-kit' );
			}
		}

		return $value;
	}

	/**
	 * Add product data to cart item remove link
	 *
	 * @hook woocommerce_cart_item_remove_link.
	 *
	 * @param string $woocommerce_cart_item_remove_link The cart item remove link.
	 * @param string $cart_item_key The cart item key
	 *
	 * @return string The updated cart item remove link containing product data.
	 */
	function cart_item_remove_link( string $woocommerce_cart_item_remove_link, string $cart_item_key ): string {

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		if ( ! $cart_item || ! $cart_item['quantity'] ) {
			return $woocommerce_cart_item_remove_link;
		}

		$item_data = $this->get_item_data(
			$cart_item['data'],
			[
				'quantity' => $cart_item['quantity']
			],
			'remove_from_cart'
		);

		$findHref                   = ' href="';
		$replace_width_product_data = sprintf( ' data-gtmkit_product_data="%s" href="', esc_attr( json_encode( $item_data ) ) );

		$substring_pos = strpos( $woocommerce_cart_item_remove_link, $findHref );

		if ( $substring_pos !== false ) {
			$woocommerce_cart_item_remove_link = substr_replace(
				$woocommerce_cart_item_remove_link,
				$replace_width_product_data,
				$substring_pos,
				strlen( $findHref )
			);
		}

		return $woocommerce_cart_item_remove_link;
	}

	/**
	 * Prefix an item ID
	 *
	 * @param string $item_id
	 *
	 * @return string
	 */
	function prefix_item_id( string $item_id ): string {
		$prefix = ( Options::init()->get( 'integrations', 'woocommerce_product_id_prefix' ) ) ?: '';
		return $prefix.$item_id;
	}
}
