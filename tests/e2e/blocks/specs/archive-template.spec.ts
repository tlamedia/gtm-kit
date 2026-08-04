/**
 * Real archive-product template tracking.
 *
 * On a block theme, WooCommerce renders the shop, category and tag
 * archives through its blockified `archive-product` template AND
 * re-fires the classic loop hooks inside it (its
 * ArchiveProductTemplatesCompatibility), so both of GTM Kit's tracking
 * paths are invited on every archive. The block path must own these
 * renders: one carrier per product, one `view_item_list` per page load
 * with the archive-aware list name, one `add_to_cart` per loop-button
 * click.
 *
 * Every assertion here COUNTS matching events. A presence assertion
 * stays green over a double-fire, which is exactly how the archive
 * double-fire shipped: the seeded-page specs asserted presence and never
 * rendered this template.
 */

import { test, expect } from '@playwright/test';
import { readEvents, settledEventCount, waitForEvent } from './helpers';

const ARCHIVES = [
	{
		label: 'shop',
		path: '/shop/',
		listName: 'General Product List',
	},
	{
		label: 'category',
		path: '/product-category/e2e-category/',
		listName: 'Product Category',
	},
	{
		label: 'tag',
		path: '/product-tag/e2e-tag/',
		listName: 'Product Tag',
	},
] as const;

test.describe( 'Archive template', () => {
	for ( const archive of ARCHIVES ) {
		test( `${ archive.label } archive emits exactly one view_item_list named "${ archive.listName }"`, async ( {
			page,
		} ) => {
			await page.goto( archive.path );

			expect( await waitForEvent( page, 'view_item_list' ) ).toBe(
				true
			);
			expect(
				await settledEventCount( page, 'view_item_list' )
			).toBe( 1 );

			const lists = ( await readEvents( page ) ).filter(
				( e ) => e.event === 'view_item_list'
			);
			const items = ( lists[ 0 ].ecommerce?.items ?? [] ) as Array< {
				item_list_name?: string;
			} >;
			expect( items.length ).toBeGreaterThan( 0 );
			for ( const item of items ) {
				expect( item.item_list_name ).toBe( archive.listName );
			}

			// The mechanism, not just the symptom: exactly one carrier
			// per product, and it is the block one. A classic carrier
			// surviving here means both paths will read the same product.
			const carriers = await page.evaluate( () => ( {
				classic: document.querySelectorAll(
					'.wp-block-woocommerce-product-template [class="gtmkit_product_data"]'
				).length,
				block: document.querySelectorAll(
					'.wp-block-woocommerce-product-template .gtmkit_block_product_data'
				).length,
			} ) );
			expect( carriers.classic ).toBe( 0 );
			expect( carriers.block ).toBe( items.length );
		} );
	}

	test( 'a loop add-to-cart click on the shop archive emits exactly one add_to_cart with the archive list name', async ( {
		page,
	} ) => {
		await page.goto( '/shop/' );
		await waitForEvent( page, 'view_item_list' );

		// The archive template's product button is the block component
		// variant, which carries BOTH the block component class and the
		// legacy add_to_cart_button class on the same element.
		const button = page
			.locator(
				'.wp-block-woocommerce-product-template .wc-block-components-product-button__button'
			)
			.first();
		await expect( button ).toHaveClass( /add_to_cart_button/ );
		await button.click();

		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );
		expect( await settledEventCount( page, 'add_to_cart' ) ).toBe( 1 );

		const adds = ( await readEvents( page ) ).filter(
			( e ) => e.event === 'add_to_cart'
		);
		const items = ( adds[ 0 ].ecommerce?.items ?? [] ) as Array< {
			item_list_name?: string;
			quantity?: number;
		} >;
		expect( items[ 0 ]?.item_list_name ).toBe( 'General Product List' );
		expect( items[ 0 ]?.quantity ).toBe( 1 );
	} );
} );
