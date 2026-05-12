import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for the gtm-kit plugin.
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
		include: [ 'tests/js/**/*.test.{js,jsx}' ],
		setupFiles: [ './tests/js/setup.js' ],
		coverage: {
			provider: 'v8',
			reporter: [ 'text', 'html', 'clover' ],
			reportsDirectory: './tests/_reports/js-coverage',
			include: [ 'src/js/**/*.js' ],
			exclude: [ 'src/js/frontend/**' ],
		},
	},
	esbuild: {
		jsx: 'automatic',
	},
	resolve: {
		alias: {
			'@js': fileURLToPath( new URL( './src/js', import.meta.url ) ),
		},
	},
} );
