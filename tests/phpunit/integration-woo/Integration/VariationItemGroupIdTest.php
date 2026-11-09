<?php
/**
 * Integration tests for the parent product id on variation items.
 *
 * A variation is reported under its own id, so a report or a feed cannot
 * tie it back to the product it belongs to. Every item built for a
 * variation carries `item_group_id`, the parent's id in the same format as
 * `item_id`. Runs against real WooCommerce products so the SKU fallbacks
 * under test are the production ones.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use TLA_Media\GTM_Kit\Integration\WooCommerceBlocks;
use WC_Helper_Product;
use WC_Product;
use WC_Product_Variable;
use WP_UnitTestCase;

/**
 * Integration tests for `item_group_id` in {@see WooCommerce::get_item_data()}.
 */
final class VariationItemGroupIdTest extends WP_UnitTestCase {

	/**
	 * A variable product with variations.
	 *
	 * @var WC_Product_Variable
	 */
	private $parent;

	/**
	 * The first variation of the parent.
	 *
	 * @var WC_Product
	 */
	private $variation;

	/**
	 * Option values the read filter answers with, keyed `group.key`.
	 *
	 * @var array<string, mixed>
	 */
	private $option_overrides = [];

	/**
	 * Seed a variable product and route the SKU and prefix options through
	 * the read filter.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$this->parent = WC_Helper_Product::create_variation_product();
		$this->parent->set_sku( 'PARENT-SKU' );
		$this->parent->save();

		$this->variation = wc_get_product( $this->parent->get_children()[0] );
		$this->variation->set_sku( 'VARIATION-SKU' );
		$this->variation->save();

		$this->option_overrides = [];
		add_filter( 'gtmkit_option_value', [ $this, 'override_option' ], 10, 3 );
	}

	/**
	 * Leave no filter behind.
	 */
	public function tear_down(): void {
		remove_filter( 'gtmkit_option_value', [ $this, 'override_option' ], 10 );
		parent::tear_down();
	}

	/**
	 * Answer an option read from the overrides set by the test.
	 *
	 * @param mixed  $value The resolved value.
	 * @param string $group The option group.
	 * @param string $key   The option key.
	 *
	 * @return mixed
	 */
	public function override_option( $value, string $group, string $key ) {
		return array_key_exists( $group . '.' . $key, $this->option_overrides ) ? $this->option_overrides[ $group . '.' . $key ] : $value;
	}

	/**
	 * Build the item the way every data layer event does.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return array<string, mixed>
	 */
	private function item( WC_Product $product ): array {
		return WooCommerce::instance()->get_item_data( $product );
	}

	/**
	 * In ID mode the variation points to the parent's id.
	 */
	public function test_variation_carries_the_parent_id(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = false;

		$item = $this->item( $this->variation );

		$this->assertSame( (string) $this->variation->get_id(), $item['item_id'] );
		$this->assertSame( (string) $this->parent->get_id(), $item['item_group_id'] );
	}

	/**
	 * In SKU mode the variation points to the parent's SKU.
	 */
	public function test_variation_carries_the_parent_sku_in_sku_mode(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = true;

		$item = $this->item( $this->variation );

		$this->assertSame( 'VARIATION-SKU', $item['item_id'] );
		$this->assertSame( 'PARENT-SKU', $item['item_group_id'] );
	}

	/**
	 * In SKU mode a parent without a SKU falls back to its id, as item_id does.
	 */
	public function test_parent_without_sku_falls_back_to_its_id(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = true;
		$this->parent->set_sku( '' );
		$this->parent->save();

		$item = $this->item( wc_get_product( $this->variation->get_id() ) );

		$this->assertSame( 'VARIATION-SKU', $item['item_id'] );
		$this->assertSame( (string) $this->parent->get_id(), $item['item_group_id'] );
	}

	/**
	 * The item id prefix applies to the parent id too.
	 */
	public function test_prefix_applies_to_the_parent_id(): void {
		$this->option_overrides['integrations.woocommerce_use_sku']           = true;
		$this->option_overrides['integrations.woocommerce_product_id_prefix'] = 'shop_';

		$item = $this->item( $this->variation );

		$this->assertSame( 'shop_VARIATION-SKU', $item['item_id'] );
		$this->assertSame( 'shop_PARENT-SKU', $item['item_group_id'] );
	}

	/**
	 * A simple product, and the variable parent itself, carry no group id.
	 */
	public function test_non_variations_carry_no_group_id(): void {
		$simple = WC_Helper_Product::create_simple_product();

		$this->assertArrayNotHasKey( 'item_group_id', $this->item( $simple ) );
		$this->assertArrayNotHasKey( 'item_group_id', $this->item( $this->parent ) );
	}

	/**
	 * The new field is appended: the fields that were there keep their order.
	 */
	public function test_existing_fields_keep_their_order(): void {
		$keys = array_keys( $this->item( $this->variation ) );

		$this->assertSame( [ 'item_variant', 'item_group_id' ], array_slice( $keys, -2 ) );
		$this->assertSame( [ 'id', 'item_id', 'item_name', 'currency', 'price' ], array_slice( $keys, 0, 5 ) );
	}

	/**
	 * In SKU mode a variation without its own SKU reports the parent's, as
	 * WooCommerce does, so both fields hold the parent SKU.
	 */
	public function test_variation_without_sku_reports_the_parent_sku_twice(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = true;
		$this->variation->set_sku( '' );
		$this->variation->save();

		$item = $this->item( wc_get_product( $this->variation->get_id() ) );

		$this->assertSame( 'PARENT-SKU', $item['item_id'] );
		$this->assertSame( 'PARENT-SKU', $item['item_group_id'] );
	}

	/**
	 * A variation class that reports its own type, as subscription
	 * variations do, still carries the parent id.
	 */
	public function test_variation_with_its_own_type_carries_the_parent_id(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = false;

		// WooCommerce Subscriptions points its variation type at the variation data store.
		$store = static function ( array $stores ): array {
			$stores['product-subscription_variation'] = 'WC_Product_Variation_Data_Store_CPT';
			return $stores;
		};
		add_filter( 'woocommerce_data_stores', $store );

		$variation = new class( $this->variation->get_id() ) extends \WC_Product_Variation {
			/**
			 * The type a subscription variation reports.
			 *
			 * @return string
			 */
			public function get_type() {
				return 'subscription_variation';
			}
		};

		remove_filter( 'woocommerce_data_stores', $store );

		$item = $this->item( $variation );

		$this->assertSame( (string) $this->parent->get_id(), $item['item_group_id'] );
	}

	/**
	 * The Store API cart line for a variation carries the parent id.
	 *
	 * The line is shaped the way the cart hands it to the endpoint callback;
	 * only its product is read.
	 */
	public function test_store_api_cart_line_carries_the_parent_id(): void {
		$this->option_overrides['integrations.woocommerce_use_sku'] = true;

		$line = [
			'product_id'   => $this->parent->get_id(),
			'variation_id' => $this->variation->get_id(),
			'quantity'     => 1,
			'data'         => $this->variation,
		];

		$data = WooCommerceBlocks::instance()->extend_cart_data( $line );
		$item = json_decode( $data['item'], true );

		$this->assertSame( 'VARIATION-SKU', $item['item_id'] );
		$this->assertSame( 'PARENT-SKU', $item['item_group_id'] );
	}
}
