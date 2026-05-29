/**
 * Mini Cart tracking.
 *
 * On a non-cart page with a Mini Cart in the layout, adding a product from
 * a Product Collection must emit one `add_to_cart`, and opening the Mini
 * Cart must emit one `view_cart` — with no duplicates and no spurious
 * `view_cart` from the add itself.
 */

import { test, expect } from '@playwright/test';
import { countEvent, waitForEvent } from './helpers';

test.describe( 'Mini Cart', () => {
	test( 'add_to_cart from a collection, then view_cart on open, no duplicates', async ( {
		page,
	} ) => {
		await page.goto( '/mini-cart-page/' );

		// Add the first product via the collection's add-to-cart button.
		const miniCartButton = page.locator( '.wc-block-mini-cart__button' ).first();
		await expect( miniCartButton ).toHaveAttribute(
			'aria-label',
			/items in the cart: 0/i
		);

		const addButton = page
			.locator(
				'.wp-block-woocommerce-product-collection .wp-block-woocommerce-product-button button'
			)
			.first();
		await addButton.click();

		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );
		expect( await countEvent( page, 'add_to_cart' ) ).toBe( 1 );
		// The add must not also emit a view_cart.
		expect( await countEvent( page, 'view_cart' ) ).toBe( 0 );

		// Regression guard: GTM Kit's tracking must not interfere with the
		// native cart mutation — the Mini Cart count updates without a reload.
		await expect( miniCartButton ).toHaveAttribute(
			'aria-label',
			/items in the cart: (?!0)\d+/i,
			{ timeout: 10_000 }
		);

		// Open the Mini Cart drawer.
		await miniCartButton.click();

		expect( await waitForEvent( page, 'view_cart' ) ).toBe( true );
		expect( await countEvent( page, 'view_cart' ) ).toBe( 1 );

		// Closing and re-opening unchanged must not duplicate view_cart. Wait
		// for the drawer overlay to fully detach before re-opening, otherwise
		// it intercepts the re-open click while animating out.
		const overlay = page.locator(
			'.wc-block-components-drawer__screen-overlay'
		);
		await page.keyboard.press( 'Escape' );
		await overlay.waitFor( { state: 'detached', timeout: 5_000 } ).catch( () => {} );
		await miniCartButton.click();
		await page.waitForTimeout( 500 );
		expect( await countEvent( page, 'view_cart' ) ).toBe( 1 );
	} );
} );
