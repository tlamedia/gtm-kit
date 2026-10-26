/**
 * Covers the settings API reachability probe and its banner: the quiet path,
 * the slow and timed-out paths, an outright network failure, an error answer,
 * "Check again", and the support details block.
 */

import { render, screen, act, fireEvent } from '@testing-library/react';

import ReachabilityBanner from '../ReachabilityBanner';
import {
	PROBE_SLOW_MS,
	PROBE_ABORT_MS,
} from '../../../hooks/useReachabilityProbe';

jest.mock( '../../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getNonce: () => 'secret-nonce-value',
		getRestRoot: () => 'https://example.test/wp-json/',
		getVersion: () => '9.9.9',
	},
} ) );

const fakeResponse = ( status, body ) => {
	const text = typeof body === 'string' ? body : JSON.stringify( body );
	const response = {
		ok: status >= 200 && status < 300,
		status,
		text: async () => text,
		json: async () => JSON.parse( text ),
	};
	response.clone = () => response;
	return response;
};

const abortError = () => {
	const error = new Error( 'The operation was aborted.' );
	error.name = 'AbortError';
	return error;
};

// A request that settles only when the test says so, or rejects on abort the
// way fetch does.
const deferredRequest = () => {
	const calls = [];
	const request = jest.fn(
		( signal ) =>
			new Promise( ( resolve, reject ) => {
				calls.push( { resolve, reject } );
				signal.addEventListener( 'abort', () =>
					reject( abortError() )
				);
			} )
	);
	return { request, calls };
};

const flush = () => act( async () => {} );

const advance = ( ms ) =>
	act( async () => {
		jest.advanceTimersByTime( ms );
	} );

const renderBanner = ( request ) =>
	render( <ReachabilityBanner probeOptions={ { request } } /> );

