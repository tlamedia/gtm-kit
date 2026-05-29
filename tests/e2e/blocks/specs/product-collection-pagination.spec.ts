/**
 * Product Collection re-query (pagination).
 *
 * A paginated Product Collection (one product per page) must report the
 * correct view_item_list for each page's product set. Pagination performs
 * a full navigation under the default block setup, so each page emits a
 * fresh view_item_list — the same user-facing outcome a filter produces
 * when it re-queries the collection. (Client-side re-render without a
 * reload is covered by the product-collection unit test.)
 */

import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { readEvents, waitForEvent } from './helpers';

const firstNames = async ( page: Page ) => {
	const list = ( await readEvents( page ) ).find(
		( e ) => e.event === 'view_item_list'
	);
	return ( ( list?.ecommerce?.items ?? [] ) as Array< { item_name?: string } > ).map(
		( i ) => i.item_name
	);
};

test.describe( 'Product Collection pagination', () => {
	test( 'reports the correct view_item_list for each page', async ( {
		page,
	} ) => {
		await page.goto( '/paged-collection/' );

		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );
		const pageOne = await firstNames( page );
		expect( pageOne ).toHaveLength( 1 );

		// Navigate to page 2 (full navigation) and assert a fresh
		// view_item_list for the second page's product.
		await page.goto( '/paged-collection/?query-7-page=2' );

		expect( await waitForEvent( page, 'view_item_list' ) ).toBe( true );
		const pageTwo = await firstNames( page );
		expect( pageTwo ).toHaveLength( 1 );
		expect( pageTwo[ 0 ] ).not.toBe( pageOne[ 0 ] );
	} );
} );
