<?php
/**
 * WooCommerce.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore;
use Exception;
use TLA_Media\GTM_Kit\Common\Conditionals\BricksConditional;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\Tax\TaxResolver;
use TLA_Media\GTM_Kit\Options\Options;
use WC_Coupon;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * WooCommerce integration
 */
final class WooCommerce extends AbstractEcommerce {

	/**
	 * Instance.
	 *
	 * @var null|WooCommerce
	 */
	protected static ?WooCommerce $instance = null;

	/**
	 * Tax resolver. Canonical price/total path for the data layer.
	 *
	 * @var TaxResolver
	 */
	private TaxResolver $tax_resolver;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->store_currency = get_woocommerce_currency();

		$this->tax_resolver = new TaxResolver( $options );

		// Call parent constructor.
		parent::__construct( $options, $util );
	}

	/**
	 * Get instance
	 */
	public static function instance(): WooCommerce {
		if ( is_null( self::$instance ) ) {
			$options         = new Options();
			$rest_api_server = new RestAPIServer();
			$util            = new Util( $options, $rest_api_server );
			self::$instance  = new self( $options, $util );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public static function register( Options $options, Util $util ): void {

		self::$instance = new self( $options, $util );

		add_filter( 'gtmkit_header_script_settings', [ self::$instance, 'get_global_settings' ] );
		add_filter( 'gtmkit_header_script_data', [ self::$instance, 'get_global_data' ] );
		add_filter( 'gtmkit_datalayer_content', [ self::$instance, 'get_datalayer_content' ] );
		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );

		// Add-to-cart tracking.
		add_action(
			'woocommerce_after_add_to_cart_button',
			[
				self::$instance,
				'single_product_add_to_cart_tracking',
			]
		);
		add_filter(
			'woocommerce_grouped_product_list_column_label',
			[
				self::$instance,
				'grouped_product_add_to_cart_tracking',
			],
			10,
			2
		);
		add_filter(
			'woocommerce_blocks_product_grid_item_html',
			[
				self::$instance,
				'product_block_add_to_cart_tracking',
			],
			20,
			3
		);
		add_action( 'woocommerce_after_shop_loop_item', [ self::$instance, 'product_list_loop_add_to_cart_tracking' ] );
		add_filter( 'woocommerce_cart_item_remove_link', [ self::$instance, 'cart_item_remove_link' ], 10, 2 );
		add_filter( 'woocommerce_available_variation', [ self::$instance, 'add_variation_tax_resolved_price' ], 10, 3 );

		// Set list name in WooCommerce loop.
		add_filter( 'woocommerce_product_loop_start', [ self::$instance, 'set_list_name_on_category_and_tag' ] );
		add_filter( 'woocommerce_related_products_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_filter( 'woocommerce_cross_sells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_filter( 'woocommerce_upsells_columns', [ self::$instance, 'set_list_name_in_woocommerce_loop_filter' ] );
		add_action(
			'woocommerce_shortcode_before_best_selling_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_filter(
			'safe_style_css',
			function ( $styles ) {
				$styles[] = 'display';
				$styles[] = 'visibility';
				return $styles;
			}
		);

		if ( $options->get( 'integrations', 'woocommerce_custom_order_received_page_enabled' ) ) {
			add_filter( 'woocommerce_is_order_received_page', [ self::$instance, 'is_custom_order_received_page' ] );
		}

		add_action(
			'woocommerce_shortcode_before_featured_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_action(
			'woocommerce_shortcode_before_recent_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_action(
			'woocommerce_shortcode_before_related_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_action(
			'woocommerce_shortcode_before_sale_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_action(
			'woocommerce_shortcode_before_top_rated_products_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
		add_action(
			'woocommerce_shortcode_before_product_category_loop',
			[
				self::$instance,
				'set_list_name_in_woocommerce_loop',
			]
		);
	}

	/**
	 * Enqueue scripts
	 *
	 * Classic-template path only. The block tracking bundle is enqueued by
	 * {@see WooCommerceBlocks::enqueue_block_assets()} on a later priority,
	 * which also dequeues `gtmkit-woocommerce` on a block-built Cart or
	 * Checkout page where the block bundle takes over tracking.
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'woocommerce_dequeue_script' ) ) {
			return;
		}

		$this->util->enqueue_script( 'gtmkit-woocommerce', 'integration/woocommerce.js', false, [ 'jquery' ] );

		if ( ( is_cart() || is_checkout() ) && ! WooCommerceBlocks::instance()->has_cart_or_checkout_block() ) {
			$this->util->enqueue_script( 'gtmkit-woocommerce-checkout', 'integration/woocommerce-checkout.js', false, [ 'gtmkit-woocommerce' ] );
		}
	}

	/**
	 * Get the Util instance.
	 *
	 * Exposed so the block integration can share the same configured Util.
	 */
	public function get_util(): Util {
		return $this->util;
	}

	/**
	 * Get the global script settings
	 *
	 * @param array<string, mixed> $global_settings Script settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_global_settings( array $global_settings ): array {

		$global_settings['wc']['use_sku']                     = (bool) $this->options->get( 'integrations', 'woocommerce_use_sku' );
		$global_settings['wc']['pid_prefix']                  = $this->prefix_item_id();
		$global_settings['wc']['add_shipping_info']['config'] = (int) $this->options->get( 'integrations', 'woocommerce_shipping_info' );
		$global_settings['wc']['add_payment_info']['config']  = (int) $this->options->get( 'integrations', 'woocommerce_payment_info' );
		$global_settings['wc']['view_item']['config']         = (int) $this->options->get( 'integrations', 'woocommerce_variable_product_tracking' );
		$global_settings['wc']['view_item_list']['config']    = (int) $this->options->get( 'integrations', 'woocommerce_view_item_list_limit' );
		$global_settings['wc']['wishlist']                    = false;
		$global_settings['wc']['css_selectors']               = $this->get_css_selectors();
		$global_settings['wc']['text']                        = [
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

		return $global_settings;
	}

	/**
	 * Get CSS Selectors
	 *
	 * @return array{product_list_select_item: string, product_list_element: string, product_list_exclude: string, product_list_add_to_cart: string}
	 */
	private function get_css_selectors(): array {

		$css_selectors = [
			'product_list_select_item' => '.products .product:not(.product-category) a:not(.add_to_cart_button.ajax_add_to_cart,.add_to_wishlist),' .
											'.wc-block-grid__products li:not(.product-category) a:not(.add_to_cart_button.ajax_add_to_cart,.add_to_wishlist),' .
											'.woocommerce-grouped-product-list-item__label a:not(.add_to_wishlist)',
			'product_list_element'     => '.product,.wc-block-grid__product',
			'product_list_exclude'     => '',
			'product_list_add_to_cart' => '.add_to_cart_button.ajax_add_to_cart:not(.single_add_to_cart_button)',
		];

		if ( ( new BricksConditional() )->is_met() ) {
			$css_selectors['product_list_add_to_cart'] .= ',.add_to_cart_button.brx_ajax_add_to_cart:not(.single_add_to_cart_button)';
		}

		return $css_selectors;
	}

	/**
	 * Get the global script data
	 *
	 * @param array<string, mixed> $global_data Script data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_global_data( array $global_data ): array {

		$global_data['wc']['currency']    = $this->store_currency;
		$global_data['wc']['is_cart']     = is_cart();
		$global_data['wc']['is_checkout'] = ( is_checkout() && ! is_order_received_page() );
		$global_data['wc']['blocks']      = WooCommerceBlocks::instance()->get_woocommerce_blocks();

		if ( is_cart() ) {
			$global_data['wc']['cart_items'] = $this->get_cart_items( 'view_cart' );
		}

		if ( is_checkout() && ! is_order_received_page() ) {
			$exclude_tax                                     = $this->tax_resolver->resolve_tax_mode();
			$global_data['wc']['cart_items']                 = $this->get_cart_items( 'begin_checkout' );
			$global_data['wc']['cart_value']                 = $this->tax_resolver->resolve_cart_total( WC()->cart, $exclude_tax );
			$global_data['wc']['chosen_shipping_method']     = WC()->session->get( 'chosen_shipping_methods' )[0] ?? '';
			$global_data['wc']['chosen_payment_method']      = $this->get_payment_method();
			$global_data['wc']['add_payment_info']['fired']  = false;
			$global_data['wc']['add_shipping_info']['fired'] = false;
		}

		$this->global_data = $global_data;

		return $global_data;
	}

	/**
	 * Get the  payment method
	 *
	 * @return string|null
	 */
	private function get_payment_method(): ?string {

		$payment_method = WC()->session->get( 'chosen_payment_method' );

		if ( ! $payment_method ) {
			$payment_method = array_key_first( WC()->payment_gateways()->get_available_payment_gateways() );
		}

		return $payment_method;
	}

	/**
	 * Get the WooCommerce dataLayer content
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_datalayer_content( array $data_layer ): array {

		if ( is_product() ) {
			$data_layer = $this->get_datalayer_content_product_page( $data_layer );
		} elseif ( is_product_category() ) {
			$data_layer = $this->get_datalayer_content_product_category( $data_layer );
		} elseif ( is_product_tag() ) {
			$data_layer = $this->get_datalayer_content_product_tag( $data_layer );
		} elseif ( is_order_received_page() ) {
			$data_layer = $this->get_datalayer_content_order_received( $data_layer );
		} elseif ( is_checkout() ) {
			$data_layer = $this->get_datalayer_content_checkout( $data_layer );
		} elseif ( is_cart() ) {
			$data_layer = $this->get_datalayer_content_cart( $data_layer );
		}

		if ( $this->options->get( 'integrations', 'woocommerce_include_permalink_structure' ) ) {
			$data_layer = $this->get_permalink_structure_property( $data_layer );
		}

		if ( $this->options->get( 'integrations', 'woocommerce_include_pages' ) ) {
			$data_layer = $this->get_pages_property( $data_layer );
		}

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for product pages
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_datalayer_content_product_page( array $data_layer ): array {

		$product = wc_get_product( get_the_ID() );

		if ( ! ( $product instanceof WC_Product ) ) {
			return $data_layer;
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'product-page';
		}

		if ( $product->get_type() === 'variable' && (int) $this->options->get( 'integrations', 'woocommerce_variable_product_tracking' ) === 2 ) {
			return $data_layer;
		}

		$item = $this->get_item_data( $product );

		$data_layer['productType'] = $product->get_type();
		$data_layer['event']       = 'view_item';
		$data_layer['ecommerce']   = [
			'items'    => [ $item ],
			'value'    => round( $item['price'], 2 ),
			'currency' => $this->store_currency,
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for category pages
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
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
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
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
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_datalayer_content_cart( array $data_layer ): array {

		$exclude_tax = $this->tax_resolver->resolve_tax_mode();
		$cart_value  = $this->tax_resolver->resolve_cart_total( WC()->cart, $exclude_tax );

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'cart';
		}

		$data_layer['event']     = 'view_cart';
		$data_layer['ecommerce'] = [
			'currency' => $this->store_currency,
			'value'    => $cart_value,
			'items'    => $this->get_cart_items( 'view_cart' ),
		];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for checkout page
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_datalayer_content_checkout( array $data_layer ): array {

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'checkout';
		}

		$exclude_tax = $this->tax_resolver->resolve_tax_mode();
		$cart_value  = $this->tax_resolver->resolve_cart_total( WC()->cart, $exclude_tax );

		$data_layer['event']                 = 'begin_checkout';
		$data_layer['ecommerce']['currency'] = $this->store_currency;
		$data_layer['ecommerce']['value']    = $cart_value;

		$coupons = WC()->cart->get_applied_coupons();
		if ( $coupons ) {
			$data_layer['ecommerce']['coupon'] = implode( '|', array_filter( $coupons ) );
		}

		$data_layer['ecommerce']['items'] = $this->global_data['wc']['cart_items'];

		return $data_layer;
	}

	/**
	 * Get the dataLayer data for order_received page
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_datalayer_content_order_received( array $data_layer ): array {

		global $wp;

		$order_id = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ?? 0 ) );

		if ( ! $order_id || apply_filters( 'gtmkit_disable_frontend_purchase_event', false ) ) {
			return $data_layer;
		}

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			return $data_layer;
		}

		$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( wp_unslash( $_GET['key'] ) ) ); // phpcs:ignore

		if ( ! hash_equals( $order->get_order_key(), (string) $order_key ) ) {
			return $data_layer;
		}

		// WooCommerce renders this page without any order details to a visitor
		// it cannot tie to the order. Returning before the tracking flag is
		// stamped is what keeps the real customer's later visit trackable.
		if ( ! $this->visitor_may_view_order( $order ) ) {
			return $data_layer;
		}

		if ( ( 'failed' === $order->get_status() ) ) {
			return $data_layer;
		}

		if ( apply_filters( 'gtmkit_datalayer_exit_order_received', false, $order ) ) {
			return $data_layer;
		}

		if ( ( 1 === (int) $order->get_meta( '_gtmkit_order_tracked' ) ) ) {
			if ( ! ( $this->options->is_const_defined( 'integrations', 'woocommerce_debug_track_purchase' ) ) ) {
				return $data_layer;
			} else {
				$data_layer['debug'] = 'order-already-tracked';
			}

			if ( $this->options->get( 'general', 'debug_log' ) ) {
				$logger = wc_get_logger();
				$logger->info( 'Order already tracked: ' . $order->get_id(), [ 'source' => 'gtmkit-order-already-tracked' ] );
			}
		}

		if ( $this->options->get( 'general', 'datalayer_page_type' ) ) {
			$data_layer['pageType'] = 'order-received';
		}

		$order_value = $this->tax_resolver->resolve_order_total( $order, $this->tax_resolver->resolve_tax_mode() );

		$data_layer = $this->get_purchase_event( $order, $data_layer );

		if ( $this->options->get( 'integrations', 'woocommerce_include_customer_data' ) ) {
			$data_layer = $this->include_customer_data( $data_layer, $order, $order_value );
		}

		$order->add_meta_data( '_gtmkit_order_tracked', '1' );
		$order->save();

		return apply_filters( 'gtmkit_datalayer_content_order_received', $data_layer );
	}

	/**
	 * Whether WooCommerce would show this order to whoever is viewing the page.
	 *
	 * Mirrors the two checks WooCommerce applies on the order-received endpoint
	 * after the order key matches, in the order it applies them: a registered
	 * customer's order is only shown to that customer, and a guest order is only
	 * shown to a visitor the store can identify. Reading WooCommerce's own
	 * filter rather than hardcoding the rule means a store that deliberately
	 * relaxes the requirement keeps its tracking, with nothing to configure.
	 *
	 * @param WC_Order $order The order being viewed.
	 *
	 * @return bool
	 */
	private function visitor_may_view_order( WC_Order $order ): bool {

		$verify_known_shoppers = (bool) apply_filters( 'woocommerce_order_received_verify_known_shoppers', true );
		$order_customer_id     = $order->get_customer_id();

		if ( $verify_known_shoppers && $order_customer_id && get_current_user_id() !== $order_customer_id ) {
			return false;
		}

		return ! $this->guest_should_verify_email( $order );
	}

	/**
	 * Whether WooCommerce would ask this visitor to verify the order's email.
	 *
	 * WooCommerce's own wrapper for this is private, so the nonce handling it
	 * performs on a submitted verification form is repeated here before the
	 * shared helper is consulted.
	 *
	 * @param WC_Order $order The order being viewed.
	 *
	 * @return bool
	 */
	private function guest_should_verify_email( WC_Order $order ): bool {

		$verifier = [ '\Automattic\WooCommerce\Internal\Utilities\Users', 'should_user_verify_order_email' ];

		// The helper lives in WooCommerce's `Internal` namespace and is not
		// part of its public API, so it may move or disappear between
		// releases. Should that happen, the known-shopper check above still
		// stands and guest orders are reported as they were before.
		if ( ! is_callable( $verifier ) ) {
			return false;
		}

		// The classic confirmation template names the verification nonce
		// `check_submission`; the block template posts the same nonce as
		// `_wpnonce`. A guest verifying on either one must be recognised.
		$supplied_email = null;
		$nonce          = '';

		foreach ( [ 'check_submission', '_wpnonce' ] as $nonce_field ) {
			if ( isset( $_POST[ $nonce_field ] ) && is_string( $_POST[ $nonce_field ] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) );
				break;
			}
		}

		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'wc_verify_email' ) && isset( $_POST['email'] ) && is_string( $_POST['email'] ) ) {
			$supplied_email = sanitize_email( wp_unslash( $_POST['email'] ) );
		}

		return (bool) call_user_func( $verifier, $order->get_id(), $supplied_email, 'order-received' );
	}

	/**
	 * Retrieves purchase event data for the data layer.
	 *
	 * @param WC_Order             $order The order.
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content.
	 */
	public function get_purchase_event( WC_Order $order, array $data_layer = [] ): array {

		$exclude_tax = $this->tax_resolver->resolve_tax_mode();
		$order_value = $this->tax_resolver->resolve_order_total( $order, $exclude_tax );

		$shipping_total = (float) $order->get_shipping_total();
		if ( $this->options->get( 'integrations', 'woocommerce_exclude_shipping' ) ) {
			$order_value -= $shipping_total;
		}

		$data_layer['event']     = 'purchase';
		$data_layer['ecommerce'] = [
			'transaction_id' => (string) $order->get_order_number(),
			'value'          => round( $order_value, 2 ),
			'tax'            => round( $order->get_total_tax(), 2 ),
			'shipping'       => round( $shipping_total, 2 ),
			'currency'       => $order->get_currency(),
		];
		/** @phpstan-ignore-next-line level5 */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
		$data_layer['new_customer'] = ! DataStore::is_returning_customer( $order ); // Param $order is declared as array but object is expected.

		$coupons = $order->get_coupon_codes();

		if ( $coupons ) {
			$data_layer['ecommerce']['coupon'] = implode( '|', array_filter( $coupons ) );
		}

		$data_layer['ecommerce']['items'] = $this->get_order_items( $order );

		if ( $this->options->get( 'general', 'debug_log' ) ) {
			$logger = wc_get_logger();
			$logger->info( wc_print_r( $data_layer, true ), [ 'source' => 'gtmkit-purchase' ] );
		}

		return $data_layer;
	}

	/**
	 * Get the permalinkStructure property for the dataLayer
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	private function get_permalink_structure_property( array $data_layer ): array {
		$wc_permalink_structure           = \wc_get_permalink_structure();
		$data_layer['permalinkStructure'] = [
			'productBase'   => $wc_permalink_structure['product_base'],
			'categoryBase'  => $wc_permalink_structure['category_base'],
			'tagBase'       => $wc_permalink_structure['tag_base'],
			'attributeBase' => $wc_permalink_structure['attribute_base'],
		];

		return $data_layer;
	}

	/**
	 * Get the pages property for the dataLayer
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 *
	 * @return array<string, mixed> The datalayer content
	 */
	public function get_pages_property( array $data_layer ): array {
		$data_layer['pages'] = [
			'cart'          => str_replace( \home_url(), '', \wc_get_cart_url() ),
			'checkout'      => str_replace( \home_url(), '', \wc_get_checkout_url() ),
			'orderReceived' => str_replace( \home_url(), '', \wc_get_endpoint_url( 'order-received', '', \wc_get_checkout_url() ) ),
			'myAccount'     => str_replace( \home_url(), '', \get_permalink( \wc_get_page_id( 'myaccount' ) ) ),
		];

		return $data_layer;
	}

	/**
	 * Get cart items.
	 *
	 * @param string $event_context The event context of the item data.
	 *
	 * @return array<int, mixed> The cart items.
	 */
	public function get_cart_items( string $event_context ): array {
		$cart_items = [];
		$coupons    = WC()->cart->get_applied_coupons();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			$item_data       = [
				'product_id'   => $cart_item['product_id'],
				'quantity'     => $cart_item['quantity'],
				'total'        => $cart_item['line_total'],
				'total_tax'    => $cart_item['line_tax'],
				'subtotal'     => $cart_item['line_subtotal'],
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
	 * @param WC_Product           $product An instance of WP_Product.
	 * @param array<string, mixed> $additional_item_attributes Any key-value pair that needs to be added to the item data.
	 * @param string               $event_context The event context of the item data.
	 *
	 * @return array<string, mixed> The item data.
	 */
	public function get_item_data( $product, array $additional_item_attributes = [], string $event_context = '' ): array {

		if ( ! ( $product instanceof WC_Product ) ) {
			return [];
		}

		$product_id_to_query = ( $product->get_type() === 'variation' ) ? $product->get_parent_id() : $product->get_id();

		if ( $this->options->get( 'integrations', 'woocommerce_use_sku' ) ) {
			$item_id = $product->get_sku() ? $product->get_sku() : $product->get_id();
		} else {
			$item_id = $product->get_id();
		}

		$item_data = [
			'id'        => $this->prefix_item_id( $item_id ),
			'item_id'   => $this->prefix_item_id( $item_id ),
			'item_name' => $product->get_title(),
			'currency'  => $this->store_currency,
			'price'     => $this->tax_resolver->resolve_product_price( $product, $this->tax_resolver->resolve_tax_mode() ),
		];

		if ( $this->options->get( 'integrations', 'woocommerce_brand' ) ) {
			$item_data['item_brand'] = $this->get_product_term( $product_id_to_query, $this->options->get( 'integrations', 'woocommerce_brand' ) );
		}

		if ( $this->options->get( 'integrations', 'woocommerce_google_business_vertical' ) ) {
			$item_data['google_business_vertical'] = $this->options->get( 'integrations', 'woocommerce_google_business_vertical' );
		}

		$item_category_elements = $this->get_primary_product_category( $product_id_to_query, 'product_cat' );

		$number_of_elements = count( $item_category_elements );

		if ( $number_of_elements ) {

			for ( $element = 0; $element < $number_of_elements; $element++ ) {
				$designator                                 = ( $element === 0 ) ? '' : $element + 1;
				$item_data[ 'item_category' . $designator ] = $item_category_elements[ $element ];
			}
		}

		if ( $product->get_type() === 'variation' ) {
			$item_data['item_variant'] = implode( ',', array_filter( $product->get_attributes() ) );
		}

		$item_data = array_merge( $item_data, $additional_item_attributes );

		return apply_filters( 'gtmkit_datalayer_item_data', $item_data, $product, $event_context );
	}

	/**
	 * Get the coupons and discount for an item
	 *
	 * @param array<int, mixed>    $coupons The coupons.
	 * @param array<string, mixed> $item The item.
	 *
	 * @return array<string, mixed>
	 */
	public function get_coupon_discount( array $coupons, array $item ): array {

		$discount     = 0;
		$coupon_codes = [];

		if ( $coupons ) {

			foreach ( $coupons as $coupon ) {

				$coupon = new WC_Coupon( $coupon );

				$included_products = true;
				$included_cats     = true;

				$product_ids = $coupon->get_product_ids();
				if ( count( $product_ids ) > 0 ) {
					if ( ! in_array( $item['product_id'], $product_ids, true ) ) {
						$included_products = false;
					}
				}

				$excluded_product_ids = $coupon->get_excluded_product_ids();
				if ( count( $excluded_product_ids ) > 0 ) {
					if ( in_array( $item['product_id'], $excluded_product_ids, true ) ) {
						$included_products = false;
					}
				}

				$product_cats = $coupon->get_product_categories();
				if ( count( $product_cats ) > 0 ) {
					if ( ! has_term( $product_cats, 'product_cat', $item['product_id'] ) ) {
						$included_cats = false;
					}
				}

				$excluded_product_cats = $coupon->get_excluded_product_categories();
				if ( count( $excluded_product_cats ) > 0 ) {
					if ( has_term( $excluded_product_cats, 'product_cat', $item['product_id'] ) ) {
						$included_cats = false;
					}
				}

				if ( $included_products && $included_cats ) {
					$coupon_codes[] = $coupon->get_code();
					$discount       = $this->tax_resolver->resolve_item_discount(
						$item,
						$this->tax_resolver->resolve_tax_mode()
					);
				}
			}
		}

		return [
			'coupon_codes' => $coupon_codes,
			'discount'     => $discount,
		];
	}

	/**
	 * Inject the TaxResolver-resolved price into the variations data.
	 *
	 * WooCommerce ships `display_price` based on `woocommerce_tax_display_shop`.
	 * The data layer follows the `integrations.woocommerce_exclude_tax`
	 * toggle instead, so the JS variation handler reads `gtmkit_price`.
	 *
	 * @hook woocommerce_available_variation
	 *
	 * @param array<string, mixed> $variation_data Variation data passed to JS.
	 * @param WC_Product           $product        The parent variable product.
	 * @param WC_Product           $variation      The variation product object.
	 *
	 * @return array<string, mixed>
	 */
	public function add_variation_tax_resolved_price( array $variation_data, WC_Product $product, WC_Product $variation ): array {
		unset( $product );

		$variation_data['gtmkit_price'] = $this->tax_resolver->resolve_product_price(
			$variation,
			$this->tax_resolver->resolve_tax_mode()
		);

		return $variation_data;
	}

	/**
	 * Add-to-cart tracing on single product.
	 *
	 * The payload rides a hidden span rather than a form field. WooCommerce's
	 * Add to Cart + Options block scans everything third parties print into
	 * the add-to-cart hooks and, on finding an input, select, textarea, button
	 * or form tag, falls back to a plain HTML POST form. That costs the
	 * shopper the in-place add and a full page reload, so this markup must
	 * stay clear of those five tags.
	 *
	 * The class is deliberately distinct from the product-list carrier so the
	 * page-wide list sweep cannot fold a product page into an impression.
	 *
	 * @hook woocommerce_after_add_to_cart_button
	 *
	 * @return void
	 */
	public function single_product_add_to_cart_tracking(): void {
		global $product;

		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$item_data = $this->get_item_data( $product );

		printf(
			'<span class="gtmkit_single_product_data" style="display:none; visibility:hidden;" data-gtmkit_product_id="%s" data-gtmkit_product_data="%s"></span>' . "\n",
			esc_attr( (string) $product->get_id() ),
			esc_attr( (string) wp_json_encode( $item_data ) )
		);
	}

	/**
	 * Add-to-cart tracking on grouped product.
	 *
	 * @hook woocommerce_grouped_product_list_column_label
	 *
	 * @param string     $label_value Product label.
	 * @param WC_Product $product The product.
	 *
	 * @return string The product label string.
	 */
	public function grouped_product_add_to_cart_tracking( string $label_value, WC_Product $product ): string {

		$label_value .= $this->get_item_data_tag( $product, __( 'Grouped Product', 'gtm-kit' ), $this->grouped_product_position++ );

		return $label_value;
	}

	/**
	 * Add-to-cart tracking on product blocks
	 *
	 * @hook woocommerce_blocks_product_grid_item_html.
	 *
	 * @param string     $html Product grid item HTML.
	 * @param object     $data Product data passed to the template.
	 * @param WC_Product $product Product object.
	 *
	 * @return string Updated product grid item HTML.
	 */
	public function product_block_add_to_cart_tracking( string $html, object $data, WC_Product $product ): string {
		$item_data_tag = $this->get_item_data_tag( $product, '', 0 );

		return preg_replace( '/<li[^>]+class="[^"]*wc-block-grid__product[^">]*"[^>]*>/i', '$0' . $item_data_tag, $html );
	}

	/**
	 * Generates a hidden <span> element that contains the item data.
	 *
	 * @param WC_Product $product Product object.
	 * @param string     $item_list_name Name of the list associated with the event.
	 * @param int        $index The index of the product in the product list. The first product should have the index no. 1.
	 *
	 * @return string A hidden <span> element that contains the item data.
	 */
	public function get_item_data_tag( WC_Product $product, string $item_list_name, int $index ): string {

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
			esc_attr( (string) $product->get_id() ),
			esc_attr( wp_json_encode( $item_data ) )
		);
	}

	/**
	 * Add-to-cart tracking in product list loop
	 *
	 * @hook woocommerce_after_shop_loop_item.
	 *
	 * @return void
	 */
	public function product_list_loop_add_to_cart_tracking(): void {
		global $product, $woocommerce_loop;

		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		if ( ! empty( $woocommerce_loop['gtmkit_list_name'] ) ) {
			$list_name = $woocommerce_loop['gtmkit_list_name'];
		} else {
			$list_name = __( 'General Product List', 'gtm-kit' );
		}

		echo wp_kses(
			$this->get_item_data_tag(
				$product,
				$list_name,
				( $woocommerce_loop['loop'] ) ?? 0
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
	 * @param mixed $columns The columns.
	 *
	 * @return mixed
	 */
	public function set_list_name_in_woocommerce_loop_filter( $columns ) {
		global $woocommerce_loop;

		$this->set_list_name_in_woocommerce_loop();

		return $columns;
	}

	/**
	 * Set the list name on categories and tags
	 *
	 * @param mixed $value The product loop start.
	 *
	 * @return mixed
	 */
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
	 * @param string $cart_item_key The cart item key.
	 *
	 * @return string The updated cart item remove link containing product data.
	 */
	public function cart_item_remove_link( string $woocommerce_cart_item_remove_link, string $cart_item_key ): string {

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		if ( ! $cart_item || ! $cart_item['quantity'] ) {
			return $woocommerce_cart_item_remove_link;
		}

		$item_data = $this->get_item_data(
			$cart_item['data'],
			[
				'quantity' => $cart_item['quantity'],
			],
			'remove_from_cart'
		);

		$link_html = new \WP_HTML_Tag_Processor( $woocommerce_cart_item_remove_link );
		$link_html->next_tag();
		// WP_HTML_Tag_Processor escapes the attribute value itself; pre-escaping
		// here would double-encode the JSON and break JSON.parse() on the client.
		$link_html->set_attribute( 'data-gtmkit_product_data', (string) wp_json_encode( $item_data ) );

		return $link_html->get_updated_html();
	}

	/**
	 * Prefix an item ID
	 *
	 * @param string $item_id The item ID.
	 *
	 * @return string
	 */
	public function prefix_item_id( string $item_id = '' ): string {
		return $this->options->get( 'integrations', 'woocommerce_product_id_prefix' ) . $item_id;
	}

	/**
	 * Include customer data
	 *
	 * Every field describes the shopper who placed the order and is read off
	 * the order itself. The browsing session is not consulted: on the
	 * order-received page it belongs to whoever opened the link, who is not
	 * necessarily the purchaser.
	 *
	 * @param array<string, mixed> $data_layer The datalayer content.
	 * @param WC_Order             $order The order.
	 * @param mixed                $order_value Order value.
	 *
	 * @return array<string, mixed>
	 */
	public function include_customer_data( array $data_layer, WC_Order $order, $order_value ): array {

		$customer_id = $order->get_customer_id();
		$wc_customer = null;
		$order_count = 1;
		$total_spent = $order_value;

		if ( $customer_id ) {
			try {
				$wc_customer = new WC_Customer( $customer_id );
				$order_count = $wc_customer->get_order_count();
				$total_spent = $wc_customer->get_total_spent();
			} catch ( Exception $e ) {
				// An account that has since been deleted leaves no lifetime
				// figures to report; the order's own values stand in, as they
				// already do for a guest purchase.
				$wc_customer = null;
			}
		}

		$billing_email = $order->get_billing_email();

		$data_layer['ecommerce']['customer']['id'] = $customer_id;

		$data_layer['ecommerce']['customer']['order_count'] = $order_count;
		$data_layer['ecommerce']['customer']['total_spent'] = round( (float) $total_spent, 2 );

		$data_layer['ecommerce']['customer']['first_name'] = ( $wc_customer instanceof WC_Customer ) ? $wc_customer->get_first_name() : $order->get_billing_first_name();
		$data_layer['ecommerce']['customer']['last_name']  = ( $wc_customer instanceof WC_Customer ) ? $wc_customer->get_last_name() : $order->get_billing_last_name();

		$data_layer['ecommerce']['customer']['billing_first_name'] = $order->get_billing_first_name();
		$data_layer['ecommerce']['customer']['billing_last_name']  = $order->get_billing_last_name();
		$data_layer['ecommerce']['customer']['billing_company']    = $order->get_billing_company();
		$data_layer['ecommerce']['customer']['billing_address_1']  = $order->get_billing_address_1();
		$data_layer['ecommerce']['customer']['billing_address_2']  = $order->get_billing_address_2();
		$data_layer['ecommerce']['customer']['billing_city']       = $order->get_billing_city();
		$data_layer['ecommerce']['customer']['billing_postcode']   = $order->get_billing_postcode();
		$data_layer['ecommerce']['customer']['billing_country']    = $order->get_billing_country();
		$data_layer['ecommerce']['customer']['billing_state']      = $order->get_billing_state();
		$data_layer['ecommerce']['customer']['billing_email']      = $billing_email;
		$data_layer['ecommerce']['customer']['billing_email_hash'] = ( $billing_email ) ? hash( 'sha256', $billing_email ) : '';
		$data_layer['ecommerce']['customer']['billing_phone']      = $order->get_billing_phone();

		$data_layer['ecommerce']['customer']['shipping_firstName'] = $order->get_shipping_first_name();
		$data_layer['ecommerce']['customer']['shipping_lastName']  = $order->get_shipping_last_name();
		$data_layer['ecommerce']['customer']['shipping_company']   = $order->get_shipping_company();
		$data_layer['ecommerce']['customer']['shipping_address_1'] = $order->get_shipping_address_1();
		$data_layer['ecommerce']['customer']['shipping_address_2'] = $order->get_shipping_address_2();
		$data_layer['ecommerce']['customer']['shipping_city']      = $order->get_shipping_city();
		$data_layer['ecommerce']['customer']['shipping_postcode']  = $order->get_shipping_postcode();
		$data_layer['ecommerce']['customer']['shipping_country']   = $order->get_shipping_country();
		$data_layer['ecommerce']['customer']['shipping_state']     = $order->get_shipping_state();

		$data_layer['user_data']['sha256_email_address']         = $this->util->normalize_and_hash_email_address( 'sha256', $billing_email );
		$data_layer['user_data']['sha256_phone_number']          = $this->util->normalize_and_hash( 'sha256', $order->get_billing_phone(), true );
		$data_layer['user_data']['address']['sha256_first_name'] = $this->util->normalize_and_hash( 'sha256', $order->get_billing_first_name(), false );
		$data_layer['user_data']['address']['sha256_last_name']  = $this->util->normalize_and_hash( 'sha256', $order->get_billing_last_name(), false );
		$data_layer['user_data']['address']['street']            = $order->get_billing_address_1();
		$data_layer['user_data']['address']['city']              = $order->get_billing_city();
		$data_layer['user_data']['address']['region']            = $order->get_billing_state();
		$data_layer['user_data']['address']['postal_code']       = $order->get_billing_postcode();
		$data_layer['user_data']['address']['country']           = $order->get_billing_country();

		return $data_layer;
	}

	/**
	 * Get order items
	 *
	 * @param WC_Order $order The order.
	 *
	 * @return array<int, mixed>
	 */
	private function get_order_items( WC_Order $order ): array {
		$order_items = [];
		$coupons     = $order->get_coupon_codes();
		$items       = $order->get_items();

		if ( $items ) {
			foreach ( $items as $item ) {

				if ( $item instanceof WC_Order_Item_Product ) {
					$product       = $item->get_product();
					$product_price = $this->tax_resolver->resolve_order_item_price( $order, $item, $this->tax_resolver->resolve_tax_mode() );

					$additional_item_attributes = [
						'quantity' => $item->get_quantity(),
						'price'    => $product_price,
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
		}

		return $order_items;
	}

	/**
	 * I the current page the custom order received page
	 *
	 * @param bool $is_order_received_page True when viewing the order received page.
	 *
	 * @return bool
	 */
	public function is_custom_order_received_page( bool $is_order_received_page ): bool {
		// If WooCommerce already detected it, respect that.
		if ( $is_order_received_page ) {
			return true;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		$page_id = $this->options->get( 'integrations', 'woocommerce_custom_order_received_page' );

		if ( ! empty( $page_id ) && is_page( $page_id ) ) {
			return true;
		}

		return false;
	}
}
