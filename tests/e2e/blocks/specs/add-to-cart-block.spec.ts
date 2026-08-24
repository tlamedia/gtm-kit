/**
 * Add to Cart + Options block interactivity.
 *
 * WooCommerce buffers everything third parties print into the classic
 * add-to-cart hooks and scans it for form elements. One input, select,
 * textarea, button or form tag is enough to make the block abandon the
 * Interactivity API and render a plain HTML POST form instead, which costs
 * the shopper the in-place add and forces a full page reload on every add.
 *
 * The mode is not exposed directly, so these assertions read the markup the
 * two paths produce: the Interactivity path binds a submit action, the
 * legacy path renders a `form.cart` posting to the current URL.
 */

import { test, expect } from '@playwright/test';
import { latestEvent, settledEventCount, waitForEvent } from './helpers';

test.describe( 'Add to Cart + Options block', () => {
	test( 'stays on the interactive path with tracking enabled', async ( {
		page,
	} ) => {
		await page.goto( '/add-to-cart-block/' );

		const form = page.locator(
			'form.wp-block-woocommerce-add-to-cart-with-options'
		);
		await expect( form ).toHaveCount( 1 );

		// The Interactivity path handles the add in place.
		await expect( form ).toHaveAttribute(
			'data-wp-on--submit',
			'actions.addToCart'
		);

		// The legacy path's fingerprints: a posting form carrying the
		// classic `cart` class.
		await expect( form ).not.toHaveAttribute( 'method', 'post' );
		await expect(
			page.locator(
				'form.cart.wp-block-woocommerce-add-to-cart-with-options'
			)
		).toHaveCount( 0 );
	} );

	test( 'ships the item payload on a carrier that is not a form element', async ( {
		page,
	} ) => {
		await page.goto( '/add-to-cart-block/' );

		const carrier = page.locator( '.gtmkit_single_product_data' );
		await expect( carrier ).toHaveCount( 1 );

		const payload = await carrier.getAttribute(
			'data-gtmkit_product_data'
		);
		expect( JSON.parse( payload ?? '{}' ).item_name ).toBe(
			'Block Product One'
		);

		// The product-detail carrier must stay out of the page-wide sweep
		// that builds list impressions.
		await expect( page.locator( '.gtmkit_product_data' ) ).toHaveCount( 0 );
	} );

	test( 'reports add_to_cart exactly once for the in-place add', async ( {
		page,
	} ) => {
		await page.goto( '/add-to-cart-block/' );

		await page.locator( '.single_add_to_cart_button' ).first().click();

		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );

		// Keeping the Interactivity path costs the classic reader its
		// `form.cart` hook, so the block bundle reports this add. The same
		// add also reaches the cart store, so exactly one event must
		// survive, not two.
		expect( await settledEventCount( page, 'add_to_cart' ) ).toBe( 1 );

		const add = await latestEvent( page, 'add_to_cart' );
		const items = ( add?.ecommerce?.items ?? [] ) as Array< {
			item_name?: string;
			quantity?: number;
		} >;

		expect( items[ 0 ]?.item_name ).toBe( 'Block Product One' );
		expect( items[ 0 ]?.quantity ).toBe( 1 );
	} );
} );
