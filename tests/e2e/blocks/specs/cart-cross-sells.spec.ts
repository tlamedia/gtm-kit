/**
 * Cart block cross-sells tracking.
 *
 * The Cart block's "You may be interested in…" grid is client-rendered
 * from the cart Store API (`crossSells`). It must emit `view_item_list`
 * for the cross-sell products and `select_item` when one is clicked.
 * (`add_to_cart` from a cross-sell is covered by the cart-store path.)
 *
 * The seed links Block Product One → Block Product Two as a cross-sell.
 */

import { test, expect } from '@playwright/test';
import { readEvents, countEvent, waitForEvent } from './helpers';

const CROSS_SELL_NAME = 'Block Product Two';

test.describe( 'Cart block cross-sells', () => {
	test( 'emits view_item_list for cross-sells and select_item on click', async ( {
		page,
	} ) => {
		// Add the product that has a cross-sell, then open the Cart block.
		await page.goto( '/block-shop/' );
		await page
			.locator(
				'.wp-block-woocommerce-product-collection .wp-block-woocommerce-product-button button'
			)
			.first()
			.click();
		await page.waitForTimeout( 2500 );

		await page.goto( '/cart/' );

		// view_item_list fires with the cross-sell carrying the list name.
		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );
		const lists = ( await readEvents( page ) ).filter(
			( e ) => e.event === 'view_item_list'
		);
		const crossSellList = lists.find( ( e ) => {
			const items = ( e.ecommerce?.items ?? [] ) as Array< {
				item_list_name?: string;
				item_name?: string;
			} >;
			return items.some( ( i ) => i.item_name === CROSS_SELL_NAME );
		} );
		expect( crossSellList, 'a view_item_list contains the cross-sell' ).toBeTruthy();
		const crossItem = (
			( crossSellList?.ecommerce?.items ?? [] ) as Array< {
				item_list_name?: string;
				item_name?: string;
			} >
		 ).find( ( i ) => i.item_name === CROSS_SELL_NAME );
		expect( crossItem?.item_list_name ).toBe( 'Cross-sells' );

		// Clicking the cross-sell product link emits one select_item.
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

		await page.locator( 'a[href*="block-product-two"]' ).first().click();

		expect( await waitForEvent( page, 'select_item' ) ).toBe( true );
		expect( await countEvent( page, 'select_item' ) ).toBe( 1 );
		const selects = ( await readEvents( page ) ).filter(
			( e ) => e.event === 'select_item'
		);
		const selItems = ( selects[ 0 ].ecommerce?.items ?? [] ) as Array< {
			item_name?: string;
		} >;
		expect( selItems[ 0 ].item_name ).toBe( CROSS_SELL_NAME );
	} );
} );
