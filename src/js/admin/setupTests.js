/**
 * Jest setup file
 *
 * Runs before each test file to set up the testing environment.
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

require( '@testing-library/jest-dom' );

/**
 * Provide TextEncoder and TextDecoder.
 *
 * jsdom leaves these globals undefined even though Node and every real browser
 * expose them, and react-router reads TextEncoder at module scope, so importing
 * anything from react-router-dom throws before a test can run.
 */
const { TextEncoder, TextDecoder } = require( 'node:util' );

if ( ! global.TextEncoder ) {
	global.TextEncoder = TextEncoder;
}
if ( ! global.TextDecoder ) {
	global.TextDecoder = TextDecoder;
}

/**
 * Mock window.gtmkitSettings
 *
 * This global object is normally provided by PHP via wp_localize_script.
 * We mock it here so tests can run without WordPress.
 */
global.window = global.window || {};
global.window.gtmkitSettings = {
	settings: {
		general: {},
		integrations: {},
		premium: {},
	},
	site_data: {
		site_url: 'http://localhost',
		admin_url: 'http://localhost/wp-admin',
	},
	notifications: {
		metrics: {
			total: 0,
			problem: 0,
		},
	},
	isPremium: false,
	hasValidLicense: false,
	plugins: {},
	user_roles: [],
	taxonomyOptions: [],
	templates: {},
	nonce: 'test-nonce',
	root: 'http://localhost/wp-json/',
	rootId: 'gtmkit-settings',
	adminPageUrl: 'http://localhost/wp-admin/admin.php?page=gtmkit_general',
	currentPage: 'general',
};

/**
 * Suppress console warnings in tests
 *
 * Uncomment to reduce noise in test output.
 */
// global.console.warn = jest.fn();
// global.console.error = jest.fn();
