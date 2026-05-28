/**
 * Frontend module for GA4 engagement events.
 *
 * Two responsibilities:
 *   1. Drain the `gtmkit_engagement_event` cookie (the cache-safe
 *      handoff for server-triggered `login` / `sign_up` events) and
 *      push the decoded payload through the shared client push seam,
 *      then delete the cookie so the next page does not re-fire.
 *   2. Push a `search` event when the rendered body carries the
 *      `gtmkit-search-results` class and a non-empty
 *      `data-gtmkit-search-term` attribute.
 *
 * Both surfaces route through `window.gtmkit.events.push()` so the
 * Premium event-deferral queue can buffer them when consent is denied.
 * No public API surface; the module only mutates `window.dataLayer`
 * (or the configured equivalent) via the shared push helper.
 *
 * Configuration:
 *   - `window.gtmkitEngagementEvents.cookieName` overrides the cookie
 *     name to match the PHP-side `gtmkit_engagement_event_cookie_name`
 *     filter. Defaults to `gtmkit_engagement_event`.
 *   - `window.gtmkitEngagementEvents.cookiePath` overrides the cookie
 *     path used when deleting (must match the path the server wrote
 *     with, otherwise the deletion no-ops). Defaults to `/`.
 *   - `window.gtmkit_settings.datalayer_name` selects the dataLayer
 *     variable to push to. Falls back to `dataLayer`.
 *
 * Bundle budget: under 2 KB minified (enforced by
 * `bin/check-engagement-events-size.js`). Keep additions tight.
 *
 * @param {Window}   window   Global window reference.
 * @param {Document} document Global document reference.
 */
( function ( window, document ) {
	const config = window.gtmkitEngagementEvents || {};
	const cookieName =
		typeof config.cookieName === 'string' && config.cookieName
			? config.cookieName
			: 'gtmkit_engagement_event';
	const cookiePath =
		typeof config.cookiePath === 'string' && config.cookiePath
			? config.cookiePath
			: '/';

	function datalayerName() {
		const settings = window.gtmkit_settings;
		if (
			settings &&
			typeof settings.datalayer_name === 'string' &&
			settings.datalayer_name
		) {
			return settings.datalayer_name;
		}
		return 'dataLayer';
	}

	function pushEvent( payload ) {
		const events = window.gtmkit && window.gtmkit.events;
		if ( ! events || typeof events.push !== 'function' ) {
			// Container disabled or runtime not registered for this URL.
			// Fall back to a direct dataLayer push so the event is not
			// lost (existing dataLayer consumers will still pick it up).
			const name = datalayerName();
			window[ name ] = window[ name ] || [];
			window[ name ].push( payload );
			return;
		}
		events.push( payload, datalayerName() );
	}

	function readCookie( name ) {
		const prefix = name + '=';
		const parts = document.cookie ? document.cookie.split( ';' ) : [];
		for ( let i = 0; i < parts.length; i++ ) {
			const part = parts[ i ].replace( /^\s+/, '' );
			if ( part.indexOf( prefix ) === 0 ) {
				return part.substring( prefix.length );
			}
		}
		return null;
	}

	function deleteCookie( name, path ) {
		let attrs = '=; Max-Age=0; Path=' + path + '; SameSite=Lax';
		if ( window.location.protocol === 'https:' ) {
			attrs += '; Secure';
		}
		document.cookie = name + attrs;
	}

	function drainCookie() {
		const raw = readCookie( cookieName );
		if ( raw === null ) {
			return;
		}

		// Always delete first so a malformed cookie cannot replay on
		// the next page load.
		deleteCookie( cookieName, cookiePath );

		let decoded;
		try {
			decoded = JSON.parse( decodeURIComponent( raw ) );
		} catch ( e ) {
			return;
		}

		if (
			! decoded ||
			typeof decoded !== 'object' ||
			typeof decoded.event !== 'string'
		) {
			return;
		}

		pushEvent( decoded );
	}

	function pushSearch() {
		if ( ! document.body || ! document.body.classList ) {
			return;
		}
		if ( ! document.body.classList.contains( 'gtmkit-search-results' ) ) {
			return;
		}
		let term = document.body.dataset
			? document.body.dataset.gtmkitSearchTerm
			: document.body.getAttribute( 'data-gtmkit-search-term' );
		if ( typeof term !== 'string' ) {
			return;
		}
		term = term.replace( /^\s+|\s+$/g, '' );
		if ( term === '' ) {
			return;
		}
		pushEvent( {
			event: 'search',
			search_term: term,
		} );
	}

	function run() {
		drainCookie();
		pushSearch();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )( window, document );
