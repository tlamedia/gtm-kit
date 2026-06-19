/**
 * GTM Container ID validation utilities
 */

import { __ } from '@wordpress/i18n';
import { ensurePrefix, toUpperCase } from './transformations';

/**
 * Validates if a GTM Container ID is in the correct format
 *
 * @param {string} id - The GTM Container ID to validate
 * @return {boolean} True if valid, false otherwise
 */
export const validateGtmId = ( id ) => {
	if ( ! id || id.trim() === '' ) {
		return false;
	}
	const normalized = normalizeGtmId( id );
	return /^GTM-[A-Z0-9]+$/i.test( normalized );
};

/**
 * Normalizes a GTM Container ID by auto-adding GTM- prefix and uppercasing
 *
 * @param {string} id - The GTM Container ID to normalize
 * @return {string} The normalized ID
 */
export const normalizeGtmId = ( id ) => {
	if ( ! id ) {
		return '';
	}
	// Use reusable transformations
	const upper = toUpperCase( id );
	return ensurePrefix( upper, 'GTM-' );
};

/**
 * Gets validation error message for a GTM Container ID
 *
 * @param {string} id - The GTM Container ID to validate
 * @return {string|null} Error message or null if valid
 */
export const getGtmIdError = ( id ) => {
	if ( ! id || id.trim() === '' ) {
		return __( 'Container ID is required', 'gtm-kit' );
	}
	if ( ! validateGtmId( id ) ) {
		return __( 'Container ID must be in format GTM-XXXXXXX', 'gtm-kit' );
	}
	return null;
};
