/**
 * The consent bridge receiver as the head inline script defines it.
 *
 * The receiver is static JavaScript inside the PHP template that builds the
 * head script, so the tests lift it out of `src/Frontend/Frontend.php` and run
 * the real code rather than a copy that could drift from it.
 *
 * @module tests/js/consent-bridge-receiver.js
 */

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const START = 'window.gtmkit.consentBridge = window.gtmkit.consentBridge || {';
const END = '\n\t\t};';

const PHP = fs.readFileSync(
	path.resolve( __dirname, '../../src/Frontend/Frontend.php' ),
	'utf-8'
);

const start = PHP.indexOf( START );
const end = start === -1 ? -1 : PHP.indexOf( END, start );

if ( start === -1 || end === -1 ) {
	throw new Error(
		'The consent bridge receiver was not found in the head script.'
	);
}

export const RECEIVER_SOURCE = PHP.slice( start, end + END.length );

if ( RECEIVER_SOURCE.indexOf( '<?php' ) !== -1 ) {
	throw new Error(
		'The consent bridge receiver must be static JavaScript without PHP.'
	);
}

/**
 * Define the receiver in the current window, as the head script does.
 *
 * `window.gtmkit` must exist, as it does on the page before the receiver.
 */
export function loadReceiver() {
	new vm.Script( RECEIVER_SOURCE ).runInThisContext();
}
