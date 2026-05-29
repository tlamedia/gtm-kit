/**
 * Single Product block view_item.
 *
 * The Single Product block placed on a non-product page must emit exactly
 * one `view_item`, with no PHP-vs-JS duplicate.
 */

import { test, expect } from '@playwright/test';
import { countEvent, latestEvent, waitForEvent } from './helpers';

test.describe( 'Single Product block', () => {
	test( 'fires view_item exactly once with the correct item', async ( { page } ) => {
		await page.goto( '/single-product-block/' );

		expect( await waitForEvent( page, 'view_item' ) ).toBe( true );
		expect( await countEvent( page, 'view_item' ) ).toBe( 1 );

		const viewItem = await latestEvent( page, 'view_item' );
		const items = ( viewItem?.ecommerce?.items ?? [] ) as Array< {
			item_name?: string;
		} >;
		expect( items.length ).toBeGreaterThan( 0 );
		expect( items[ 0 ].item_name ).toBe( 'Block Product One' );
	} );
} );
