<?php
/**
 * WooCommerce.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 */

namespace TLA_Media\GTM_Kit\Integration;

use Exception;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;
use WC_Coupon;
use WC_Customer;
use WC_Order;
use WC_Product;

/**
 * WooCommerce integration
 */
final class WooCommerce  extends AbstractEcommerce {

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options, Util $util) {
		$this->store_currency = get_woocommerce_currency();

		// Call parent constructor.
		parent::__construct( $options, $util );
	}

	/**
	 * Get instance
	 */
	public static function instance(): ?WooCommerce {
		if ( is_null( self::$instance ) ) {
			$options        = new Options();
			$rest_API_server = new RestAPIServer();
			$util        = new Util( $rest_API_server );
			self::$instance = new self( $options, $util );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options, Util $util ): void {

		self::$instance = new self( $options, $util );

		add_filter( 'gtmkit_header_script_data', [ self::$instance, 'get_global_settings' ] );
		add_filter( 'gtmkit_header_script_data', [ self::$instance, 'get_global_data' ] );
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
		], 20, 3 );
		add_filter( 'tinvwl_wishlist_item_meta_post', [ self::$instance, 'Compatibility_With_TI_Wishlist' ] );

		add_action( 'woocommerce_after_shop_loop_item', [ self::$instance, 'product_list_loop_add_to_cart_tracking' ] );
		add_filter( 'woocommerce_cart_item_remove_link', [ self::$instance, 'cart_item_remove_link' ], 10, 2 );

		// Set list name in WooCommerce loop
		add_filter( 'woocommerce_product_loop_start', [ self::$instance, 'set_list_name_on_category_and_tag' ] );
		add_filter( 'woocommerce_related_products_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_filter( 'woocommerce_cross_sells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_filter( 'woocommerce_upsells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_action( 'woocommerce_shortcode_before_best_selling_products_loop', [
			self::$instance,
			'set_list_name_in_woocommerce_loop'
		] );
		add_filter( 'safe_style_css', function( $styles ) {
			$styles[] = 'display';
			$styles[] = 'visibility';
			return $styles;
		} );

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

		wp_enqueue_script(
			'gtmkit-woocommerce',
			GTMKIT_URL . 'assets/js/woocommerce.js',
			[],
			$this->util->get_plugin_version(),
			true
		);


		if ( is_cart() || is_checkout() ) {
			wp_enqueue_script(
				'gtmkit-woocommerce-checkout',
				GTMKIT_URL . 'assets/js/woocommerce-checkout.js',
				[ 'gtmkit-woocommerce' ],
				$this->util->get_plugin_version(),
				true
			);
		}
		}

	/**
	 * get the global script settings
	 *
	 * @param array $global_data Script data
	 *
	 * @return array
	 */
	public function get_global_settings( array $global_data ): array {

		$global_data['settings']['wc']['use_sku']                     = (bool) $this->options->get( 'integrations', 'woocommerce_use_sku' );
		$global_data['settings']['wc']['add_shipping_info']['config'] = (int) Options::init()->get( 'integrations', 'woocommerce_shipping_info' );
		$global_data['settings']['wc']['add_payment_info']['config']  = (int) Options::init()->get( 'integrations', 'woocommerce_payment_info' );
		$global_data['settings']['wc']['view_item']['config']         = (int) Options::init()->get( 'integrations', 'woocommerce_variable_product_tracking' );
		$global_data['settings']['wc']['text']                        = [
			'wp-block-handpicked-products'   => __( 'Handpicked Products', 'gtm-kit' ),
			'wp-block-product-best-sellers'  => __( 'Best Sellers', 'gtm-kit' ),
			'wp-block-product-category'      => __( 'Product Category', 'gtm-kit' ),
			'wp-block-product-new'           => __( 'New Products', 'gtm-kit' ),
			'wp-block-product-on-sale'       => __( 'Products On Sale', 'gtm-kit' ),
			'wp-block-products-by-attribute' => __( 'Products By Attribute', 'gtm-kit' ),
			'wp-block-product-tag'           => __( 'Product Tag', 'gtm-kit' ),
			'wp-block-product-top-rated'     => __( 'Top Rated Products', 'gtm-kit' ),
			'shipping-tier-not-found'        => __( 'Shipping tier not found', 'gtm-kit' ),
			'payment-method-not-found'       => __( 'Payment method not found', 'gtm-kit' ),
		];

		return $global_data;
	}

	/**
	 * Get the global script data
	 *
	 * @param array $global_data Script data
	 *
	 * @return array
	 */
	public function get_global_data( array $global_data ): array {

		$global_data['data']['wc']['currency']    = $this->store_currency;
		$global_data['data']['wc']['is_cart']     = is_cart();
		$global_data['data']['wc']['is_checkout'] = ( is_checkout() && ! is_order_received_page() );

		if ( is_checkout() && ! is_order_received_page() ) {
			$global_data['data']['wc']['cart_items'] = $this->get_cart_items( 'begin_checkout' );
			$global_data['data']['wc']['cart_value'] = (float) WC()->cart->cart_contents_total;
			$global_data['data']['wc']['chosen_shipping_method'] = WC()->session->get('chosen_shipping_methods')[0];
			$global_data['data']['wc']['block'] = has_block('woocommerce/checkout');
			$global_data['data']['wc']['add_payment_info']['fired']   = false;
			$global_data['data']['wc']['add_shipping_info']['fired']  = false;
		}

		$this->global_data = $global_data;

		return $global_data;
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

		if ( $product->get_type() == 'variable' && Options::init()->get( 'integrations', 'woocommerce_variable_product_tracking' ) == 2 ) {
			return $data_layer;
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
			'items'    => $this->get_cart_items( 'view_cart' )
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

		if ( wc_prices_include_tax() ) {
			$cart_value = WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();
		} else {
			$cart_value = WC()->cart->get_cart_contents_total();
		}

		$data_layer['event']                 = 'begin_checkout';
		$data_layer['ecommerce']['currency'] = $this->store_currency;
		$data_layer['ecommerce']['value']    = (float) $cart_value;

		$coupons = WC()->cart->get_applied_coupons();
		if ( $coupons ) {
			$data_layer['ecommerce']['coupon'] = implode( '|', array_filter( $coupons ) );
		}

		$data_layer['ecommerce']['items'] = $this->global_data['data']['wc']['cart_items'];

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

			if ( ( 1 === (int) $order->get_meta( '_gtmkit_order_tracked' ) ) ) {
				if ( ! ( $this->options->is_const_enabled() && $this->options->is_const_defined( 'integration', 'woocommerce_debug_track_purchase' ) ) ) {
					return $data_layer;
				}
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

		$coupons = $order->get_coupon_codes();
		$order_items = [];

		if ( $items = $order->get_items() ) {
			foreach ( $items as $item ) {

				$product       = $item->get_product();
				$inc_tax       = ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) );
				$product_price = round( $order->get_item_total( $item, $inc_tax ), 2 );

				$additional_item_attributes = [
					'quantity' => $item->get_quantity(),
					'price'    => $product_price
				];

				$coupon_discount = $this->get_coupon_discount( $coupons, $item->get_data() );

				if ( $coupon_discount['coupon_codes'] ) {
					$additional_item_attributes['coupon'] = implode( '|', array_filter( $coupon_discount['coupon_codes'] ) );
				}
				if ( $coupon_discount['discount'] ) {
					$additional_item_attributes['discount'] = round( (float) $coupon_discount['discount'], 2 );
				}

				$order_items[] = $this->get_item_data(
					$product,
					$additional_item_attributes,
					'purchase'
				);
			}
		}

		$data_layer['event']     = 'purchase';
		$data_layer['ecommerce'] = [
			'transaction_id' => (string) $order->get_order_number(),
			'value'          => (float) $order_value,
			'tax'            => (float) $order->get_total_tax(),
			'shipping'       => (float) $shipping_total,
			'currency'       => $order->get_currency(),
		];

		if ( $coupons ) {
			$data_layer['ecommerce']['coupon'] = implode( '|', array_filter( $coupons ) );
		}

		$data_layer['ecommerce']['items'] = $order_items;

		if ( $this->options->get( 'integrations', 'woocommerce_include_customer_data' ) ) {

			if (is_user_logged_in()) {
				try {
					$wc_customer = new WC_Customer( WC()->customer->get_id() );
					$order_count = $wc_customer->get_order_count();
					$total_spent = $wc_customer->get_total_spent();
				} catch ( Exception $e ) {
					$wc_customer = WC()->customer;
					$order_count = 1;
					$total_spent = $order_value;
				}
			} else {
				$wc_customer = WC()->customer;
				$order_count = 1;
				$total_spent = $order_value;
			}

			$data_layer['ecommerce']['customer']['id'] = $wc_customer->get_id();

			$data_layer['ecommerce']['customer']['order_count'] = $order_count;
			$data_layer['ecommerce']['customer']['total_spent'] = (float) $total_spent;

			$data_layer['ecommerce']['customer']['first_name'] = $wc_customer->get_first_name();
			$data_layer['ecommerce']['customer']['last_name']  = $wc_customer->get_last_name();

			$data_layer['ecommerce']['customer']['billing_first_name'] = $wc_customer->get_billing_first_name();
			$data_layer['ecommerce']['customer']['billing_last_name']  = $wc_customer->get_billing_last_name();
			$data_layer['ecommerce']['customer']['billing_company']    = $wc_customer->get_billing_company();
			$data_layer['ecommerce']['customer']['billing_address_1']  = $wc_customer->get_billing_address_1();
			$data_layer['ecommerce']['customer']['billing_address_2']  = $wc_customer->get_billing_address_2();
			$data_layer['ecommerce']['customer']['billing_city']       = $wc_customer->get_billing_city();
			$data_layer['ecommerce']['customer']['billing_postcode']   = $wc_customer->get_billing_postcode();
			$data_layer['ecommerce']['customer']['billing_country']    = $wc_customer->get_billing_country();
			$data_layer['ecommerce']['customer']['billing_email']      = $wc_customer->get_billing_email();
			$data_layer['ecommerce']['customer']['billing_email_hash'] = ($wc_customer->get_billing_email() ) ? hash( 'sha256', $wc_customer->get_billing_email() ) : '' ;
			$data_layer['ecommerce']['customer']['billing_phone']      = $wc_customer->get_billing_phone();

			$data_layer['ecommerce']['customer']['shipping_firstName'] = $wc_customer->get_shipping_first_name();
			$data_layer['ecommerce']['customer']['shipping_lastName']  = $wc_customer->get_shipping_last_name();
			$data_layer['ecommerce']['customer']['shipping_company']   = $wc_customer->get_shipping_company();
			$data_layer['ecommerce']['customer']['shipping_address_1'] = $wc_customer->get_shipping_address_1();
			$data_layer['ecommerce']['customer']['shipping_address_2'] = $wc_customer->get_shipping_address_2();
			$data_layer['ecommerce']['customer']['shipping_city']      = $wc_customer->get_shipping_city();
			$data_layer['ecommerce']['customer']['shipping_postcode']  = $wc_customer->get_shipping_postcode();
			$data_layer['ecommerce']['customer']['shipping_country']   = $wc_customer->get_shipping_country();
		}

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
		$coupons = WC()->cart->get_applied_coupons();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			$item_data = [
				'product_id' => $cart_item['product_id'],
				'quantity' => $cart_item['quantity'],
				'total' => $cart_item['line_total'],
				'total_tax' => $cart_item['line_tax'],
				'subtotal' => $cart_item['line_subtotal'],
				'subtotal_tax' => $cart_item['line_subtotal_tax'],
			];
			$coupon_discount = $this->get_coupon_discount( $coupons, $item_data );

			$additional_item_attributes = [
				'quantity' => $cart_item['quantity'],
			];

			if ( $coupon_discount['coupon_codes'] ) {
				$additional_item_attributes['coupon'] = implode( '|', array_filter( $coupon_discount['coupon_codes'] ) );
			}
			if ( $coupon_discount['discount'] ) {
				$additional_item_attributes['discount'] = round( (float) $coupon_discount['discount'], 2 );
			}

			$product      = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$cart_items[] = $this->get_item_data( $product, $additional_item_attributes, $event_context );
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

		if ( $product->get_type() == 'variation' ) {
			$item_data['item_variant'] = implode( ',', array_filter( $product->get_attributes() ) );
		}

		$item_data = array_merge( $item_data, $additional_item_attributes );

		return apply_filters( 'gtmkit_datalayer_item_data', $item_data, $product, $event_context );
	}

	/**
	 * Get the coupons and discount for an item
	 *
	 * @param array $coupons
	 * @param array $item
	 *
	 * @return array
	 */
	function get_coupon_discount( array $coupons, array $item ): array {

		$discount     = 0;
		$coupon_codes = [];

		if ( $coupons ) {

			foreach ( $coupons as $coupon ) {

				$coupon = new WC_Coupon( $coupon );

				$included_products = true;
				$included_cats     = true;

				if ( count( $product_ids = $coupon->get_product_ids() ) > 0 ) {
					if ( ! in_array( $item['product_id'], $product_ids ) ) {
						$included_products = false;
					}
				}
				if ( count( $excluded_product_ids = $coupon->get_excluded_product_ids() ) > 0 ) {
					if ( in_array( $item['product_id'], $excluded_product_ids ) ) {
						$included_products = false;
					}
				}
				if ( count( $product_cats = $coupon->get_product_categories() ) > 0 ) {
					if ( ! has_term( $product_cats, 'product_cat', $item['product_id'] ) ) {
						$included_cats = false;
					}
				}
				if ( count( $excluded_product_cats = $coupon->get_excluded_product_categories() ) > 0 ) {
					if ( has_term( $excluded_product_cats, 'product_cat', $item['product_id'] ) ) {
						$included_cats = false;
					}
				}

				if ( $included_products && $included_cats ) {
					$coupon_codes[] = $coupon->get_code();
					$discount       = $item['subtotal'] - $item['total'];

					if ( wc_prices_include_tax() ) {
						$discount = $discount + $item['subtotal_tax'] - $item['total_tax'];
					}

					$discount = $discount / $item['quantity'];
				}
			}
		}

		return [ 'coupon_codes' => $coupon_codes, 'discount' => $discount ];
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

		return preg_replace( '/<li[^>]+class="[^"]*wc-block-grid__product[^">]*"[^>]*>/i', '$0' . $item_data_tag, $html );
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

		if ( ! empty( $woocommerce_loop['gtmkit_list_name'] ) ) {
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
	public function set_list_name_in_woocommerce_loop(): void {
		global $woocommerce_loop;

		if ( ! empty( $woocommerce_loop['name'] ) ) {
			$woocommerce_loop['gtmkit_list_name'] = ucwords( str_replace( '_', ' ', $woocommerce_loop['name'] ) );
		} else {
			$woocommerce_loop['gtmkit_list_name'] = __( 'General Product List', 'gtm-kit' );
		}
	}

	/**
	 * Set list name in WooCommerce loop
	 *
	 * @hook woocommerce_after_shop_loop_item.
	 *
	 * @param mixed $columns
	 *
	 * @return mixed
	 */
	public function set_list_name_in_woocommerce_loop_filter( $columns ) {
		global $woocommerce_loop;

		$this->set_list_name_in_woocommerce_loop();

		return $columns;
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

	/**
	 * Compatibility with TI WooCommerce Wishlist
	 *
	 * @param array $item_data
	 *
	 * @return array
	 */
	function Compatibility_With_TI_Wishlist( array $item_data ): array {

		foreach ( array_keys( $item_data ) as $key ) {
			if ( strpos( $key, 'gtmkit_' ) === 0 ) {
				unset( $item_data[ $key ] );
			}
		}

		return $item_data;
	}
}
