/**
 * Common transformation utilities
 *
 * Reusable transformation functions for normalizing and formatting values.
 * Used with TextSetting's transform prop and other components.
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 */

/**
 * Transforms a domain by removing protocol and trailing slash
 *
 * @param {string} domain Domain to transform
 * @return {string} Normalized domain
 *
 * @example
 * normalizeDomain('https://example.com/') // 'example.com'
 * normalizeDomain('http://example.com')   // 'example.com'
 * normalizeDomain('example.com/')         // 'example.com'
 */
export const normalizeDomain = ( domain ) => {
	if ( ! domain || typeof domain !== 'string' ) {
		return '';
	}

	let normalized = domain.trim();

	// Remove protocol
	normalized = normalized.replace( /^https?:\/\//i, '' );

	// Remove trailing slash
	normalized = normalized.replace( /\/+$/, '' );

	// Remove www. prefix (optional, depends on requirements)
	// normalized = normalized.replace(/^www\./i, '');

	return normalized;
};

/**
 * Transforms a URL to ensure it has a protocol
 *
 * @param {string} url      URL to transform
 * @param {string} protocol Default protocol to add (default: 'https')
 * @return {string} Normalized URL
 *
 * @example
 * ensureProtocol('example.com')         // 'https://example.com'
 * ensureProtocol('http://example.com')  // 'http://example.com'
 */
export const ensureProtocol = ( url, protocol = 'https' ) => {
	if ( ! url || typeof url !== 'string' ) {
		return '';
	}

	const trimmed = url.trim();

	if ( /^https?:\/\//i.test( trimmed ) ) {
		return trimmed;
	}

	return `${ protocol }://${ trimmed }`;
};

/**
 * Transforms text to uppercase
 *
 * @param {string} text Text to transform
 * @return {string} Uppercase text
 */
export const toUpperCase = ( text ) => {
	if ( ! text || typeof text !== 'string' ) {
		return '';
	}
	return text.trim().toUpperCase();
};

/**
 * Transforms text to lowercase
 *
 * @param {string} text Text to transform
 * @return {string} Lowercase text
 */
export const toLowerCase = ( text ) => {
	if ( ! text || typeof text !== 'string' ) {
		return '';
	}
	return text.trim().toLowerCase();
};

/**
 * Transforms text to slug format (lowercase, hyphens, no spaces)
 *
 * @param {string} text Text to transform
 * @return {string} Slugified text
 *
 * @example
 * toSlug('Hello World')  // 'hello-world'
 * toSlug('GTM Kit 2024') // 'gtm-kit-2024'
 */
export const toSlug = ( text ) => {
	if ( ! text || typeof text !== 'string' ) {
		return '';
	}

	return text
		.trim()
		.toLowerCase()
		.replace( /[^\w\s-]/g, '' ) // Remove special chars
		.replace( /\s+/g, '-' ) // Replace spaces with hyphens
		.replace( /-+/g, '-' ) // Replace multiple hyphens with single
		.replace( /^-+|-+$/g, '' ); // Remove leading/trailing hyphens
};

/**
 * Removes all whitespace from a string
 *
 * @param {string} text Text to transform
 * @return {string} Text without whitespace
 */
export const removeWhitespace = ( text ) => {
	if ( ! text || typeof text !== 'string' ) {
		return '';
	}
	return text.replace( /\s+/g, '' );
};

/**
 * Trims and normalizes whitespace
 *
 * @param {string} text Text to transform
 * @return {string} Normalized text
 */
export const trimAndNormalize = ( text ) => {
	if ( ! text || typeof text !== 'string' ) {
		return '';
	}
	return text.trim().replace( /\s+/g, ' ' );
};

/**
 * Ensures a value ends with a specific suffix
 *
 * @param {string} value  Value to transform
 * @param {string} suffix Suffix to ensure
 * @return {string} Value with suffix
 *
 * @example
 * ensureSuffix('example.com', '/')    // 'example.com/'
 * ensureSuffix('example.com/', '/')   // 'example.com/'
 */
export const ensureSuffix = ( value, suffix ) => {
	if ( ! value || typeof value !== 'string' ) {
		return suffix;
	}

	const trimmed = value.trim();
	return trimmed.endsWith( suffix ) ? trimmed : trimmed + suffix;
};

/**
 * Ensures a value starts with a specific prefix
 *
 * @param {string} value  Value to transform
 * @param {string} prefix Prefix to ensure
 * @return {string} Value with prefix
 *
 * @example
 * ensurePrefix('12345', 'GTM-')    // 'GTM-12345'
 * ensurePrefix('GTM-12345', 'GTM-') // 'GTM-12345'
 */
export const ensurePrefix = ( value, prefix ) => {
	if ( ! value || typeof value !== 'string' ) {
		return prefix;
	}

	const trimmed = value.trim();
	return trimmed.startsWith( prefix ) ? trimmed : prefix + trimmed;
};
