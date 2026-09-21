<?php
/**
 * Multi-product, mixed-rate companion to `run-tax-matrix.php`.
 *
 * Adds a 10% reduced-rate tax row, assigns the Belt product (default ID
 * 30) to the `reduced-rate` class, then runs the four-cell matrix with
 * a cart containing TWO line items at DIFFERENT rates and quantities:
 *
 *  - Cap   (id 31, qty 2, standard 25%)
 *  - Belt  (id 30, qty 1, reduced 10%)
 *
 * Asserts the headline invariant `sum(items[].price * qty) === value`
 * within a 1-cent tolerance (per the spec). Snapshots and restores all
 * state, including the tax-rate row and Belt's tax class.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/gtm-kit/tests/manual/run-tax-matrix-multi.php [cap_id] [belt_id]
 *
 * @package TLA_Media\GTM_Kit
 */

use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Integration\WooCommerce as GTMKitWooCommerce;
use TLA_Media\GTM_Kit\Options\Options;

global $wpdb;

$cap_id  = isset( $args[0] ) ? (int) $args[0] : 31;
$belt_id = isset( $args[1] ) ? (int) $args[1] : 30;

WP_CLI::log( "Multi-product matrix: cap_id={$cap_id}  belt_id={$belt_id}" );

$cap  = wc_get_product( $cap_id );
$belt = wc_get_product( $belt_id );
if ( ! $cap || ! $belt ) {
	WP_CLI::error( 'Cap or Belt product not found.' );
}

// ---------------------------------------------------------------- snapshot
$snapshot = [
	'woocommerce_prices_include_tax' => get_option( 'woocommerce_prices_include_tax' ),
	'woocommerce_tax_display_shop'   => get_option( 'woocommerce_tax_display_shop' ),
	'woocommerce_tax_display_cart'   => get_option( 'woocommerce_tax_display_cart' ),
	'gtmkit'                         => get_option( 'gtmkit', [] ),
	'belt_tax_class'                 => $belt->get_tax_class(),
];
WP_CLI::log( '  snapshot: belt_tax_class="' . $snapshot['belt_tax_class'] . '"' );

// Add a 10% reduced-rate row under the existing `reduced-rate` class.
$wpdb->insert(
	$wpdb->prefix . 'woocommerce_tax_rates',
	[
		'tax_rate'          => '10.0000',
		'tax_rate_class'    => 'reduced-rate',
		'tax_rate_country'  => '',
		'tax_rate_state'    => '',
		'tax_rate_name'     => 'Reduced VAT (test)',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 0,
	]
);
$reduced_rate_id = (int) $wpdb->insert_id;
WP_CLI::log( "  setup: inserted reduced-rate tax row id={$reduced_rate_id}" );

// Assign Belt to the reduced-rate class.
$belt->set_tax_class( 'reduced-rate' );
$belt->save();

wp_cache_flush();
WC_Tax::get_rates_from_location( 'standard', [] );

// ---------------------------------------------------------------- helpers
$apply_state = static function ( array $cell ): void {
	update_option( 'woocommerce_prices_include_tax', $cell['prices_include_tax'] );
	update_option( 'woocommerce_tax_display_shop', $cell['display'] );
	update_option( 'woocommerce_tax_display_cart', $cell['display'] );

	$gtmkit                                              = get_option( 'gtmkit', [] );
	$gtmkit['integrations']                              = is_array( $gtmkit['integrations'] ?? null ) ? $gtmkit['integrations'] : [];
	$gtmkit['integrations']['woocommerce_exclude_tax']   = $cell['toggle'];
	update_option( 'gtmkit', $gtmkit );

	wp_cache_flush();
	WC_Tax::get_rates_from_location( 'standard', [] );
};

