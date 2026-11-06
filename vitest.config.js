import { fileURLToPath } from 'node:url';
import { transformWithOxc } from 'vite';
import { defineConfig } from 'vitest/config';

/**
 * Compile JSX in plain `.js` files.
 *
 * The settings app writes its components as JSX in `.js` files, which Vite
 * only parses as plain JavaScript.
 */
const jsxInJs = {
	name: 'gtmkit:jsx-in-js',
	enforce: 'pre',
	transform( code, id ) {
		if ( ! id.endsWith( '.js' ) || id.includes( '/node_modules/' ) ) {
			return null;
		}
		return transformWithOxc( code, id, {
			lang: 'jsx',
			jsx: { runtime: 'automatic' },
		} );
	},
};

/**
 * Vitest configuration for the gtm-kit plugin.
 *
 * Two projects share this config. `scripts` covers the frontend and
 * introductions code under `tests/js/`; `settings` covers the React settings
 * app, whose tests sit next to the code in `src/js/admin/`.
 * `npm test` runs both; `npm run test:settings` runs the settings app alone.
 *
 * JSDOM is the default environment because most helpers in `src/js/`
 * touch `window` / `document`. Individual test files can override with
 * a `// @vitest-environment node` pragma when they need faster,
 * DOM-less isolation.
 *
 * Coverage is opt-in (run via `npm run test:coverage`). The v8 provider
 * matches the CI coverage flow in `.github/workflows/test.yml`.
 */
export default defineConfig( {
	test: {
		environment: 'jsdom',
		projects: [
			{
				extends: true,
				test: {
					name: 'scripts',
					include: [ 'tests/js/**/*.test.{js,jsx}' ],
					setupFiles: [ './tests/js/setup.js' ],
				},
			},
			{
				extends: true,
				test: {
					name: 'settings',
					include: [ 'src/js/admin/**/*.test.js' ],
					setupFiles: [ './src/js/admin/setupTests.js' ],
					// Let Vite load these packages instead of Node. Under
					// React 19, @wordpress/element and react-transition-group
					// import `findDOMNode`, which react-dom no longer exports,
					// and Node rejects a missing named import outright.
					server: {
						deps: {
							inline: [
								/@wordpress\//,
								/react-transition-group/,
							],
						},
					},
				},
			},
		],
		coverage: {
			provider: 'v8',
			reporter: [ 'text', 'html', 'clover' ],
			reportsDirectory: './tests/_reports/js-coverage',
			include: [ 'src/js/**/*.js' ],
			// The block subscriber modules are covered by the
			// tests/js/woocommerce-blocks suite. The bundle entry and the
			// boot module pull in the externalized `@wordpress/data` global
			// that only exists in a real WordPress runtime, so they are
			// excluded from the unit-coverage surface.
			exclude: [
				'src/js/frontend/woocommerce-blocks.js',
				'src/js/frontend/woocommerce-blocks/index.js',
			],
		},
	},
	plugins: [ jsxInJs ],
	oxc: {
		jsx: { runtime: 'automatic' },
	},
	resolve: {
		alias: {
			'@js': fileURLToPath( new URL( './src/js', import.meta.url ) ),
		},
	},
} );
