#!/usr/bin/env node
/**
 * Bundle-size guard for the engagement-events frontend module.
 *
 * The module ships on every public page, so its size matters for
 * first-paint. The budget is 2048 bytes minified; this script
 * enforces that hard limit and exits non-zero on regression so CI
 * fails before the bundle drifts.
 */

'use strict';

const fs = require( 'node:fs' );
const path = require( 'node:path' );

const ASSET_PATH = path.resolve(
	__dirname,
	'..',
	'assets',
	'frontend',
	'engagement-events.js'
);
const LIMIT_BYTES = 2048;

if ( ! fs.existsSync( ASSET_PATH ) ) {
	console.error(
		`engagement-events.js not found at ${ ASSET_PATH }. Run \`npm run uglify:engagement-events\` first.`
	);
	process.exit( 1 );
}

const stat = fs.statSync( ASSET_PATH );
const bytes = stat.size;

if ( bytes > LIMIT_BYTES ) {
	console.error(
		`engagement-events.js is ${ bytes } bytes, exceeds the ${ LIMIT_BYTES }-byte budget.`
	);
	process.exit( 1 );
}

console.log( `engagement-events.js OK: ${ bytes } / ${ LIMIT_BYTES } bytes.` );
