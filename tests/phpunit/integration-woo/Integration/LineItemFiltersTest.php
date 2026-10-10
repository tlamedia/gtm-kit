<?php
/**
 * Integration tests for the filters that expose the cart line and the order
 * line to the data layer item builders.
 *
 * The product-level filter cannot see data stored on a cart line or an
 * order line, so an add-on that keeps per-line data (a list the product was
 * added from, for instance) needs the line itself. These tests run against
 * a real WooCommerce cart and order so the arguments handed to the filters
 * are the production ones.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use WC_Helper_Order;
use WC_Helper_Product;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_UnitTestCase;

/**
 * Integration tests for `gtmkit_datalayer_cart_item_data` and
 * `gtmkit_datalayer_order_item_data`.
 */
final class LineItemFiltersTest extends WP_UnitTestCase {

	/**
	 * The product seeded into the cart or the order.
	 *
	 * @var WC_Product
	 */
	private $product;

	/**
	 * Seed a product and start from an empty cart.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$this->product = WC_Helper_Product::create_simple_product();
		WC()->cart->empty_cart();
	}

	/**
	 * Leave no cart line and no callback behind.
	 */
	public function tear_down(): void {
		WC()->cart->empty_cart();
		remove_all_filters( 'gtmkit_datalayer_cart_item_data' );
		remove_all_filters( 'gtmkit_datalayer_order_item_data' );
		parent::tear_down();
	}

	/**
	 * The cart filter gets the built item, the cart line, its key and the
	 * event context, and what it returns is what the event carries.
	 */
	public function test_cart_item_filter_receives_the_line_and_its_return_value_is_used(): void {
		$cart_item_key = WC()->cart->add_to_cart( $this->product->get_id(), 2 );
		$seen          = [];

		add_filter(
			'gtmkit_datalayer_cart_item_data',
			static function ( array $item, array $cart_item, string $key, string $event_context ) use ( &$seen ): array {
				$seen[]                 = [ $cart_item, $key, $event_context ];
				$item['item_list_name'] = 'Line ' . $key;
				return $item;
			},
			10,
			4
		);

		$items = WooCommerce::instance()->get_cart_items( 'begin_checkout' );

		$this->assertCount( 1, $items );
		$this->assertCount( 1, $seen );
		$this->assertSame( $this->product->get_id(), $seen[0][0]['product_id'] );
		$this->assertSame( $cart_item_key, $seen[0][1] );
		$this->assertSame( 'begin_checkout', $seen[0][2] );
		$this->assertSame( 'Line ' . $cart_item_key, $items[0]['item_list_name'] );
		$this->assertSame( 2, $items[0]['quantity'], 'The item handed to the filter is the finished one, quantity included.' );
	}

	/**
	 * Without a callback the cart items are exactly what the product-level
	 * builder produces.
	 */
	public function test_cart_items_are_unchanged_without_a_callback(): void {
		WC()->cart->add_to_cart( $this->product->get_id(), 3 );

		$woocommerce = WooCommerce::instance();
		$items       = $woocommerce->get_cart_items( 'view_cart' );

		$this->assertCount( 1, $items );
		$this->assertSame(
			$woocommerce->get_item_data( $this->product, [ 'quantity' => 3 ], 'view_cart' ),
			$items[0]
		);
	}

	/**
	 * The order filter gets the built item, the order line and the order, and
	 * what it returns is what the purchase event carries.
	 */
	public function test_order_item_filter_receives_the_line_and_its_return_value_is_used(): void {
		$order = WC_Helper_Order::create_order( 0, $this->product );
		$seen  = [];

		add_filter(
			'gtmkit_datalayer_order_item_data',
			static function ( array $item, $order_item, $filtered_order ) use ( &$seen ): array {
				$seen[]                 = [ $order_item, $filtered_order ];
				$item['item_list_name'] = 'Order line';
				return $item;
			},
			10,
			3
		);

		$event = WooCommerce::instance()->get_purchase_event( $order );
		$items = $event['ecommerce']['items'];

		$this->assertCount( 1, $items );
		$this->assertCount( 1, $seen );
		$this->assertInstanceOf( WC_Order_Item_Product::class, $seen[0][0] );
		$this->assertSame( $this->product->get_id(), $seen[0][0]->get_product_id() );
		$this->assertInstanceOf( WC_Order::class, $seen[0][1] );
		$this->assertSame( $order->get_id(), $seen[0][1]->get_id() );
		$this->assertSame( 'Order line', $items[0]['item_list_name'] );
	}

	/**
	 * Without a callback the purchase items carry no line-level additions.
	 */
	public function test_order_items_are_unchanged_without_a_callback(): void {
		$order = WC_Helper_Order::create_order( 0, $this->product );

		$event = WooCommerce::instance()->get_purchase_event( $order );

		$this->assertCount( 1, $event['ecommerce']['items'] );
		$this->assertArrayNotHasKey( 'item_list_name', $event['ecommerce']['items'][0] );
		$this->assertSame( WooCommerce::instance()->prefix_item_id( (string) $this->product->get_id() ), $event['ecommerce']['items'][0]['item_id'] );
	}
}
