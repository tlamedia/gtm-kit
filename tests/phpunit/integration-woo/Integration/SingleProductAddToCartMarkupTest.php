<?php
/**
 * Integration tests for the markup printed into the single-product
 * add-to-cart hooks.
 *
 * WooCommerce's Add to Cart + Options block buffers everything third parties
 * print into those hooks and scans the buffer for form elements. Finding an
 * INPUT, TEXTAREA, SELECT, BUTTON or FORM tag makes the block abandon the
 * Interactivity API and render a plain HTML POST form instead, which costs
 * the shopper the in-place add and forces a full page reload. The scan is an
 * internal WooCommerce detail with no compatibility promise, so the claim
 * these tests pin is ours: our single-product markup contains none of the
 * five tags WooCommerce scans for.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use WC_Helper_Product;
use WP_HTML_Tag_Processor;
use WP_UnitTestCase;

/**
 * Integration tests for {@see WooCommerce::single_product_add_to_cart_tracking()}.
 */
final class SingleProductAddToCartMarkupTest extends WP_UnitTestCase {

	/**
	 * The tags WooCommerce's form-element scan treats as a legacy-mode trigger.
	 *
	 * @var string[]
	 */
	private const FORM_ELEMENTS = [ 'INPUT', 'TEXTAREA', 'SELECT', 'BUTTON', 'FORM' ];

	/**
	 * The product the hook renders for.
	 *
	 * @var \WC_Product|null
	 */
	private $product = null;

	/**
	 * Put a real product in the global the hook reads.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$this->product = WC_Helper_Product::create_simple_product();

		// WooCommerce's own template bootstrap, so the `$product` global the
		// hook reads is populated the way a product page populates it.
		wc_setup_product_data( get_post( $this->product->get_id() ) );
	}

	/**
	 * Clear the product global between tests.
	 */
	public function tear_down(): void {
		wc_setup_product_data( null );
		parent::tear_down();
	}

	/**
	 * Capture what the hook prints.
	 *
	 * @return string
	 */
	private function hook_output(): string {
		ob_start();
		WooCommerce::instance()->single_product_add_to_cart_tracking();

		return (string) ob_get_clean();
	}

	/**
	 * Reproduce WooCommerce's own scan over our hook output.
	 *
	 * @param string $html The markup.
	 *
	 * @return string[] The form element tag names found.
	 */
	private function form_elements_in( string $html ): array {
		$processor = new WP_HTML_Tag_Processor( $html );
		$found     = [];

		while ( $processor->next_tag() ) {
			$tag = (string) $processor->get_tag();

			if ( in_array( $tag, self::FORM_ELEMENTS, true ) ) {
				$found[] = $tag;
			}
		}

		return $found;
	}

	/**
	 * The item payload must reach the page.
	 */
	public function test_hook_emits_the_single_product_carrier(): void {
		$html = $this->hook_output();

		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( [ 'class_name' => 'gtmkit_single_product_data' ] ), 'The hook should emit the single-product carrier span.' );
		$this->assertSame( 'SPAN', $processor->get_tag() );

		$payload = json_decode( (string) $processor->get_attribute( 'data-gtmkit_product_data' ), true );

		$this->assertIsArray( $payload, 'The carrier should hold JSON the browser can parse directly.' );
		$this->assertArrayHasKey( 'item_id', $payload );
		$this->assertSame(
			(string) $this->product->get_id(),
			(string) $processor->get_attribute( 'data-gtmkit_product_id' )
		);
	}

	/**
	 * The regression guard: nothing we print into the add-to-cart hooks may
	 * be a tag WooCommerce reads as a form element.
	 */
	public function test_hook_output_contains_no_form_elements(): void {
		$found = $this->form_elements_in( $this->hook_output() );

		$this->assertSame(
			[],
			$found,
			'Printing a form element into the add-to-cart hooks drops the block add to cart into full-page-reload mode.'
		);
	}

	/**
	 * The product-detail carrier must not answer the page-wide sweep that
	 * builds list impressions, or a product page would report itself as a
	 * `view_item_list`.
	 */
	public function test_single_product_carrier_is_not_matched_by_the_list_sweep(): void {
		$html = $this->hook_output();

		$processor = new WP_HTML_Tag_Processor( $html );

		$this->assertFalse(
			$processor->next_tag( [ 'class_name' => 'gtmkit_product_data' ] ),
			'The single-product carrier must not carry the product-list carrier class.'
		);
	}
}
