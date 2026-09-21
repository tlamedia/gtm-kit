<?php
/**
 * Manual end-to-end tax-handling matrix.
 *
 * Runs four cells against a real WC store. For each cell:
 *  1. Sets WC tax options + the gtmkit `woocommerce_exclude_tax` toggle.
 *  2. Optionally installs a `gtmkit_resolve_tax_mode` filter override.
 *  3. Builds a fresh cart with the test product and a fresh WC_Order.
 *  4. Calls the migrated WooCommerce class methods and captures the
 *     dataLayer payloads for `view_cart`, `begin_checkout`, `purchase`.
 *  5. Asserts `sum(items[].price * qty) === ecommerce.value` per cell.
 *
 * Snapshots all settings at start, restores them at the end.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/gtm-kit/tests/manual/run-tax-matrix.php <product_id>
 *
 * @package TLA_Media\GTM_Kit
 */

use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\WooCommerce as GTMKitWooCommerce;
use TLA_Media\GTM_Kit\Options\Options;

$product_id = isset( $args[0] ) ? (int) $args[0] : 31;

WP_CLI::log( "Tax-handling matrix: product_id={$product_id}" );

$product = wc_get_product( $product_id );
if ( ! $product ) {
	WP_CLI::error( "Product {$product_id} not found." );
}

WP_CLI::log( sprintf( '  product: "%s" stored price=%s tax_class=%s', $product->get_name(), $product->get_price(), $product->get_tax_class() ?: '(standard)' ) );

// ---------------------------------------------------------------- snapshot
$snapshot = [
	'woocommerce_prices_include_tax' => get_option( 'woocommerce_prices_include_tax' ),
	'woocommerce_tax_display_shop'   => get_option( 'woocommerce_tax_display_shop' ),
	'woocommerce_tax_display_cart'   => get_option( 'woocommerce_tax_display_cart' ),
	'gtmkit'                         => get_option( 'gtmkit', [] ),
];
WP_CLI::log( '  snapshot OK' );

// ---------------------------------------------------------------- helpers
$apply_state = static function ( array $cell ): void {
	update_option( 'woocommerce_prices_include_tax', $cell['prices_include_tax'] );
	update_option( 'woocommerce_tax_display_shop', $cell['display'] );
	update_option( 'woocommerce_tax_display_cart', $cell['display'] );

	$gtmkit                                              = get_option( 'gtmkit', [] );
	$gtmkit['integrations']                              = is_array( $gtmkit['integrations'] ?? null ) ? $gtmkit['integrations'] : [];
	$gtmkit['integrations']['woocommerce_exclude_tax']   = $cell['toggle'];
	update_option( 'gtmkit', $gtmkit );

	// Persistent object caches (Redis, Memcached) can hold WC tax-rate
	// rows, product objects, and alloptions snapshots from before the
	// change. A full flush guarantees the next call rebuilds tax math
	// from current settings.
	wp_cache_flush();

	// Force WooCommerce to re-read tax settings.
	WC_Tax::get_rates_from_location( 'standard', [] );
};

$run_cell = static function ( int $product_id, array $cell ): array {
	$options = Options::create();
	$util    = new Util( $options, new RestAPIServer() );
	$wc      = new GTMKitWooCommerce( $options, $util );

	// Build a fresh cart.
	WC()->cart->empty_cart( true );
	WC()->cart->add_to_cart( $product_id, 1 );
	WC()->cart->calculate_totals();

	// `get_datalayer_content_checkout()` reads cart_items from the
	// protected $global_data populated during a real request by
	// `get_global_data()`. In CLI we don't pass through that filter, so
	// inject the items via reflection.
	$ref = new ReflectionClass( $wc );
	$gd  = $ref->getProperty( 'global_data' );
	if ( \PHP_VERSION_ID < 80100 ) {
		$gd->setAccessible( true );
	}
	$gd->setValue( $wc, [ 'wc' => [ 'cart_items' => $wc->get_cart_items( 'begin_checkout' ) ] ] );

	$view_cart      = $wc->get_datalayer_content_cart( [] );
	$begin_checkout = $wc->get_datalayer_content_checkout( [] );

	// Build a fresh order with the same product.
	$order = wc_create_order();
	$order->add_product( wc_get_product( $product_id ), 1 );
	$order->calculate_totals();
	$purchase = $wc->get_purchase_event( $order, [] );

	// Clean up the test order so we do not pollute the store.
	$order->delete( true );

	WC()->cart->empty_cart( true );

	return [
		'view_cart'      => $view_cart,
		'begin_checkout' => $begin_checkout,
		'purchase'       => $purchase,
	];
};

