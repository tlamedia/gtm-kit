/**
 * Jest configuration for the React settings app (src/js/admin).
 *
 * Scoped to src/js/admin so it never picks up the plugin's Vitest suite, which
 * lives elsewhere and runs via `npm test`. Run with `npm run test:settings`.
 */

const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	rootDir: '.',
	roots: [ '<rootDir>/src/js/admin' ],
	setupFilesAfterEnv: [ '<rootDir>/src/js/admin/setupTests.js' ],
	testMatch: [
		'**/__tests__/**/*.[jt]s?(x)',
		'**/?(*.)+(spec|test).[jt]s?(x)',
	],
	collectCoverageFrom: [
		'src/js/admin/**/*.{js,jsx}',
		'!src/js/admin/**/*.d.ts',
		'!src/js/admin/settings.js',
		'!src/js/admin/wizard.js',
		'!src/js/admin/app/routes-*.js',
		'!src/js/admin/setupTests.js',
	],
	coverageThreshold: {
		global: {
			branches: 50,
			functions: 50,
			lines: 50,
			statements: 50,
		},
	},
	coveragePathIgnorePatterns: [
		'/node_modules/',
		'/__tests__/',
		'\\.d\\.ts$',
	],
};
