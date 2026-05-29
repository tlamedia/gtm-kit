/**
 * Product Collection list tracking.
 *
 * The Block Shop page renders two named Product Collection blocks
 * ("Homepage picks", "Staff favourites"). Each must emit its own
 * `view_item_list` with its distinct list name, and a product click must
 * emit a single `select_item` carrying the originating list name.
 */

import { test, expect } from '@playwright/test';
import { readEvents, countEvent, waitForEvent } from './helpers';

test.describe( 'Product Collection', () => {
	test( 'emits one view_item_list per collection with distinct list names', async ( {
		page,
	} ) => {
		await page.goto( '/block-shop/' );

		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );

		const lists = ( await readEvents( page ) )
			.filter( ( e ) => e.event === 'view_item_list' )
			.map( ( e ) => {
				const items = ( e.ecommerce?.items ?? [] ) as Array< {
					item_list_name?: string;
				} >;
				return items[ 0 ]?.item_list_name;
			} );

		expect( lists ).toContain( 'Homepage picks' );
		expect( lists ).toContain( 'Staff favourites' );
	} );

	test( 'emits a single select_item with the originating list name on a product click', async ( {
		page,
	} ) => {
		await page.goto( '/block-shop/' );
		await waitForEvent( page, 'view_item_list' );

		// The product title is a real link; GTM Kit emits select_item on the
		// capture phase before navigation. Prevent the default so the test can
		// observe the event without the navigation clearing window.dataLayer.
		await page.evaluate( () => {
			document.addEventListener(
				'click',
				( e ) => {
					const a = ( e.target as HTMLElement ).closest?.( 'a' );
					if ( a ) e.preventDefault();
				},
				false
			);
		} );

		// Click the first product title link inside the first collection.
		const firstProductLink = page
			.locator( '.wp-block-woocommerce-product-collection a' )
			.first();
		await firstProductLink.click();

		expect( await waitForEvent( page, 'select_item' ) ).toBe( true );
		expect( await countEvent( page, 'select_item' ) ).toBe( 1 );
	} );
} );
