import { execFileSync } from 'node:child_process';
import { request } from '@playwright/test';
import type { FullConfig } from '@playwright/test';

const BASE_URL = process.env.WP_BASE_URL ?? 'http://localhost:8891';

function wpCli( ...args: string[] ): string {
	return execFileSync(
		'npx',
		[ 'wp-env', 'run', 'tests-cli', 'wp', ...args ],
		{ stdio: [ 'ignore', 'pipe', 'pipe' ], encoding: 'utf8' }
	).trim();
}

async function assertReachable(): Promise< void > {
	const ctx = await request.newContext();
	try {
		const response = await ctx.get( BASE_URL );
		if ( ! response.ok() && response.status() !== 302 ) {
			throw new Error(
				`wp-env not reachable at ${ BASE_URL } (HTTP ${ response.status() }). ` +
					'Run `npm run test:e2e:blocks:env:start` first.'
			);
		}
	} catch ( err ) {
		throw new Error(
			`wp-env not reachable at ${ BASE_URL }: ${
				err instanceof Error ? err.message : String( err )
			}. Run \`npm run test:e2e:blocks:env:start\` first.`
		);
	} finally {
		await ctx.dispose();
	}
}

function assertPluginsActive(): void {
	const list = wpCli( 'plugin', 'list', '--status=active', '--field=name' );
	const active = list.split( /\s+/ ).filter( Boolean );

	// GTM Kit's plugin slug equals the directory wp-env mounted it from,
	// which is the repo root's basename and not always "gtm-kit" (a CI
	// checkout is named after the repository). Detect the plugin by its
	// runtime constant instead of a hard-coded slug.
	const gtmKitActive =
		wpCli( 'eval', 'echo defined( "GTMKIT_VERSION" ) ? 1 : 0;' ) === '1';

	const missing = gtmKitActive ? [] : [ 'gtm-kit' ];
	if ( ! active.includes( 'woocommerce' ) ) {
		missing.push( 'woocommerce' );
	}
	if ( missing.length ) {
		throw new Error(
			`Required plugins not active in tests env: ${ missing.join( ', ' ) }. ` +
				`Active plugins: ${ active.join( ', ' ) || '(none)' }.`
		);
	}

	// eslint-disable-next-line no-console
	console.log( `[globalSetup] active plugins: ${ active.join( ', ' ) }` );
}

function assertBlockPagesSeeded(): void {
	// The seeder creates block-built pages with known slugs. Verify the
	// Product Collection page exists; if not, the seeder did not run.
	let found = '';
	try {
		found = wpCli(
			'post', 'list',
			'--post_type=page',
			'--name=block-shop',
			'--field=ID',
			'--format=ids'
		);
	} catch {
		throw new Error(
			'block-shop page missing. Run `npm run test:e2e:blocks:env:seed`.'
		);
	}

	if ( ! found ) {
		throw new Error(
			'block-shop page missing. Run `npm run test:e2e:blocks:env:seed`.'
		);
	}

	// eslint-disable-next-line no-console
	console.log( '[globalSetup] block storefront pages present.' );
}

export default async function globalSetup( _config: FullConfig ): Promise< void > {
	await assertReachable();
	assertPluginsActive();
	assertBlockPagesSeeded();
}
