/**
 * Cart-store subtraction for block product button adds.
 *
 * With a Cart block (or an opened Mini Cart) on the page, wc/store/cart
 * is live and the cart-store subscriber diffs every Store API mutation.
 * A block product button click is reported by the click handler (which
 * knows the list name); the same mutation then appears in the cart diff,
 * and the subscriber must subtract the already-reported units instead of
 * emitting a second, context-free event.
 *
 * N clicks must produce exactly N `add_to_cart` events, every one
 * carrying its list attribution. The counts are taken only after the
 * event stream has settled: the subscriber's duplicate arrives after the
 * Store API round-trip, so an immediate count cannot see it.
 */

import { test, expect } from '@playwright/test';
import { readEvents, settledEventCount, waitForEvent } from './helpers';

const COLLECTION_BUTTON =
	'.wp-block-woocommerce-product-collection .wc-block-components-product-button__button';

const addEvents = async ( page ) =>
	( await readEvents( page ) )
		.filter( ( e ) => e.event === 'add_to_cart' )
		.map( ( e ) => {
			const items = ( e.ecommerce?.items ?? [] ) as Array< {
				item_id?: string;
				item_list_name?: string;
				quantity?: number;
			} >;
			return items[ 0 ] ?? {};
		} );

test.describe( 'Block product buttons with a live cart store', () => {
	test( 'clicks on two products emit exactly two attributed add_to_cart events', async ( {
		page,
	} ) => {
		await page.goto( '/cart-with-buttons/' );
		await waitForEvent( page, 'view_item_list' );

		const buttons = page.locator( COLLECTION_BUTTON );
		await buttons.nth( 0 ).click();
		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );
		await buttons.nth( 1 ).click();

		expect( await settledEventCount( page, 'add_to_cart' ) ).toBe( 2 );

		const adds = await addEvents( page );
		const ids = adds.map( ( a ) => a.item_id );
		expect( new Set( ids ).size ).toBe( 2 );
		for ( const add of adds ) {
			expect( add.item_list_name ).toBe( 'Cart Page Picks' );
			expect( add.quantity ).toBe( 1 );
		}
	} );

	test( 'two clicks on the same product emit exactly two events totalling two units', async ( {
		page,
	} ) => {
		await page.goto( '/cart-with-buttons/' );
		await waitForEvent( page, 'view_item_list' );

		const button = page.locator( COLLECTION_BUTTON ).first();
		await button.click();
		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );
		await button.click();

		expect( await settledEventCount( page, 'add_to_cart' ) ).toBe( 2 );

		const adds = await addEvents( page );
		const units = adds.reduce(
			( sum, a ) => sum + ( a.quantity ?? 0 ),
			0
		);
		expect( units ).toBe( 2 );
		for ( const add of adds ) {
			expect( add.item_list_name ).toBe( 'Cart Page Picks' );
		}
	} );

	test( 'mini-cart page: clicks on two products emit exactly two attributed events', async ( {
		page,
	} ) => {
		await page.goto( '/mini-cart-page/' );
		await waitForEvent( page, 'view_item_list' );

		const buttons = page.locator( COLLECTION_BUTTON );
		await buttons.nth( 0 ).click();
		expect( await waitForEvent( page, 'add_to_cart' ) ).toBe( true );
		await buttons.nth( 1 ).click();

		expect( await settledEventCount( page, 'add_to_cart' ) ).toBe( 2 );

		const adds = await addEvents( page );
		expect( new Set( adds.map( ( a ) => a.item_id ) ).size ).toBe( 2 );
		for ( const add of adds ) {
			expect( add.item_list_name ).toBe( 'Homepage picks' );
		}
	} );
} );