$run_cell = static function ( int $cap_id, int $belt_id, array $cell ): array {
	$options = Options::create();
	$util    = new Util( $options, new RestAPIServer() );
	$wc      = new GTMKitWooCommerce( $options, $util );

	WC()->cart->empty_cart( true );
	WC()->cart->add_to_cart( $cap_id, 2 );
	WC()->cart->add_to_cart( $belt_id, 1 );
	WC()->cart->calculate_totals();

	$ref = new ReflectionClass( $wc );
	$gd  = $ref->getProperty( 'global_data' );
	if ( \PHP_VERSION_ID < 80100 ) {
		$gd->setAccessible( true );
	}
	$gd->setValue( $wc, [ 'wc' => [ 'cart_items' => $wc->get_cart_items( 'begin_checkout' ) ] ] );

	$view_cart      = $wc->get_datalayer_content_cart( [] );
	$begin_checkout = $wc->get_datalayer_content_checkout( [] );

	$order = wc_create_order();
	$order->add_product( wc_get_product( $cap_id ), 2 );
	$order->add_product( wc_get_product( $belt_id ), 1 );
	$order->calculate_totals();
	$purchase = $wc->get_purchase_event( $order, [] );
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
	$lines     = [];
	foreach ( $items as $item ) {
		$qty   = (int) ( $item['quantity'] ?? 1 );
		$price = (float) ( $item['price'] ?? 0 );
		$sum  += $price * $qty;
		$lines[] = sprintf( '%s×%d@%.4f', $item['item_name'] ?? '?', $qty, $price );
	}
	// Spec accepts 1 cent / 1 ore drift between sum-of-rounded-items and
	// the cart's twice-rounded value.
	$ok = ( null !== $value ) && abs( (float) $value - $sum ) <= 0.01;

	WP_CLI::log( sprintf(
		'  %s %-14s value=%-9s sum_items=%-9s drift=%-7s items=[%s] %s',
		$cell_label,
		$event,
		number_format( (float) $value, 4, '.', '' ),
		number_format( $sum, 4, '.', '' ),
		number_format( abs( (float) $value - $sum ), 4, '.', '' ),
		implode( ', ', $lines ),
		$ok ? 'PASS' : 'FAIL'
	) );

	return [
		'invariant_ok' => $ok,
		'drift'        => abs( (float) $value - $sum ),
	];
};

// ---------------------------------------------------------------- matrix
$cells = [
	[
		'label'              => 'cell-1 control      ',
		'prices_include_tax' => 'yes',
		'display'            => 'incl',
		'toggle'             => false,
	],
	[
		'label'              => 'cell-2 andersbolander',
		'prices_include_tax' => 'no',
		'display'            => 'incl',
		'toggle'             => false,
	],
	[
		'label'              => 'cell-3 toggle ON    ',
		'prices_include_tax' => 'no',
		'display'            => 'incl',
		'toggle'             => true,
	],
	[
		'label'              => 'cell-4 toggle ON+inc-prices',
		'prices_include_tax' => 'yes',
		'display'            => 'incl',
		'toggle'             => true,
	],
];

$any_fail   = false;
$max_drift  = 0.0;

foreach ( $cells as $cell ) {
	WP_CLI::log( sprintf( '%s prices_include_tax=%s display=%s toggle=%s',
		$cell['label'], $cell['prices_include_tax'], $cell['display'], $cell['toggle'] ? 'ON' : 'OFF'
	) );

	$apply_state( $cell );
	$payloads = $run_cell( $cap_id, $belt_id, $cell );

	foreach ( $payloads as $event => $payload ) {
		$row = $check( $cell['label'], $event, $payload );
		if ( ! $row['invariant_ok'] ) {
			$any_fail = true;
		}
		if ( $row['drift'] > $max_drift ) {
			$max_drift = $row['drift'];
		}
	}
}

// ---------------------------------------------------------------- restore
update_option( 'woocommerce_prices_include_tax', $snapshot['woocommerce_prices_include_tax'] );
update_option( 'woocommerce_tax_display_shop', $snapshot['woocommerce_tax_display_shop'] );
update_option( 'woocommerce_tax_display_cart', $snapshot['woocommerce_tax_display_cart'] );
update_option( 'gtmkit', $snapshot['gtmkit'] );

$belt_restore = wc_get_product( $belt_id );
$belt_restore->set_tax_class( $snapshot['belt_tax_class'] );
$belt_restore->save();

$wpdb->delete( $wpdb->prefix . 'woocommerce_tax_rates', [ 'tax_rate_id' => $reduced_rate_id ] );

WC()->cart->empty_cart( true );
wp_cache_flush();

WP_CLI::log( '  restore OK (cache flushed, reduced-rate row removed, belt class reset)' );

if ( $any_fail ) {
	WP_CLI::error( sprintf( 'invariant FAILED in at least one cell. max drift=%.4f', $max_drift ) );
}

WP_CLI::success( sprintf( 'all cells passed within 1-cent tolerance. max observed drift=%.4f', $max_drift ) );
