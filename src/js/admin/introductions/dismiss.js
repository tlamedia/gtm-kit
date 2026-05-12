/**
 * Send a seen-state POST for the given introduction id.
 *
 * Resolves to true on a 2xx response, false otherwise. Network failures
 * resolve to false rather than rejecting so callers can always treat the
 * dismissal as a fire-and-forget operation.
 *
 * @param {string} introId Intro id.
 * @param {{ restRoot: string, nonce: string, fetchImpl?: typeof fetch }} options
 * @return {Promise<boolean>}
 */
export async function dismissIntroduction( introId, options ) {
	if ( typeof introId !== 'string' || introId === '' ) {
		return false;
	}
	if ( ! options || typeof options.restRoot !== 'string' || options.restRoot === '' ) {
		return false;
	}

	const fetchImpl = options.fetchImpl || ( typeof fetch === 'function' ? fetch : null );
	if ( ! fetchImpl ) {
		return false;
	}

	const trimmed = options.restRoot.replace( /\/$/, '' );
	const url     = `${ trimmed }/introductions/${ encodeURIComponent( introId ) }/seen`;

	try {
		const response = await fetchImpl( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': options.nonce || '',
			},
		} );
		return response.ok === true;
	} catch ( err ) {
		return false;
	}
}
