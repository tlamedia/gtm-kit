/**
 * End-to-end block purchase journey.
 *
 * Block Shop (Product Collection) → add to cart → Cart block → Checkout
 * block → place order (COD) → thank-you. Asserts the GA4 ecommerce events
 * fire across the journey, and that `purchase` fires exactly once with the
 * expected shape.
 *
 * Block checkout field ids follow the @woocommerce/block-checkout
 * convention (e.g. `#email`, `#billing-first_name`). If a WooCommerce
 * release renames them, update the selectors here.
 */

import { test, expect } from '@playwright/test';
import { countEvent, latestEvent, waitForEvent } from './helpers';

const CURRENCY = 'USD';

test.describe( 'block purchase journey', () => {
	test( 'fires the ecommerce events end to end and purchase exactly once', async ( {
		page,
	} ) => {
		// 1. Product Collection list view.
		await page.goto( '/block-shop/' );
		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );

		// 2. Add the first product to the cart from the collection.
		await page
			.locator(
				'.wp-block-woocommerce-product-collection .wp-block-woocommerce-product-button button'
			)
			.first()
			.click();
		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );

		// 3. Cart block.
		await page.goto( '/cart/' );
		expect( await waitForEvent( page, 'view_cart' ) ).toBe( true );

		// 4. Checkout block.
		await page.goto( '/checkout/' );
		expect( await waitForEvent( page, 'begin_checkout' ) ).toBe( true );

		// 5. Fill the block checkout shipping fields (guest checkout). The
		// store default country/state (US:CA) is pre-filled. Blur each field
		// so the block's controlled inputs commit before the address pushes.
		const fillField = async ( id: string, value: string ) => {
			const field = page.locator( `#${ id }` );
			await field.fill( value );
			await field.blur();
		};
		await fillField( 'email', 'block.buyer@example.com' );
		await fillField( 'shipping-first_name', 'Block' );
		await fillField( 'shipping-last_name', 'Buyer' );
		await fillField( 'shipping-address_1', '123 E2E Lane' );
		await fillField( 'shipping-city', 'San Francisco' );
		await fillField( 'shipping-postcode', '94103' );
		await fillField( 'shipping-phone', '5551234567' );

		// Let the shipping rates recalculate against the entered address, then
		// select the flat-rate option.
		await page.waitForTimeout( 2000 );
		const rate = page.locator( '#radio-control-0-flat_rate\\:1' );
		if ( await rate.count() ) {
			await rate.check().catch( () => {} );
		}

		// Ensure Cash on delivery is selected.
		const cod = page.locator(
			'#radio-control-wc-payment-method-options-cod'
		);
		if ( ( await cod.count() ) && ! ( await cod.isChecked() ) ) {
			await cod.check();
		}
		await page.waitForTimeout( 1000 );

		// 6. Place the order. With the shipping/payment toggles at "on submit",
		// add_shipping_info and add_payment_info fire as the order is placed.
		await page
			.locator( '.wc-block-components-checkout-place-order-button' )
			.click();

		// 7. Wait for the thank-you page, then assert purchase fires once.
		await page.waitForURL( '**/order-received/**', { timeout: 30_000 } );
		expect( await waitForEvent( page, 'purchase', 20_000 ) ).toBe( true );
		expect( await countEvent( page, 'purchase' ) ).toBe( 1 );

		const purchase = await latestEvent( page, 'purchase' );
		expect( purchase?.ecommerce ).toBeTruthy();
		expect( ( purchase?.ecommerce as { currency?: string } ).currency ).toBe(
			CURRENCY
		);
		expect(
			( purchase?.ecommerce as { transaction_id?: unknown } ).transaction_id
		).toBeTruthy();
	} );
} );
