/**
 * Covers the review request: it shows only when the server says so, each
 * answer hides it and is sent to the site, and the review link opens
 * WordPress.org in a new tab.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

import { render, screen, fireEvent } from '@testing-library/react';

import ReviewAsk from '../ReviewAsk';
import SettingsService from '../../../services/SettingsService';
import { sendReviewAskAction } from '../../../api/settings';

const URL = 'https://wordpress.org/support/plugin/gtm-kit/reviews/#new-post';

vi.mock( '../../../services/SettingsService', () => ( {
	__esModule: true,
	default: { getReviewAsk: vi.fn() },
} ) );

vi.mock( '../../../api/settings', () => ( {
	sendReviewAskAction: vi.fn( () => Promise.resolve() ),
} ) );

beforeEach( () => {
	vi.clearAllMocks();
	SettingsService.getReviewAsk.mockReturnValue( { url: URL } );
} );

describe( 'ReviewAsk', () => {
	it( 'renders nothing when the server does not ask', () => {
		SettingsService.getReviewAsk.mockReturnValue( null );
		const { container } = render( <ReviewAsk /> );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'links to the WordPress.org review form in a new tab', () => {
		render( <ReviewAsk /> );
		const link = screen.getByRole( 'link', { name: /Write a review/ } );

		expect( link ).toHaveAttribute( 'href', URL );
		expect( link ).toHaveAttribute( 'target', '_blank' );
		expect( link.getAttribute( 'rel' ) ).toContain( 'noopener' );
	} );

	it.each( [
		[ /Write a review/, 'link', 'reviewed' ],
		[ 'Not now', 'button', 'snooze' ],
		[ "Don't ask again", 'button', 'dismiss' ],
	] )( '"%s" hides it and records %s', ( name, role, action ) => {
		const { container } = render(
			<main>
				<ReviewAsk />
			</main>
		);

		fireEvent.click( screen.getByRole( role, { name } ) );

		expect( sendReviewAskAction ).toHaveBeenCalledWith( action );
		expect( container.querySelector( 'main' ) ).toBeEmptyDOMElement();
		expect( document.activeElement ).toBe(
			container.querySelector( 'main' )
		);
	} );
} );
