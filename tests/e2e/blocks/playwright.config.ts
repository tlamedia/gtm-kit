import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8891';

export default defineConfig( {
	testDir: './specs',
	outputDir: './test-results',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	// One retry absorbs the inherent async timing of the block cart (legacy
	// AJAX add + drawer animation + Store API cart fetch). A genuine failure
	// fails both attempts.
	retries: 1,
	workers: 1,
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: './playwright-report', open: 'never' } ],
	],
	globalSetup: require.resolve( './globalSetup' ),
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 15_000,
		navigationTimeout: 30_000,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
