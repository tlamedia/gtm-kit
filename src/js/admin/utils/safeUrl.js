/**
 * URL sanitization for links and navigation targets that originate outside
 * the bundle: the remote content feed (upsells, upgrades, templates,
 * tutorials) and notification HTML. Rendering such a value into an href, or
 * assigning it to window.location, would execute script if it carried a
 * javascript: URL, so every one of those sinks must pass through safeHref()
 * first.
 */

const ALLOWED_PROTOCOLS = [ 'http:', 'https:', 'mailto:' ];

/**
 * Returns the given URL if it resolves to an allowed protocol (http, https,
 * mailto, or a relative path on the current origin), or an empty string
 * otherwise. Callers should treat an empty result as "no link".
 *
 * @param {string} url The untrusted URL.
 * @return {string} The URL unchanged, or an empty string if it is not safe.
 */
export const safeHref = ( url ) => {
	if ( typeof url !== 'string' ) {
		return '';
	}

	const trimmed = url.trim();
	if ( trimmed === '' ) {
		return '';
	}

	let parsed;
	try {
		parsed = new URL( trimmed, window.location.origin );
	} catch ( error ) {
		return '';
	}

	return ALLOWED_PROTOCOLS.includes( parsed.protocol ) ? trimmed : '';
};
