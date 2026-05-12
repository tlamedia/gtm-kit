// @vitest-environment jsdom
/**
 * Tests for the generic-blocks renderer used by remote introductions.
 *
 * Verifies that each v1 block type renders, that unknown block types
 * are dropped silently, and that the `dismiss` CTA variant routes
 * through the same handler the modal's close button uses.
 *
 * Target: src/js/admin/introductions/GenericBlocksRenderer.jsx
 */

import { describe, expect, it, vi } from 'vitest';
import { renderToString } from 'react-dom/server';
import { createRoot } from 'react-dom/client';
import { act } from 'react';

import GenericBlocksRenderer from '../../../src/js/admin/introductions/GenericBlocksRenderer.jsx';

const noop = () => {};

describe( 'GenericBlocksRenderer', () => {
	it( 'renders each block type from a mixed list', () => {
		const html = renderToString(
			<GenericBlocksRenderer
				blocks={ [
					{ type: 'heading', text: 'Title' },
					{ type: 'body', paragraphs: [ 'Para one', 'Para two' ] },
					{
						type: 'image',
						url: 'https://example.test/x.png',
						alt: 'X',
						width: 100,
						height: 50,
					},
					{ type: 'video', provider: 'youtube', id: 'abc123' },
					{
						type: 'cta',
						label: 'Learn more',
						url: 'https://example.test/',
						variant: 'primary',
					},
					{ type: 'cta', label: 'Maybe later', variant: 'dismiss' },
				] }
				onDismiss={ noop }
			/>
		);

		expect( html ).toContain( '<h2' );
		expect( html ).toContain( 'Title' );
		expect( html ).toContain( 'Para one' );
		expect( html ).toContain( 'Para two' );
		expect( html ).toContain( 'https://example.test/x.png' );
		expect( html ).toContain( 'alt="X"' );
		expect( html ).toContain( 'youtube-nocookie.com/embed/abc123' );
		expect( html ).toContain( 'Learn more' );
		expect( html ).toContain( 'Maybe later' );
	} );

	it( 'drops unknown block types silently', () => {
		const html = renderToString(
			<GenericBlocksRenderer
				blocks={ [
					{ type: 'heading', text: 'Visible' },
					{ type: 'tweet-embed', text: 'Should not render' },
					{ type: 'body', paragraphs: [ 'Also visible' ] },
				] }
				onDismiss={ noop }
			/>
		);

		expect( html ).toContain( 'Visible' );
		expect( html ).toContain( 'Also visible' );
		expect( html ).not.toContain( 'Should not render' );
		expect( html ).not.toContain( 'tweet-embed' );
	} );

	it( 'invokes onDismiss when a dismiss-variant CTA is clicked', () => {
		const container = document.createElement( 'div' );
		document.body.appendChild( container );
		const root     = createRoot( container );
		const onDismiss = vi.fn();

		act( () => {
			root.render(
				<GenericBlocksRenderer
					blocks={ [
						{ type: 'cta', label: 'Maybe later', variant: 'dismiss' },
					] }
					onDismiss={ onDismiss }
				/>
			);
		} );

		const button = container.querySelector( 'button' );
		expect( button ).not.toBeNull();
		act( () => {
			button.click();
		} );

		expect( onDismiss ).toHaveBeenCalledTimes( 1 );

		act( () => {
			root.unmount();
		} );
		container.remove();
	} );

	it( 'drops invalid block payloads silently', () => {
		const html = renderToString(
			<GenericBlocksRenderer
				blocks={ [
					{ type: 'heading' }, // missing text
					{ type: 'image', url: '', alt: 'X' }, // missing url
					{ type: 'cta', label: '', variant: 'primary' }, // missing label
					{ type: 'video', provider: 'vimeo', id: 'abc' }, // unsupported provider
					{ type: 'heading', text: 'Survives' },
				] }
				onDismiss={ noop }
			/>
		);

		expect( html ).toContain( 'Survives' );
		expect( html ).not.toContain( '<img' );
		expect( html ).not.toContain( '<iframe' );
	} );

	it( 'returns null for a non-array blocks input', () => {
		expect(
			renderToString(
				<GenericBlocksRenderer blocks={ null } onDismiss={ noop } />
			)
		).toBe( '' );
	} );
} );