beforeEach( () => {
	jest.useFakeTimers();
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'ReachabilityBanner', () => {
	it( 'uses the 10 and 30 second timings', () => {
		expect( PROBE_SLOW_MS ).toBe( 10000 );
		expect( PROBE_ABORT_MS ).toBe( 30000 );
	} );

	it( 'renders nothing while waiting under 10 seconds or once the site answers', async () => {
		const { request, calls } = deferredRequest();
		const { container } = renderBanner( request );
		await flush();

		await advance( PROBE_SLOW_MS - 1 );
		expect( container ).toBeEmptyDOMElement();

		await act( async () =>
			calls[ 0 ].resolve( fakeResponse( 200, { reachable: true } ) )
		);
		await advance( PROBE_ABORT_MS );
		expect( container ).toBeEmptyDOMElement();
		expect( request ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows the slow notice at 10 seconds and clears it when the answer arrives', async () => {
		const { request, calls } = deferredRequest();
		const { container } = renderBanner( request );
		await flush();

		await advance( PROBE_SLOW_MS );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Your site has not answered yet.'
		);
		expect(
			screen.queryByText( 'Copy details for support' )
		).not.toBeInTheDocument();

		await advance( 5000 );
		await act( async () =>
			calls[ 0 ].resolve( fakeResponse( 200, { reachable: true } ) )
		);
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'gives up at 30 seconds and reports the site as unreachable', async () => {
		const { request } = deferredRequest();
		renderBanner( request );
		await flush();

		await advance( PROBE_SLOW_MS );
		expect( screen.getByRole( 'status' ) ).toBeInTheDocument();

		await advance( PROBE_ABORT_MS - PROBE_SLOW_MS );
		const banner = screen.getByRole( 'alert' );
		expect( banner ).toHaveTextContent(
			"Your browser could not reach your site's settings API"
		);
		expect( banner ).toHaveTextContent(
			'usually caused by a browser extension'
		);
		expect( banner ).toHaveAttribute( 'data-probe-state', 'unreachable' );
		expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument();
	} );

	it( 'reports an outright network failure as unreachable at once', async () => {
		const request = jest.fn().mockRejectedValue( {
			code: 'fetch_error',
			message: 'Could not get a valid response from the server.',
		} );
		renderBanner( request );
		await flush();

		expect( screen.getByRole( 'alert' ) ).toHaveAttribute(
			'data-probe-state',
			'unreachable'
		);
	} );

	it( 'reports an error answer with its HTTP status', async () => {
		const request = jest
			.fn()
			.mockRejectedValue(
				fakeResponse(
					403,
					'<html><body>Access denied by firewall</body></html>'
				)
			);
		renderBanner( request );
		await flush();

		const banner = screen.getByRole( 'alert' );
		expect( banner ).toHaveAttribute( 'data-probe-state', 'error' );
		expect( banner ).toHaveTextContent( '(HTTP 403)' );
		expect( banner ).toHaveTextContent(
			'often answer blocked requests with HTTP 403'
		);
	} );

	it.each( [ 401, 403 ] )(
		'reports a capability refusal (HTTP %d) as a permission problem, not a blocked browser',
		async ( status ) => {
			const request = jest.fn().mockRejectedValue(
				fakeResponse( status, {
					code: 'rest_forbidden',
					message: 'Only authenticated users can access endpoint.',
					data: { status },
				} )
			);
			renderBanner( request );
			await flush();

			const banner = screen.getByRole( 'alert' );
			expect( banner ).toHaveAttribute( 'data-probe-state', 'error' );
			expect( banner ).toHaveTextContent(
				'refused the settings API check for your user account'
			);
			expect( banner ).not.toHaveTextContent( 'firewalls' );
		}
	);

	it( 'keeps the firewall wording for a 403 with another error code', async () => {
		const request = jest.fn().mockRejectedValue(
			fakeResponse( 403, {
				code: 'waf_request_blocked',
				message: 'Request blocked',
			} )
		);
		renderBanner( request );
		await flush();

		const banner = screen.getByRole( 'alert' );
		expect( banner ).toHaveTextContent( '(HTTP 403)' );
		expect( banner ).toHaveTextContent( 'firewalls' );
		expect( banner ).not.toHaveTextContent(
			'refused the settings API check'
		);
	} );

	it( 'reports a nonce that is still rejected as a login problem, not a blocked browser', async () => {
		const request = jest.fn().mockRejectedValue(
			fakeResponse( 403, {
				code: 'rest_cookie_invalid_nonce',
				message: 'Cookie check failed',
				data: { status: 403 },
			} )
		);
		renderBanner( request );
		await flush();

		const banner = screen.getByRole( 'alert' );
		expect( banner ).toHaveAttribute( 'data-probe-state', 'error' );
		expect( banner ).toHaveTextContent(
			'did not accept your login for the settings API check'
		);
		expect( banner ).not.toHaveTextContent( 'firewalls' );
	} );

	it( 'reports a body that stops arriving after the headers as a timeout', async () => {
		// The headers arrive at once; the body only settles when the probe
		// gives up and aborts, the way fetch rejects a stalled body read.
		const request = jest.fn( ( signal ) => {
			const stalled = () =>
				new Promise( ( resolve, reject ) => {
					signal.addEventListener( 'abort', () =>
						reject( abortError() )
					);
				} );
			const response = {
				ok: true,
				status: 200,
				text: stalled,
				json: stalled,
			};
			response.clone = () => response;
			return Promise.resolve( response );
		} );
		renderBanner( request );
		await flush();
		await advance( PROBE_ABORT_MS );
		await flush();

		const banner = screen.getByRole( 'alert' );
		expect( banner ).toHaveAttribute( 'data-probe-state', 'unreachable' );
		expect( banner ).not.toHaveTextContent( 'HTTP 200' );

		const writeText = jest.fn().mockResolvedValue();
		Object.defineProperty( window.navigator, 'clipboard', {
			value: { writeText },
			configurable: true,
		} );
		fireEvent.click( screen.getByText( 'Copy details for support' ) );
		await flush();
		expect( writeText.mock.calls[ 0 ][ 0 ] ).toContain(
			'Error code: timeout'
		);
	} );

	it( 'treats a 2xx answer without the expected body as an error', async () => {
		const request = jest
			.fn()
			.mockResolvedValue(
				fakeResponse( 200, '<html>cached page</html>' )
			);
		renderBanner( request );
		await flush();

		expect( screen.getByRole( 'alert' ) ).toHaveAttribute(
			'data-probe-state',
			'error'
		);
	} );

	it( 'runs the probe again on Check again and follows the new result', async () => {
		const request = jest
			.fn()
			.mockRejectedValueOnce( { code: 'fetch_error', message: 'failed' } )
			.mockResolvedValueOnce( fakeResponse( 200, { reachable: true } ) );
		const { container } = renderBanner( request );
		await flush();
		expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByText( 'Check again' ) );
		await flush();

		expect( request ).toHaveBeenCalledTimes( 2 );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'copies a support block with the listed fields and no secrets', async () => {
		const writeText = jest.fn().mockResolvedValue();
		Object.defineProperty( window.navigator, 'clipboard', {
			value: { writeText },
			configurable: true,
		} );
		document.cookie = 'wordpress_logged_in_test=cookie-secret-value';

		const request = jest.fn().mockRejectedValue(
			fakeResponse( 403, {
				code: 'rest_forbidden',
				message: 'Sorry, you are not allowed to do that.',
			} )
		);
		renderBanner( request );
		await flush();

		fireEvent.click( screen.getByText( 'Copy details for support' ) );
		await flush();

		const copied = writeText.mock.calls[ 0 ][ 0 ];
		expect( copied ).toContain( 'State: error' );
		expect( copied ).toContain( 'HTTP status: 403' );
		expect( copied ).toContain( 'Error code: rest_forbidden' );
		expect( copied ).toContain(
			'Message: Sorry, you are not allowed to do that.'
		);
		expect( copied ).toMatch( /Elapsed: \d+ ms/ );
		expect( copied ).toContain(
			'REST root: https://example.test/wp-json/'
		);
		expect( copied ).toMatch( /Checked at: \d{4}-\d{2}-\d{2}T/ );
		expect( copied ).toContain( 'GTM Kit version: 9.9.9' );
		expect( copied ).toContain(
			`User agent: ${ window.navigator.userAgent }`
		);
		expect( copied ).not.toContain( 'secret-nonce-value' );
		expect( copied ).not.toContain( 'cookie-secret-value' );
		expect( screen.getByText( 'Copied' ) ).toBeInTheDocument();
	} );

	it( 'keeps only the first 200 characters of a server message', async () => {
		const writeText = jest.fn().mockResolvedValue();
		Object.defineProperty( window.navigator, 'clipboard', {
			value: { writeText },
			configurable: true,
		} );
		const request = jest
			.fn()
			.mockRejectedValue( fakeResponse( 500, 'y'.repeat( 300 ) ) );
		renderBanner( request );
		await flush();

		fireEvent.click( screen.getByText( 'Copy details for support' ) );
		await flush();

		const line = writeText.mock.calls[ 0 ][ 0 ]
			.split( '\n' )
			.find( ( l ) => l.startsWith( 'Message: ' ) );
		expect( line ).toBe( `Message: ${ 'y'.repeat( 200 ) }` );
	} );
} );
