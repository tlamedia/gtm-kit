<?php
/**
 * Unit tests for the WooCommerce block integration.
 *
 * Exercises the block detection helpers, the supported-block trigger
 * list (and its `gtmkit_blocks_supported` filter), and the shape of the
 * Store API data callbacks. WooCommerce itself is not booted: the helpers
 * under test depend only on WordPress block functions, which are stubbed
 * via BrainMonkey. The live Store API registration is exercised by the
 * block E2E suite against a real storefront.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\Unit\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\WooCommerce;
use TLA_Media\GTM_Kit\Integration\WooCommerceBlocks;
use TLA_Media\GTM_Kit\Options\Options;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

/**
 * Unit tests for {@see WooCommerceBlocks}.
 */
final class WooCommerceBlocksTest extends TestCase {

	/**
	 * Build a WooCommerceBlocks bound to a real (dependency-light) WooCommerce.
	 *
	 * @return WooCommerceBlocks
	 */
	private function make_blocks(): WooCommerceBlocks {
		if ( ! defined( 'GTMKIT_PATH' ) ) {
			define( 'GTMKIT_PATH', '/fake/plugin/path/' );
		}
		if ( ! defined( 'GTMKIT_URL' ) ) {
			define( 'GTMKIT_URL', 'https://example.test/wp-content/plugins/gtm-kit/' );
		}

		Functions\stubs(
			[
				'get_option'               => [],
				'add_filter'               => null,
				'add_action'               => null,
				'get_woocommerce_currency' => 'USD',
				'__'                       => static fn( $text ) => $text,
			]
		);

		$options     = Options::create();
		$util        = new Util( $options, new RestAPIServer() );
		$woocommerce = new WooCommerce( $options, $util );

		return new WooCommerceBlocks( $options, $woocommerce );
	}

	/**
	 * Block parsing returns the short names of every WooCommerce block.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::has_woocommerce_blocks
	 */
	public function test_has_woocommerce_blocks_classifies_supported_blocks(): void {
		$blocks = $this->make_blocks();

		Functions\when( 'get_the_content' )->justReturn( '' );
		Functions\when( 'parse_blocks' )->justReturn(
			[
				[ 'blockName' => 'woocommerce/cart' ],
				[ 'blockName' => 'woocommerce/checkout' ],
				[ 'blockName' => 'woocommerce/mini-cart' ],
				[ 'blockName' => 'woocommerce/all-products' ],
				[ 'blockName' => 'woocommerce/product-collection' ],
				[ 'blockName' => 'woocommerce/single-product' ],
				[ 'blockName' => 'woocommerce/related-products' ],
				[ 'blockName' => 'woocommerce/product-filter-price' ],
				[ 'blockName' => 'core/paragraph' ],
				[ 'blockName' => '' ],
			]
		);

		$result = $blocks->has_woocommerce_blocks( 99 );

		$this->assertContains( 'cart', $result );
		$this->assertContains( 'checkout', $result );
		$this->assertContains( 'mini-cart', $result );
		$this->assertContains( 'all-products', $result );
		$this->assertContains( 'product-collection', $result );
		$this->assertContains( 'single-product', $result );
		$this->assertContains( 'related-products', $result );
		$this->assertContains( 'product-filter-price', $result );
		$this->assertNotContains( 'paragraph', $result, 'Non-WooCommerce blocks must be excluded.' );
	}

	/**
	 * A null post id yields an empty block list.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::has_woocommerce_blocks
	 */
	public function test_has_woocommerce_blocks_returns_empty_for_null_post(): void {
		$this->assertSame( [], $this->make_blocks()->has_woocommerce_blocks( null ) );
	}

	/**
	 * The supported-blocks list is the seven canonical names by default.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::get_supported_blocks
	 */
	public function test_get_supported_blocks_returns_canonical_list(): void {
		$blocks = $this->make_blocks();

		Filters\expectApplied( 'gtmkit_blocks_supported' )
			->once()
			->andReturnFirstArg();

		$supported = $blocks->get_supported_blocks();

		$this->assertContains( 'woocommerce/cart', $supported );
		$this->assertContains( 'woocommerce/checkout', $supported );
		$this->assertContains( 'woocommerce/mini-cart', $supported );
		$this->assertContains( 'woocommerce/all-products', $supported );
		$this->assertContains( 'woocommerce/product-collection', $supported );
		$this->assertContains( 'woocommerce/single-product', $supported );
		$this->assertContains( 'woocommerce/related-products', $supported );
		$this->assertCount( 7, $supported );
	}

