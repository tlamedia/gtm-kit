/**
 * The wording the Stape loader readout shows after a refresh or paste.
 */

/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';

/*Internal*/
import { NetworkError } from '../../utils/errors';

/**
 * Work out why a refresh or paste request threw before the server could
 * report an outcome.
 *
 * @param {Error} error The error the request threw.
 * @return {string} The failure reason: `connection`, `session` or `unexpected`.
 */
export const getSgtmLoaderErrorReason = ( error ) => {
	const response = error?.response || {};

	if ( error instanceof NetworkError ) {
		return 'connection';
	}

	// An expired login makes the nonce check fail, and the nonce that
	// api-fetch fetches again is refused too.
	if (
		response.code === 'rest_cookie_invalid_nonce' ||
		[ 401, 403 ].includes( response.data?.status )
	) {
		return 'session';
	}

	return 'unexpected';
};

/**
 * Explain why GTM Kit could not get the loader from Stape.
 *
 * @param {string} reason The failure reason reported by the server, or the one
 *                        derived from a thrown request error.
 * @return {string} The explanation.
 */
export const describeSgtmLoaderFailure = ( reason ) => {
	switch ( reason ) {
		case 'network':
			return __( 'GTM Kit could not reach Stape.', 'gtm-kit' );
		case 'connection':
			return __(
				'Your browser could not reach this site. Check your connection.',
				'gtm-kit'
			);
		case 'session':
			return __(
				'Your WordPress session has expired. Reload the page and log in if asked.',
				'gtm-kit'
			);
		case 'unexpected':
			return __(
				'The request failed inside WordPress, not at Stape. If reloading the page does not help, check the PHP error log.',
				'gtm-kit'
			);
		case 'http_404':
			return __(
				'Stape does not know a container with this container identifier.',
				'gtm-kit'
			);
		case 'http_400':
			return __(
				'Stape did not accept the container ID or the sGTM container domain.',
				'gtm-kit'
			);
		case 'invalid_json':
		case 'no_loader':
			return __( 'Stape answered without a loader.', 'gtm-kit' );
		case 'unparseable':
			return __(
				'The loader could not be read safely, so GTM Kit did not use it.',
				'gtm-kit'
			);
		case 'datalayer_mismatch':
			return __(
				'This code was made for a different data layer name than the one GTM Kit uses. Change the data layer name in Stape to match, then copy and paste the new code.',
				'gtm-kit'
			);
		default: {
			const status = /^http_(\d+)$/.exec( reason || '' );

			return status
				? sprintf(
						// translators: %s is an HTTP status code, for example 500.
						__(
							'Stape answered with an error (HTTP %s).',
							'gtm-kit'
						),
						status[ 1 ]
				  )
				: __(
						'GTM Kit could not get the loader from Stape.',
						'gtm-kit'
				  );
		}
	}
};

/**
 * Say which loader the pages keep after a failed refresh or paste.
 *
 * A failure never removes a stored loader, so the pages keep whichever one
 * they used before.
 *
 * @param {string} source The loader in use: `api`, `pasted` or `standard`.
 * @return {string} The sentence.
 */
export const describeSgtmLoaderFallback = ( source ) => {
	if ( source === 'api' ) {
		return __(
			'Your pages keep the loader Stape issued earlier, which still works. Try again, or paste the code Stape shows for your container.',
			'gtm-kit'
		);
	}

	if ( source === 'pasted' ) {
		return __(
			'Your pages keep the loader you pasted earlier, which still works. Try again, or paste the code Stape shows for your container.',
			'gtm-kit'
		);
	}

	return __(
		'Your pages keep the standard loader, which still works. Try again, or paste the code Stape shows for your container.',
		'gtm-kit'
	);
};
