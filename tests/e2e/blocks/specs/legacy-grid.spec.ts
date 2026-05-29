/**
 * Legacy product-grid block tracking.
 *
 * The legacy grid family (Hand-picked Products, On Sale, Newest, Best
 * Sellers, Top Rated, Products by Category/Tag/Attribute) is
 * server-rendered with a `.gtmkit_product_data` carrier (the
 * woocommerce_blocks_product_grid_item_html injection) and tracked by the
 * classic script. This spec uses Hand-picked Products as the
 * representative case: it must emit view_item_list on load and select_item
 * on a product click.
 */

import { test, expect } from '@playwright/test';
import { countEvent, readEvents, waitForEvent } from './helpers';

test.describe( 'Legacy product grid', () => {
	test( 'emits view_item_list on load and select_item on click', async ( {
		page,
	} ) => {
		await page.goto( '/legacy-grid/' );

		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );
		const list = ( await readEvents( page ) ).find(
			( e ) => e.event === 'view_item_list'
		);
		const items = ( list?.ecommerce?.items ?? [] ) as Array< {
			item_name?: string;
		} >;
		expect( items.length ).toBeGreaterThanOrEqual( 2 );

		// Clicking a product link emits select_item (prevent navigation so the
		// synchronous push stays observable).
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

		await page
			.locator( '.wc-block-grid__product a' )
			.first()
			.click();

		expect( await waitForEvent( page, 'select_item' ) ).toBe( true );
		expect( await countEvent( page, 'select_item' ) ).toBe( 1 );
	} );
} );
