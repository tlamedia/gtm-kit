/*WordPress*/
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';

/*Internal*/
import { probeHealth } from '../api/settings';

/**
 * How long the probe may go unanswered before the user is told it is slow.
 */
export const PROBE_SLOW_MS = 10000;

/**
 * How long the probe may go unanswered before it is given up on.
 */
export const PROBE_ABORT_MS = 30000;

/**
 * The longest server message kept for the support details.
 */
const MESSAGE_LIMIT = 200;

/**
 * Probe states.
 *
 * `pending` and `slow` are still waiting; `ok`, `unreachable` and `error`
 * are final.
 */
export const PROBE_STATE = {
	PENDING: 'pending',
	SLOW: 'slow',
	OK: 'ok',
	UNREACHABLE: 'unreachable',
	ERROR: 'error',
};

const truncate = ( text ) =>
	typeof text === 'string' ? text.slice( 0, MESSAGE_LIMIT ) : '';

/**
 * Rethrow an abort so a body that stops arriving counts as a timeout rather
 * than as an unreadable answer.
 *
 * @param {*} error What reading the body threw.
 */
const rethrowAbort = ( error ) => {
	if ( error?.name === 'AbortError' ) {
		throw error;
	}
};

/**
 * Read an error code and message out of a response body, whether the site
 * answered with a REST error object or an HTML page from a firewall.
 *
 * @param {Response} response The response.
 * @return {Promise<{code: string, message: string}>} The code and message.
 */
const readErrorBody = async ( response ) => {
	let text = '';
	try {
		text = await response.text();
	} catch ( error ) {
		rethrowAbort( error );
		return { code: '', message: '' };
	}

	try {
		const body = JSON.parse( text );
		return {
			code: typeof body?.code === 'string' ? body.code : '',
			message: truncate( body?.message ),
		};
	} catch {
		return { code: '', message: truncate( text.trim() ) };
	}
};

/**
 * Turn the site's answer into a probe result.
 *
 * @param {Response} response The response, 2xx or not.
 * @return {Promise<Object>} The result.
 */
const classifyResponse = async ( response ) => {
	if ( response.ok ) {
		let body = null;
		try {
			body = await response.clone().json();
		} catch ( error ) {
			rethrowAbort( error );
		}

		if ( body?.reachable === true ) {
			return { state: PROBE_STATE.OK, status: response.status };
		}
	}

	const { code, message } = await readErrorBody( response );
	return {
		state: PROBE_STATE.ERROR,
		status: response.status,
		code: code || ( response.ok ? 'unexpected_response' : '' ),
		message,
	};
};

/**
 * Check, once on mount, that this browser can reach the site's settings API.
 *
 * The settings screen renders from data printed into the page, so it looks
 * normal even when every request it makes later is blocked by an extension,
 * a VPN or a filter in front of the site. The probe makes one request to the
 * same route, nonce and permission check a save uses and reports the outcome
 * as component state. It sends nothing anywhere else and stores nothing.
 *
 * @param {Object}   [options]         Options.
 * @param {number}   [options.slowMs]  Wait before reporting `slow`.
 * @param {number}   [options.abortMs] Wait before giving up as `unreachable`.
 * @param {Function} [options.request] Sends the probe; receives an AbortSignal.
 * @return {{state: string, details: Object, retry: Function}} The probe.
 */
export const useReachabilityProbe = ( {
	slowMs = PROBE_SLOW_MS,
	abortMs = PROBE_ABORT_MS,
	request = probeHealth,
} = {} ) => {
	const [ result, setResult ] = useState( {
		state: PROBE_STATE.PENDING,
	} );
	const runRef = useRef( null );

	const run = useCallback( () => {
		runRef.current?.cancel();

		const controller = new window.AbortController();
		const startedAt = Date.now();
		let timedOut = false;
		let current = true;

		const slowTimer = setTimeout( () => {
			setResult( { state: PROBE_STATE.SLOW } );
		}, slowMs );
		const abortTimer = setTimeout( () => {
			timedOut = true;
			controller.abort();
		}, abortMs );

		const finish = ( outcome ) => {
			clearTimeout( slowTimer );
			clearTimeout( abortTimer );
			if ( current ) {
				setResult( {
					...outcome,
					elapsedMs: Date.now() - startedAt,
					checkedAt: new Date().toISOString(),
				} );
			}
		};

		runRef.current = {
			cancel: () => {
				current = false;
				clearTimeout( slowTimer );
				clearTimeout( abortTimer );
				controller.abort();
			},
		};

		setResult( { state: PROBE_STATE.PENDING } );

		Promise.resolve()
			.then( () => request( controller.signal ) )
			.then( classifyResponse, ( error ) =>
				// api-fetch throws a non-2xx response as is when it is not
				// asked to parse it.
				typeof error?.status === 'number' && error?.text
					? classifyResponse( error )
					: Promise.reject( error )
			)
			// Reading a body can fail too, after the headers have arrived.
			.catch( ( error ) => {
				if ( error?.name === 'AbortError' ) {
					return timedOut
						? {
								state: PROBE_STATE.UNREACHABLE,
								code: 'timeout',
								message: '',
						  }
						: null;
				}

				return {
					state: PROBE_STATE.UNREACHABLE,
					code: typeof error?.code === 'string' ? error.code : '',
					message: truncate( error?.message ),
				};
			} )
			.then( ( outcome ) => {
				if ( outcome ) {
					finish( outcome );
				}
			} );
	}, [ slowMs, abortMs, request ] );

	useEffect( () => {
		run();
		return () => runRef.current?.cancel();
	}, [ run ] );

	const { state, ...details } = result;

	return { state, details, retry: run };
};
