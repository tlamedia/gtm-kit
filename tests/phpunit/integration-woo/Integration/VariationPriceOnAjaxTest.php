<?php
/**
 * Integration tests for the variation price on AJAX requests.
 *
 * Themes that load product variations over admin-ajax read the
 * tax-resolved price GTM Kit adds to each variation, but admin-ajax runs as
 * an admin request, which the frontend bootstrap skips. The AJAX bootstrap
 * attaches only the variation filter, so the price is present there too.
 *
 * Runs against real WordPress and real WooCommerce so the variation payload
 * under test is the production one.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Integration tests for {@see WooCommerce::register_ajax()}.
 */
final class VariationPriceOnAjaxTest extends WP_UnitTestCase {

	/**
	 * A variable product with priced variations.
	 *
	 * @var \WC_Product_Variable|null
	 */
	private $product = null;

	/**
	 * Create the product and detach the frontend-registered filter so the
	 * AJAX bootstrap is the only thing that can put the price back.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$this->product = WC_Helper_Product::create_variation_product();

		remove_filter( 'woocommerce_available_variation', [ WooCommerce::instance(), 'add_variation_tax_resolved_price' ], 10 );
	}

	/**
	 * Leave the filter the way the frontend bootstrap registered it.
	 */
	public function tear_down(): void {
		if ( ! has_filter( 'woocommerce_available_variation', [ WooCommerce::instance(), 'add_variation_tax_resolved_price' ] ) ) {
			add_filter( 'woocommerce_available_variation', [ WooCommerce::instance(), 'add_variation_tax_resolved_price' ], 10, 3 );
		}
		parent::tear_down();
	}

	/**
	 * The first variation's payload, as WooCommerce builds it for the browser.
	 *
	 * @return array<string, mixed>
	 */
	private function first_variation(): array {
		$children = $this->product->get_children();
		$this->assertNotEmpty( $children, 'The helper product must have variations.' );

		return $this->product->get_available_variation( wc_get_product( $children[0] ) );
	}

	/**
	 * The frontend-registered filter is the only thing that adds the price
	 * today; without it the payload has none.
	 */
	public function test_variation_payload_has_no_price_without_the_frontend_filter(): void {
		$this->assertArrayNotHasKey( 'gtmkit_price', $this->first_variation() );
	}

	/**
	 * The AJAX bootstrap puts the tax-resolved price on the payload.
	 */
	public function test_ajax_bootstrap_adds_the_tax_resolved_price(): void {
		$this->assertFalse( wp_doing_ajax(), 'The test runner is not an AJAX request; the bootstrap is called directly.' );

		\TLA_Media\GTM_Kit\gtmkit_ajax_init();

		$variation = $this->first_variation();

		$this->assertArrayHasKey( 'gtmkit_price', $variation );
		$this->assertIsFloat( $variation['gtmkit_price'] );
		$this->assertSame( (float) $variation['display_price'], $variation['gtmkit_price'] );
	}

	/**
	 * Running the bootstrap again does not stack the filter.
	 */
	public function test_ajax_bootstrap_registers_the_filter_once(): void {
		\TLA_Media\GTM_Kit\gtmkit_ajax_init();
		\TLA_Media\GTM_Kit\gtmkit_ajax_init();

		global $wp_filter;
		$callbacks = $wp_filter['woocommerce_available_variation']->callbacks[10] ?? [];
		$matches   = 0;
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][1] === 'add_variation_tax_resolved_price' ) {
				++$matches;
			}
		}

		$this->assertSame( 1, $matches );
	}
}
