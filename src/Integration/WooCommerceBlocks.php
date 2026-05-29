<?php
/**
 * WooCommerce Blocks.
 *
 * Owns everything specific to the WooCommerce block storefront: the Store API
 * extension that exposes the GTM Kit item payload, block detection, and the
 * conditional enqueue of the block tracking bundle. The classic-template path
 * stays in {@see WooCommerce}.
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?hl=en&client_type=gtm
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use WC_Product;

/**
 * WooCommerce block integration.
 */
final class WooCommerceBlocks {

	/**
	 * The canonical block names whose presence enqueues the tracking bundle.
	 * The filter-block family is matched by prefix separately.
	 */
	private const SUPPORTED_BLOCKS = [
		'woocommerce/cart',
		'woocommerce/checkout',
		'woocommerce/mini-cart',
		'woocommerce/all-products',
		'woocommerce/product-collection',
		'woocommerce/single-product',
		'woocommerce/related-products',
	];

	/**
	 * Prefix that identifies the WooCommerce product-filter block family
	 * (Filter by Price, Attribute, Rating, Stock, Active Filters, the
	 * filter wrapper, etc.).
	 */
	private const FILTER_BLOCK_PREFIX = 'woocommerce/product-filter';

	/**
	 * Instance.
	 *
	 * @var null|WooCommerceBlocks
	 */
	private static ?WooCommerceBlocks $instance = null;

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * The WooCommerce integration. Canonical source of the item-data shape.
	 *
	 * @var WooCommerce
	 */
	private WooCommerce $woocommerce;

	/**
	 * Constructor.
	 *
	 * @param Options     $options An instance of Options.
	 * @param WooCommerce $woocommerce The WooCommerce integration, used to shape item data.
	 */
	public function __construct( Options $options, WooCommerce $woocommerce ) {
		$this->options     = $options;
		$this->woocommerce = $woocommerce;
	}

	/**
	 * Get instance.
	 */
	public static function instance(): WooCommerceBlocks {
		return self::$instance;
	}

	/**
	 * Register the block integration.
	 *
	 * @param Options     $options An instance of Options.
	 * @param WooCommerce $woocommerce The WooCommerce integration.
	 */
	public static function register( Options $options, WooCommerce $woocommerce ): void {

		self::$instance = new self( $options, $woocommerce );

		add_action( 'woocommerce_blocks_loaded', [ self::$instance, 'extend_store' ] );

		// Priority 20 so the classic enqueue in WooCommerce::enqueue_scripts() (priority 10)
		// has already registered `gtmkit-woocommerce`; the block path dequeues it on a
		// block-built Cart or Checkout page where the block bundle takes over.
		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_block_assets' ], 20 );

		// Render the GTM Kit item payload into the server-rendered product
		// grids so the block bundle can emit list and view_item events. The
		// markup is identical for every visitor (full-page-cache safe).
		add_filter( 'render_block', [ self::$instance, 'inject_block_product_data' ], 10, 2 );
	}

	/**
	 * The list name stamped onto items for each server-rendered block grid.
	 *
	 * @var array<string, string>
	 */
	private const LIST_BLOCKS = [
		'woocommerce/product-collection' => 'Product Collection',
		'woocommerce/related-products'   => 'Related products',
	];

	/**
	 * The block names whose presence on a page enqueues the tracking bundle.
	 *
	 * Third parties can extend the trigger list (e.g. a custom block wrapping
	 * Product Collection) via the `gtmkit_blocks_supported` filter.
	 *
	 * @return array<int, string>
	 */
	public function get_supported_blocks(): array {
		/**
		 * Filter the canonical list of block names that trigger the GTM Kit
		 * block tracking bundle.
		 *
		 * @param array<int, string> $blocks The canonical block names.
		 */
		$blocks = (array) apply_filters( 'gtmkit_blocks_supported', self::SUPPORTED_BLOCKS );

		return array_values( array_unique( array_filter( array_map( 'strval', $blocks ) ) ) );
	}

	/**
	 * Enqueue the block tracking bundle when a supported block renders.
	 *
	 * Detection is page-content driven, never visitor driven, so the emitted
	 * markup is identical for every visitor on a given page (full-page-cache
	 * safe). The bundle itself decides at runtime which subscribers to mount.
	 */
	public function enqueue_block_assets(): void {

		if ( $this->options->get( 'integrations', 'woocommerce_dequeue_script' ) ) {
			return;
		}

		if ( ! $this->page_has_supported_blocks() ) {
			return;
		}

		$this->util()->enqueue_script( 'gtmkit-woocommerce-blocks', 'frontend/woocommerce-blocks.js', true );

		wp_localize_script(
			'gtmkit-woocommerce-blocks',
			'gtmkitWooCommerceBlocksBuild',
			[
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			]
		);

		// On a block-built Cart or Checkout page the block bundle owns tracking,
		// so the classic integration script is redundant and is removed.
		if ( $this->has_cart_or_checkout_block() ) {
			wp_dequeue_script( 'gtmkit-woocommerce' );
		}
	}

