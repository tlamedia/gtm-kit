<?php
/**
 * Block storefront page seeder, run via `wp eval-file` from
 * seed-block-store.sh. Creates the block-built pages the E2E specs visit.
 *
 * Idempotent: pages are keyed by slug and updated in place on re-run.
 *
 * @package GTM Kit
 */

/**
 * Create or update a page by slug.
 *
 * @param string $slug    Page slug.
 * @param string $title   Page title.
 * @param string $content Block markup.
 *
 * @return int The page ID.
 */
function gtmkit_e2e_upsert_page( string $slug, string $title, string $content ): int {
	$existing = get_page_by_path( $slug );

	if ( $existing instanceof WP_Post ) {
		wp_update_post(
			[
				'ID'           => $existing->ID,
				'post_content' => $content,
			]
		);
		return (int) $existing->ID;
	}

	return (int) wp_insert_post(
		[
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => $content,
		]
	);
}

/**
 * A minimal, named Product Collection block.
 *
 * @param string $name The editor block name (becomes the GA4 list name).
 *
 * @return string
 */
function gtmkit_e2e_collection_block( string $name ): string {
	$attrs = wp_json_encode(
		[
			'queryId'       => 0,
			'query'         => [
				'perPage'  => 9,
				'pages'    => 0,
				'offset'   => 0,
				'postType' => 'product',
				'order'    => 'asc',
				'orderBy'  => 'title',
				'inherit'  => false,
			],
			'metadata'      => [ 'name' => $name ],
			'displayLayout' => [
				'type'    => 'flex',
				'columns' => 3,
			],
		]
	);

	return <<<HTML
<!-- wp:woocommerce/product-collection {$attrs} -->
<div class="wp-block-woocommerce-product-collection">
<!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"isDescendentOfQueryLoop":true} /-->
<!-- wp:post-title {"level":3,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->
<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true} /-->
<!-- /wp:woocommerce/product-template -->
</div>
<!-- /wp:woocommerce/product-collection -->
HTML;
}

$search_block = '<!-- wp:woocommerce/product-search {"label":"Search","placeholder":"Search products"} /-->';

$shop_content =
	gtmkit_e2e_collection_block( 'Homepage picks' ) . "\n" .
	gtmkit_e2e_collection_block( 'Staff favourites' ) . "\n" .
	$search_block;

$shop_id = gtmkit_e2e_upsert_page( 'block-shop', 'Block Shop', $shop_content );
echo "block-shop id={$shop_id}\n";

$mini_cart_content =
	"<!-- wp:woocommerce/mini-cart /-->\n" .
	gtmkit_e2e_collection_block( 'Homepage picks' );

$mini_id = gtmkit_e2e_upsert_page( 'mini-cart-page', 'Mini Cart Page', $mini_cart_content );
echo "mini-cart-page id={$mini_id}\n";

$product_id     = (int) wc_get_product_id_by_sku( 'BLOCK-PROD-001' );
$single_content =
	"<!-- wp:woocommerce/single-product {\"productId\":{$product_id}} -->\n" .
	'<div class="wp-block-woocommerce-single-product"></div>' . "\n" .
	'<!-- /wp:woocommerce/single-product -->';

$single_id = gtmkit_e2e_upsert_page( 'single-product-block', 'Single Product Block', $single_content );
echo "single-product-block id={$single_id}\n";

// Legacy product grid (Hand-picked Products): server-rendered, tracked by
// the classic path via the .gtmkit_product_data carrier. Represents the
// whole legacy grid family (On Sale, Newest, Best Sellers, etc.).
$one = (int) wc_get_product_id_by_sku( 'BLOCK-PROD-001' );
$two = (int) wc_get_product_id_by_sku( 'BLOCK-PROD-002' );
$legacy_content = sprintf(
	'<!-- wp:woocommerce/handpicked-products {"editMode":false,"columns":2,"products":[%d,%d]} /-->',
	$one,
	$two
);
$legacy_id = gtmkit_e2e_upsert_page( 'legacy-grid', 'Legacy Grid', $legacy_content );
echo "legacy-grid id={$legacy_id}\n";

// Paginated Product Collection (one product per page) so navigating pages
// exercises a collection re-query reporting the correct view_item_list.
$paged_content = <<<HTML
<!-- wp:woocommerce/product-collection {"queryId":7,"query":{"perPage":1,"pages":0,"offset":0,"postType":"product","order":"asc","orderBy":"title","inherit":false},"metadata":{"name":"Paged"},"displayLayout":{"type":"flex","columns":1}} -->
<div class="wp-block-woocommerce-product-collection">
<!-- wp:woocommerce/product-template -->
<!-- wp:woocommerce/product-image {"isDescendentOfQueryLoop":true} /-->
<!-- wp:post-title {"level":3,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->
<!-- /wp:woocommerce/product-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-numbers /-->
<!-- /wp:query-pagination -->
</div>
<!-- /wp:woocommerce/product-collection -->
HTML;
$paged_id = gtmkit_e2e_upsert_page( 'paged-collection', 'Paged Collection', $paged_content );
echo "paged-collection id={$paged_id}\n";
