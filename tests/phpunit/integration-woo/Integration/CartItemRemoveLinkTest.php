<?php
/**
 * Integration tests for the product data stamped onto the cart's remove link.
 *
 * The attribute is written with WP_HTML_Tag_Processor, which escapes the
 * value it is given. Pre-escaping the JSON before handing it over encodes
 * the quotes twice; the browser then reads `&quot;` literals instead of
 * JSON, JSON.parse() throws inside the click handler, and the
 * remove_from_cart event is skipped with no console error and no visible
 * symptom.
 *
 * Runs against real WordPress and real WooCommerce so both the escaping
 * and the cart lookup under test are the production ones.
 *
 * @package TLA_Media\GTM_Kit
 */

namespace TLA_Media\GTM_Kit\Tests\IntegrationWoo\Integration;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Integration tests for {@see WooCommerce::cart_item_remove_link()}.
 */
final class CartItemRemoveLinkTest extends WP_UnitTestCase {

	/**
	 * The cart item key of the product seeded into the cart.
	 *
	 * @var string
	 */
	private $cart_item_key = '';

	/**
	 * Seed a product into a real WooCommerce cart.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( WC_Helper_Product::class ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not installed.' );
		}

		$product = WC_Helper_Product::create_simple_product();
		// A double quote in the name is what a second escaping pass mangles:
		// the JSON string delimiter comes back as a `&quot;` literal. An
		// ampersand would be a poor probe here, because WordPress already
		// stores `&` in post titles as `&amp;`.
		$product->set_name( 'Beanie "Deluxe" Edition' );
		$product->save();

		WC()->cart->empty_cart();
		$this->cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
	}

	/**
	 * Empty the cart between tests.
	 */
	public function tear_down(): void {
		WC()->cart->empty_cart();
		parent::tear_down();
	}

	/**
	 * Run the filter the way WooCommerce does and return the link markup.
	 */
	private function remove_link(): string {
		$link = sprintf(
			'<a href="%s" class="remove" aria-label="Remove this item">&times;</a>',
			esc_url( wc_get_cart_remove_url( $this->cart_item_key ) )
		);

		return apply_filters( 'woocommerce_cart_item_remove_link', $link, $this->cart_item_key );
	}

	/**
	 * Read the stamped attribute back off the returned markup.
	 *
	 * @param string $html The link markup.
	 *
	 * @return string|null
	 */
	private function read_attribute( string $html ): ?string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$value = $processor->get_attribute( 'data-gtmkit_product_data' );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * The attribute must decode as JSON in one pass, the way the browser's
	 * getAttribute() + JSON.parse() reads it.
	 */
	public function test_remove_link_attribute_is_single_encoded_json(): void {
		$raw = $this->read_attribute( $this->remove_link() );

		$this->assertIsString( $raw, 'The remove link should carry the product data attribute.' );

		$decoded = json_decode( $raw, true );
		$this->assertIsArray( $decoded, 'The attribute value should be JSON the browser can parse directly.' );
		$this->assertSame( 'Beanie "Deluxe" Edition', $decoded['item_name'] );
		$this->assertArrayHasKey( 'item_id', $decoded );
	}

	/**
	 * Guard the specific regression: the JSON string delimiters must reach
	 * the browser as quotes, not as the `&quot;` literals a second escaping
	 * pass leaves behind.
	 */
	public function test_remove_link_attribute_carries_no_escaped_quotes(): void {
		$raw = (string) $this->read_attribute( $this->remove_link() );

		$this->assertStringNotContainsString( '&quot;', $raw );
		$this->assertStringStartsWith( '{"', $raw );
	}

	/**
	 * The href and class of the original link survive the rewrite, so the
	 * remove action itself keeps working.
	 */
	public function test_existing_link_attributes_are_preserved(): void {
		$processor = new \WP_HTML_Tag_Processor( $this->remove_link() );
		$processor->next_tag();

		$this->assertSame( 'remove', $processor->get_attribute( 'class' ) );
		$this->assertStringContainsString(
			'remove_item',
			(string) $processor->get_attribute( 'href' ),
			'The remove URL should be left intact.'
		);
	}

	/**
	 * An unknown cart item key leaves the link untouched.
	 */
	public function test_unknown_cart_item_key_leaves_the_link_untouched(): void {
		$link     = '<a href="https://example.test/?remove_item=nope" class="remove">&times;</a>';
		$filtered = apply_filters( 'woocommerce_cart_item_remove_link', $link, 'not-a-real-key' );

		$this->assertSame( $link, $filtered );
	}
}
