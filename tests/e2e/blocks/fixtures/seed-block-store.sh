#!/usr/bin/env bash
#
# wp-env afterStart hook for the block E2E harness.
#
# Configures the tests environment (port 8891) with a block-built storefront:
# a Product Collection page, a Single Product block page, a Mini Cart page, and
# WooCommerce's default block-based Cart and Checkout pages (left untouched).
#
# Idempotent. Re-runs do not duplicate state. Anchors uniqueness on product
# SKUs, the shipping zone name, and the page slugs.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"
WP_ENV_BIN="${REPO_ROOT}/node_modules/.bin/wp-env"

if [ ! -x "$WP_ENV_BIN" ]; then
    echo "[seed-block-store] wp-env binary not found at $WP_ENV_BIN. Run \`npm install\` first." >&2
    exit 1
fi

WP_ENV="${WP_ENV:-tests-cli}"

run_wp() {
    "$WP_ENV_BIN" run "$WP_ENV" wp "$@"
}

log() { echo "[seed-block-store] $*"; }

log "Configuring WooCommerce store options..."
run_wp option update woocommerce_default_country 'US:CA'
run_wp option update woocommerce_currency 'USD'
run_wp option update woocommerce_calc_taxes 'no'
run_wp option update woocommerce_enable_guest_checkout 'yes'
run_wp option update woocommerce_enable_checkout_login_reminder 'no'
run_wp option update woocommerce_cod_settings \
    '{"enabled":"yes","title":"Cash on delivery","description":"Pay with cash upon delivery.","instructions":"Pay with cash upon delivery.","enable_for_methods":[],"enable_for_virtual":"yes"}' \
    --format=json

log "Seeding GTM Kit options..."
run_wp eval '
update_option(
    "gtmkit",
    [
        "general"      => [
            "gtm_id"           => "GTM-TEST123",
            "container_active" => "1",
            "datalayer_name"   => "dataLayer",
        ],
        "integrations" => [
            "woocommerce_integration"               => "1",
            "woocommerce_variable_product_tracking" => "1",
            "woocommerce_view_item_list_limit"      => 12,
            "woocommerce_shipping_info"             => "1",
            "woocommerce_payment_info"              => "1",
        ],
    ],
    true
);'

log "Ensuring test products exist..."
run_wp eval '
$skus = [ "BLOCK-PROD-001" => "Block Product One", "BLOCK-PROD-002" => "Block Product Two" ];
foreach ( $skus as $sku => $name ) {
    if ( wc_get_product_id_by_sku( $sku ) > 0 ) { continue; }
    $product = new WC_Product_Simple();
    $product->set_name( $name );
    $product->set_slug( sanitize_title( $name ) );
    $product->set_sku( $sku );
    $product->set_regular_price( "19.99" );
    $product->set_price( "19.99" );
    $product->set_status( "publish" );
    $product->set_catalog_visibility( "visible" );
    $product->set_stock_status( "instock" );
    $product->save();
    echo "Created {$name}\n";
}
'

log "Linking a cross-sell (Product One → Product Two) for the Cart block..."
run_wp eval '
$one = wc_get_product_id_by_sku( "BLOCK-PROD-001" );
$two = wc_get_product_id_by_sku( "BLOCK-PROD-002" );
if ( $one && $two ) {
    $product = wc_get_product( $one );
    $product->set_cross_sell_ids( [ $two ] );
    $product->save();
    echo "Cross-sell linked\n";
}
'

log "Ensuring flat-rate shipping zone exists..."
run_wp eval '
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
    if ( "E2E Test Zone" === $z["zone_name"] ) { return; }
}
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( "E2E Test Zone" );
$zone->add_location( "US", "country" );
$zone->save();
$zone->add_shipping_method( "flat_rate" );
$methods     = $zone->get_shipping_methods();
$method      = reset( $methods );
$instance_id = isset( $method->instance_id ) ? (int) $method->instance_id : 0;
if ( $instance_id > 0 ) {
    update_option( "woocommerce_flat_rate_" . $instance_id . "_settings", [ "title" => "Flat rate", "tax_status" => "none", "cost" => "5.00" ] );
}
echo "Created E2E Test Zone\n";
'

log "Creating the block storefront pages..."
run_wp eval-file wp-content/plugins/gtm-kit/tests/e2e/blocks/fixtures/seed-pages.php

log "Refreshing permalinks..."
run_wp rewrite structure '/%postname%/' --hard
run_wp rewrite flush --hard

log "Done."
