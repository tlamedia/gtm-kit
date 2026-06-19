/**
 * Jest setup file
 *
 * Runs before each test file to set up the testing environment.
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

require( '@testing-library/jest-dom' );

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