	/**
	 * The gtmkit_blocks_supported filter can extend the trigger list.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::get_supported_blocks
	 */
	public function test_get_supported_blocks_honors_filter(): void {
		$blocks = $this->make_blocks();

		Filters\expectApplied( 'gtmkit_blocks_supported' )
			->once()
			->andReturnUsing(
				static function ( array $blocks ): array {
					$blocks[] = 'acme/custom-collection';
					return $blocks;
				}
			);

		$this->assertContains( 'acme/custom-collection', $blocks->get_supported_blocks() );
	}

	/**
	 * The cart-or-checkout check reflects the presence of either block.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::has_cart_or_checkout_block
	 */
	public function test_has_cart_or_checkout_block(): void {
		$blocks = $this->make_blocks();

		Functions\when( 'has_block' )->alias(
			static fn( string $name ): bool => 'woocommerce/checkout' === $name
		);

		$this->assertTrue( $blocks->has_cart_or_checkout_block() );

		Functions\when( 'has_block' )->justReturn( false );
		$this->assertFalse( $blocks->has_cart_or_checkout_block() );
	}

	/**
	 * The page detection method finds a single supported block.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::page_has_supported_blocks
	 */
	public function test_page_has_supported_blocks_detects_single_block(): void {
		$blocks = $this->make_blocks();

		Filters\expectApplied( 'gtmkit_blocks_supported' )->andReturnFirstArg();
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_the_content' )->justReturn( '' );
		Functions\when( 'parse_blocks' )->justReturn( [] );
		Functions\when( 'has_block' )->alias(
			static fn( string $name ): bool => 'woocommerce/product-collection' === $name
		);

		$this->assertTrue( $blocks->page_has_supported_blocks() );
	}

	/**
	 * The page detection method finds the product-filter family by prefix.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::page_has_supported_blocks
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::has_filter_block
	 */
	public function test_page_has_supported_blocks_detects_filter_family(): void {
		$blocks = $this->make_blocks();

		Filters\expectApplied( 'gtmkit_blocks_supported' )->andReturnFirstArg();
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'has_block' )->justReturn( false );
		Functions\when( 'get_the_content' )->justReturn( '' );
		Functions\when( 'parse_blocks' )->justReturn(
			[ [ 'blockName' => 'woocommerce/product-filter-attribute' ] ]
		);

		$this->assertTrue( $blocks->page_has_supported_blocks() );
	}

	/**
	 * The page detection method is false when nothing matches.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::page_has_supported_blocks
	 */
	public function test_page_has_supported_blocks_false_when_absent(): void {
		$blocks = $this->make_blocks();

		Filters\expectApplied( 'gtmkit_blocks_supported' )->andReturnFirstArg();
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'has_block' )->justReturn( false );
		Functions\when( 'get_the_content' )->justReturn( '' );
		Functions\when( 'parse_blocks' )->justReturn( [ [ 'blockName' => 'core/paragraph' ] ] );

		$this->assertFalse( $blocks->page_has_supported_blocks() );
	}

	/**
	 * The product data callback wraps the item payload under an `item` key.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::extend_product_data
	 */
	public function test_extend_product_data_wraps_item(): void {
		$blocks = $this->make_blocks();

		// A non-WC_Product yields an empty item from get_item_data, which is
		// enough to assert the `item` envelope shape without booting WooCommerce.
		$result = $blocks->extend_product_data( null );

		$this->assertArrayHasKey( 'item', $result );
		$this->assertSame( [], $result['item'] );
	}

	/**
	 * The cart data callback JSON-encodes the item payload under an `item` key.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::extend_cart_data
	 */
	public function test_extend_cart_data_json_encodes_item(): void {
		$blocks = $this->make_blocks();

		Functions\when( 'wp_json_encode' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test stub standing in for wp_json_encode without booting WP.
			static fn( $data ): string => (string) json_encode( $data )
		);

		$result = $blocks->extend_cart_data( [ 'data' => null ] );

		$this->assertArrayHasKey( 'item', $result );
		$this->assertSame( '[]', $result['item'] );
	}

	/**
	 * The product schema callback declares the gtmkit_data read-only field.
	 *
	 * @covers \TLA_Media\GTM_Kit\Integration\WooCommerceBlocks::extend_product_schema
	 */
	public function test_extend_product_schema_shape(): void {
		$schema = $this->make_blocks()->extend_product_schema();

		$this->assertArrayHasKey( 'gtmkit_data', $schema );
		$this->assertSame( [ 'string', 'null' ], $schema['gtmkit_data']['type'] );
		$this->assertTrue( $schema['gtmkit_data']['readonly'] );
	}
}