$check = static function ( string $cell_label, string $event, array $payload ): array {
	$ecommerce = $payload['ecommerce'] ?? [];
	$value     = $ecommerce['value'] ?? null;
	$items     = $ecommerce['items'] ?? [];
	$sum       = 0.0;
	foreach ( $items as $item ) {
		$qty   = (int) ( $item['quantity'] ?? 1 );
		$price = (float) ( $item['price'] ?? 0 );
		$sum  += $price * $qty;
	}
	$ok = ( null !== $value ) && abs( (float) $value - $sum ) < 0.01;

	$first_price = $items[0]['price'] ?? null;
	WP_CLI::log( sprintf(
		'  %s %-14s value=%-10s sum_items=%-10s items[0].price=%-10s %s',
		$cell_label,
		$event,
		number_format( (float) $value, 4, '.', '' ),
		number_format( $sum, 4, '.', '' ),
		null === $first_price ? '(none)' : number_format( (float) $first_price, 4, '.', '' ),
		$ok ? 'PASS' : 'FAIL'
	) );

	return [
		'event'        => $event,
		'value'        => $value,
		'sum_items'    => $sum,
		'first_price'  => $first_price,
		'invariant_ok' => $ok,
	];
};

// ---------------------------------------------------------------- matrix
$cells = [
	[
		'label'              => 'cell-1 control      ',
		'prices_include_tax' => 'yes',
		'display'            => 'incl',
		'toggle'             => false,
		'filter'             => null,
	],
	[
		'label'              => 'cell-2 andersbolander',
		'prices_include_tax' => 'no',
		'display'            => 'incl',
		'toggle'             => false,
		'filter'             => null,
	],
	[
		'label'              => 'cell-3 toggle ON    ',
		'prices_include_tax' => 'no',
		'display'            => 'incl',
		'toggle'             => true,
		'filter'             => null,
	],
	[
		'label'              => 'cell-4 filter force ',
		'prices_include_tax' => 'yes',
		'display'            => 'incl',
		'toggle'             => false,
		'filter'             => true, // force ex-tax via filter, overriding the OFF toggle.
	],
];

$results = [];
foreach ( $cells as $cell ) {
	WP_CLI::log( sprintf( '%s prices_include_tax=%s display=%s toggle=%s filter=%s',
		$cell['label'], $cell['prices_include_tax'], $cell['display'],
		$cell['toggle'] ? 'ON' : 'OFF',
		null === $cell['filter'] ? '-' : ( $cell['filter'] ? 'force-ex-tax' : 'force-inc-tax' )
	) );

	$apply_state( $cell );

	$filter_cb = null;
	if ( null !== $cell['filter'] ) {
		$forced    = (bool) $cell['filter'];
		$filter_cb = static function () use ( $forced ): bool {
			return $forced;
		};
		add_filter( 'gtmkit_resolve_tax_mode', $filter_cb, 999 );
	}

	$payloads = $run_cell( $product_id, $cell );

	if ( null !== $filter_cb ) {
		remove_filter( 'gtmkit_resolve_tax_mode', $filter_cb, 999 );
	}

	$cell_results = [];
	foreach ( $payloads as $event => $payload ) {
		$cell_results[ $event ] = $check( $cell['label'], $event, $payload );
	}
	$results[ $cell['label'] ] = $cell_results;
}

// ---------------------------------------------------------------- restore
update_option( 'woocommerce_prices_include_tax', $snapshot['woocommerce_prices_include_tax'] );
update_option( 'woocommerce_tax_display_shop', $snapshot['woocommerce_tax_display_shop'] );
update_option( 'woocommerce_tax_display_cart', $snapshot['woocommerce_tax_display_cart'] );
update_option( 'gtmkit', $snapshot['gtmkit'] );
WC()->cart->empty_cart( true );
wp_cache_flush();

WP_CLI::log( '  restore OK (cache flushed)' );

// ---------------------------------------------------------------- summary
$any_fail = false;
foreach ( $results as $cell_results ) {
	foreach ( $cell_results as $row ) {
		if ( ! $row['invariant_ok'] ) {
			$any_fail = true;
			break 2;
		}
	}
}

if ( $any_fail ) {
	WP_CLI::error( 'invariant FAILED in at least one cell.' );
}

WP_CLI::success( 'all cells passed: sum(items[].price * qty) === ecommerce.value' );