	/**
	 * Whether the current page renders any supported block.
	 */
	public function page_has_supported_blocks(): bool {

		foreach ( $this->get_supported_blocks() as $block_name ) {
			if ( 'woocommerce/mini-cart' === $block_name ) {
				if ( $this->has_mini_cart() ) {
					return true;
				}
				continue;
			}

			if ( has_block( $block_name ) ) {
				return true;
			}
		}

		return $this->has_filter_block();
	}

	/**
	 * Whether the current page renders the Cart or Checkout block.
	 */
	public function has_cart_or_checkout_block(): bool {
		return has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' );
	}

	/**
	 * Whether the current page renders the Mini Cart block.
	 *
	 * The Mini Cart is typically inserted via a block-template part (e.g. the
	 * header) rather than post content, so `has_block()` alone misses it.
	 * `WC_Blocks_Utils::has_block_in_page()` scans the resolved page including
	 * template parts; fall back to the post-content check when it is absent.
	 */
	public function has_mini_cart(): bool {

		$post_id = get_the_ID();

		if (
			$post_id
			&& class_exists( '\WC_Blocks_Utils' )
			&& \WC_Blocks_Utils::has_block_in_page( (int) $post_id, 'woocommerce/mini-cart' )
		) {
			return true;
		}

		return has_block( 'woocommerce/mini-cart' );
	}

