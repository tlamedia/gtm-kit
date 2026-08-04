<?php
/**
 * Integration tests for the carrier spans injected into block product grids.
 *
 * WooCommerce re-fires the classic archive loop hooks inside block-rendered
 * product templates (its ArchiveProductTemplatesCompatibility), so a plugin
 * with both a classic and a block path is invited to stamp every product
 * twice. GTM Kit has both, which produced a duplicate `view_item_list` and a
 * duplicate `add_to_cart` on every block-theme archive. The block path owns
 * block-rendered lists, so the classic carrier is stripped from the content
 * the block path claims.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerceBlocks;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Integration tests for {@see WooCommerceBlocks::inject_block_product_data()}.
 */
final class BlockProductDataInjectionTest extends WP_UnitTestCase {

	/**
	 * A real product to render into the grid markup.
	 *
	 * @var \WC_Product|null
	 */
	private $product = null;

	/**
	 * Create the product the fixture markup refers to.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$this->product = WC_Helper_Product::create_simple_product();
	}

	/**
	 * Block grid markup as WooCommerce renders it, including the classic
	 * carrier that the compatibility layer's hook emits inside the item.
	 *
	 * @return string
	 */
	private function grid_markup(): string {
		return sprintf(
			'<ul class="wp-block-woocommerce-product-template">' .
				'<li class="wc-block-product post-%1$d product type-product">' .
					'<a href="#">Product</a>' .
					'<span class="gtmkit_product_data" style="display:none; visibility:hidden;" data-gtmkit_product_id="%1$d" data-gtmkit_product_data="{}"></span>' .
				'</li>' .
			'</ul>',
			$this->product->get_id()
		);
	}

	/**
	 * Invoke the plugin's `render_block` callback directly.
	 *
	 * Calling it through `apply_filters( 'render_block', ... )` would also run
	 * core callbacks that take a third `WP_Block` argument, which is not
	 * available here and is irrelevant to what these tests assert.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 *
	 * @return string
	 */
	private function render( array $attrs = [] ): string {
		return WooCommerceBlocks::instance()->inject_block_product_data(
			$this->grid_markup(),
			[
				'blockName' => 'woocommerce/product-collection',
				'attrs'     => $attrs,
			]
		);
	}

	/**
	 * Count occurrences of a carrier class in the rendered markup.
	 *
	 * The block carrier contains the classic class name as a substring, so
	 * the classic one is matched on its exact class attribute.
	 *
	 * @param string $html          The markup.
	 * @param string $carrier_class The carrier class.
	 *
	 * @return int
	 */
	private function count_carrier( string $html, string $carrier_class ): int {
		return substr_count( $html, 'class="' . $carrier_class . '"' );
	}

	/**
	 * The classic carrier must not survive into block-rendered content, and
	 * the block carrier must take its place.
	 */
	public function test_block_render_replaces_the_classic_carrier(): void {
		$html = $this->render();

		$this->assertSame(
			0,
			$this->count_carrier( $html, 'gtmkit_product_data' ),
			'The classic carrier should be stripped from block-rendered content.'
		);
		$this->assertSame(
			1,
			$this->count_carrier( $html, 'gtmkit_block_product_data' ),
			'The block carrier should be injected exactly once per product.'
		);
	}

	/**
	 * A curated collection keeps the block's own list name.
	 */
	public function test_curated_collection_keeps_the_block_list_name(): void {
		$html = $this->render( [ 'query' => [ 'inherit' => false ] ] );

		$this->assertStringContainsString( 'Product Collection', $html );
	}

	/**
	 * An editor-supplied block name still wins over everything else.
	 */
	public function test_editor_supplied_block_name_wins(): void {
		$html = $this->render(
			[
				'metadata' => [ 'name' => 'Featured picks' ],
				'query'    => [ 'inherit' => true ],
			]
		);

		$this->assertStringContainsString( 'Featured picks', $html );
	}

	/**
	 * A collection that inherits the template query on a category archive
	 * reports the same list name the classic loop reports there, so moving a
	 * store to a block theme does not silently rename its lists.
	 */
	public function test_inherited_collection_on_a_category_archive_uses_the_archive_list_name(): void {
		$term = wp_insert_term( 'Compat Hats', 'product_cat' );
		$this->assertIsArray( $term );
		wp_set_object_terms( $this->product->get_id(), [ (int) $term['term_id'] ], 'product_cat' );

		$this->go_to( (string) get_term_link( (int) $term['term_id'], 'product_cat' ) );
		$this->assertTrue( is_product_category(), 'Expected the category archive to be the queried route.' );

		$html = $this->render( [ 'query' => [ 'inherit' => true ] ] );

		$this->assertStringContainsString( 'Product Category', $html );
		$this->assertStringNotContainsString( 'Product Collection', $html );
	}
}
