/**
 * The command palette opens settings sections by id from PHP, so each id it
 * lists has to be a section the app routes to, or the command lands on the
 * fallback route instead.
 */
import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

import { CAPABILITIES } from '../capabilities';

const SOURCE = fs.readFileSync(
	path.resolve( __dirname, '../../../../Admin/CommandPalette.php' ),
	'utf-8'
);

/**
 * The section ids the command palette lists, read from its `$sections` map.
 *
 * @return {string[]} The ids.
 */
const commandSectionIds = () => {
	const block = SOURCE.match( /\$sections = \[([\s\S]*?)\];/ );
	return [ ...block[ 1 ].matchAll( /'([a-z-]+)'\s*=>/g ) ].map(
		( match ) => match[ 1 ]
	);
};

describe( 'command palette sections', () => {
	it( 'lists only sections the settings app routes to', () => {
		const ids = commandSectionIds();
		const routes = CAPABILITIES.map( ( capability ) => capability.id );

		expect( ids.length ).toBeGreaterThan( 0 );
		ids.forEach( ( id ) => expect( routes ).toContain( id ) );
	} );
} );