	/**
	 * Whether the current page renders any product-filter block.
	 */
	public function has_filter_block(): bool {

		foreach ( $this->get_woocommerce_blocks() as $block_short_name ) {
			if ( strpos( 'woocommerce/' . $block_short_name, self::FILTER_BLOCK_PREFIX ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse the WooCommerce blocks present in a post's content.
	 *
	 * @param int|null $post_id The post ID.
	 *
	 * @return array<int, string> The WooCommerce block names with the `woocommerce/` prefix stripped.
	 */
	public function has_woocommerce_blocks( ?int $post_id ): array {
		if ( null === $post_id ) {
			return [];
		}

		$post_content = get_the_content( null, false, $post_id );

		$woocommerce_blocks = [];

		$blocks = parse_blocks( $post_content );

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && strpos( $block['blockName'], 'woocommerce/' ) === 0 ) {
				$woocommerce_blocks[] = str_replace( 'woocommerce/', '', $block['blockName'] );
			}
		}

		return $woocommerce_blocks;
	}

	/**
	 * The WooCommerce blocks present in the current post's content.
	 *
	 * @return array<int, string>
	 */
	public function get_woocommerce_blocks(): array {
		return $this->has_woocommerce_blocks( get_the_ID() );
	}

	/**
	 * Register the GTM Kit item payload into the Store API product and cart endpoints.
	 */
	public function extend_store(): void {

		$extend = $this->extend();

		$extend->register_endpoint_data(
			[
				'endpoint'        => ProductSchema::IDENTIFIER,
				'namespace'       => 'gtmkit',
				'data_callback'   => [ self::$instance, 'extend_product_data' ],
				'schema_callback' => [ self::$instance, 'extend_product_schema' ],
				'schema_type'     => ARRAY_A,
			]
		);

		$extend->register_endpoint_data(
			[
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => 'gtmkit',
				'data_callback'   => [ self::$instance, 'extend_cart_data' ],
				'schema_callback' => [ self::$instance, 'extend_product_schema' ],
				'schema_type'     => ARRAY_A,
			]
		);
	}

	/**
	 * GTM Kit data for the Store API product endpoint.
	 *
	 * @param WC_Product $product Current product.
	 *
	 * @return array<string, mixed>
	 */
	public function extend_product_data( $product ): array {
		return [
			'item' => $this->woocommerce->get_item_data( $product ),
		];
	}

	/**
	 * GTM Kit data for the Store API cart-item endpoint.
	 *
	 * @param array<string, mixed> $cart_item Cart item data.
	 *
	 * @return array<string, mixed>
	 */
	public function extend_cart_data( array $cart_item ): array {
		return [
			'item' => wp_json_encode( $this->woocommerce->get_item_data( $cart_item['data'] ) ),
		];
	}

	/**
	 * GTM Kit schema registered into the Store API product and cart-item endpoints.
	 *
	 * @return array<string, mixed>
	 */
	public function extend_product_schema(): array {
		return [
			'gtmkit_data' => [
				'description' => __( 'GTM Kit data.', 'gtm-kit' ),
				'type'        => [ 'string', 'null' ],
				'readonly'    => true,
			],
		];
	}

	/**
	 * Inject the GTM Kit item payload into server-rendered block product grids.
	 *
	 * Product Collection and Related Products render each product as a list
	 * item carrying a `post-{ID}` body class; the Single Product block stores
	 * its product id in the block attributes. A hidden `gtmkit_block_product_data`
	 * span is injected per product so the block bundle can read GA4 item data.
	 * The carrier class is deliberately distinct from the classic script's
	 * `gtmkit_product_data` so the two tracking paths never double-fire.
	 *
	 * @hook render_block
	 *
	 * @param string               $block_content The rendered block HTML.
	 * @param array<string, mixed> $block         The parsed block.
	 *
	 * @return string
	 */
	public function inject_block_product_data( string $block_content, array $block ): string {

		$block_name = $block['blockName'] ?? '';

		if ( 'woocommerce/single-product' === $block_name ) {
			return $this->inject_single_product_data( $block_content, $block );
		}

		if ( ! isset( self::LIST_BLOCKS[ $block_name ] ) ) {
			return $block_content;
		}

		// Prefer a block name set in the editor (e.g. two Product Collection
		// blocks named differently) so each list reports a distinct list name.
		$list_name = $block['attrs']['metadata']['name'] ?? self::LIST_BLOCKS[ $block_name ];

		$with_spans = (string) preg_replace_callback(
			'/<li\b[^>]*\bclass="[^"]*\bpost-(\d+)\b[^"]*"[^>]*>/i',
			function ( array $matches ) use ( $list_name ): string {
				$product = wc_get_product( (int) $matches[1] );

				if ( ! ( $product instanceof WC_Product ) ) {
					return $matches[0];
				}

				return $matches[0] . $this->build_block_product_span( $product, (string) $list_name );
			},
			$block_content
		);

		// Stamp the list name on the wrapper so the block bundle's list
		// re-fire resolves the same name on a client-side re-render.
		$attribute = ' data-gtmkit-list-name="' . esc_attr( (string) $list_name ) . '"';

		return (string) preg_replace_callback(
			'/<[a-z0-9]+\b[^>]*\bclass="[^"]*\bwp-block-woocommerce-(?:product-collection|related-products)\b[^"]*"/i',
			static function ( array $matches ) use ( $attribute ): string {
				return $matches[0] . $attribute;
			},
			$with_spans,
			1
		);
	}

	/**
	 * Inject the item payload into a Single Product block.
	 *
	 * @param string               $block_content The rendered block HTML.
	 * @param array<string, mixed> $block         The parsed block.
	 *
	 * @return string
	 */
	private function inject_single_product_data( string $block_content, array $block ): string {

		$product_id = isset( $block['attrs']['productId'] ) ? (int) $block['attrs']['productId'] : 0;

		if ( ! $product_id ) {
			return $block_content;
		}

		$product = wc_get_product( $product_id );

		if ( ! ( $product instanceof WC_Product ) ) {
			return $block_content;
		}

		return (string) preg_replace_callback(
			'/<div\b[^>]*\bclass="[^"]*\bwp-block-woocommerce-single-product\b[^"]*"[^>]*>/i',
			function ( array $matches ) use ( $product ): string {
				return $matches[0] . $this->build_block_product_span( $product, '' );
			},
			$block_content,
			1
		);
	}

	/**
	 * Build the hidden span that carries a product's GA4 item data.
	 *
	 * @param WC_Product $product   The product.
	 * @param string     $list_name The list name to stamp on the item (empty for single product).
	 *
	 * @return string
	 */
	private function build_block_product_span( WC_Product $product, string $list_name ): string {

		$attributes    = ( '' !== $list_name ) ? [ 'item_list_name' => $list_name ] : [];
		$event_context = ( '' !== $list_name ) ? 'product_list' : 'view_item';

		$item_data = $this->woocommerce->get_item_data( $product, $attributes, $event_context );

		return sprintf(
			'<span class="gtmkit_block_product_data" style="display:none;visibility:hidden;" data-gtmkit_product_id="%s" data-gtmkit_product_data="%s"></span>',
			esc_attr( (string) $product->get_id() ),
			esc_attr( (string) wp_json_encode( $item_data ) )
		);
	}

	/**
	 * Resolve the Store API schema extender on demand.
	 *
	 * Resolved lazily (rather than in the constructor) so the block
	 * detection helpers can be exercised without booting the full Store
	 * API container.
	 */
	private function extend(): ExtendSchema {
		// @phpstan-ignore-next-line return.type
		return StoreApi::container()->get( ExtendSchema::class );
	}

	/**
	 * The shared Util instance, borrowed from the WooCommerce integration.
	 */
	private function util(): Util {
		return $this->woocommerce->get_util();
	}
}
