/**
 * Covers autofill suppression across the settings app.
 *
 * A password manager fills an input by setting its value and dispatching an
 * input event, which a controlled field accepts as typing, so a filled value
 * is saved with the next save of any setting. Every text, search, number and
 * password input therefore carries the opt-out attributes, and checkboxes and
 * radios, which managers do not fill, are left as they are.
 *
 * The rendered checks assert on the attributes in the DOM, so they fail if the
 * markup is wrong even when the constant is imported. The source check covers
 * every input in the app, including screens too involved to render here, so a
 * control added later without the attributes fails this suite.
 */

/*
 * `react` is a real dependency; `import/no-extraneous-dependencies` misfires on
 * the JSX inside the `jest.mock` stub factories below, flagging a phantom
 * `@types/react`. Disable the rule for this test file only.
 */
/* eslint-disable import/no-extraneous-dependencies */

import fs from 'fs';
import path from 'path';

import { render } from '@testing-library/react';
import { TextControl } from '@wordpress/components';

import { NO_AUTOFILL } from '../autofill';
import { getShellControl } from '../../app/shell/fields/controls';
import TopBar from '../../app/shell/TopBar';

jest.mock( '../../hooks/useSettingField', () => ( {
	useSettingField: () => [ '', () => {} ],
} ) );

jest.mock( '../../hooks/useNotification', () => ( {
	useNotification: () => ( { removeNotification: () => {} } ),
} ) );

jest.mock( '../../services/SettingsService', () => ( {
	__esModule: true,
	default: {
		getNonce: () => 'test-nonce',
		getRestRoot: () => '/wp-json/',
		getUserRoles: () => [],
		getPageOptions: () => [],
		getTaxonomyOptions: () => [],
		isPluginActive: () => false,
		getActiveTier: () => 'free',
	},
} ) );

jest.mock( '../../app/atoms/save-btn', () => ( {
	__esModule: true,
	default: () => null,
} ) );

jest.mock( '../../app/shell/FilterChips', () => ( {
	__esModule: true,
	default: () => null,
} ) );

const SUPPRESSION = {
	autocomplete: 'off',
	'data-1p-ignore': 'true',
	'data-lpignore': 'true',
	'data-bwignore': 'true',
	'data-form-type': 'other',
};

/**
 * The autofill opt-outs an element carries, as rendered.
 *
 * @param {Element} element The input.
 * @return {Object<string, string>} Each opt-out attribute present, with its value.
 */
const suppressionOn = ( element ) =>
	Object.fromEntries(
		Object.keys( SUPPRESSION )
			.filter( ( name ) => element.hasAttribute( name ) )
			.map( ( name ) => [ name, element.getAttribute( name ) ] )
	);

const renderShellControl = ( control, extra = {} ) => {
	const Control = getShellControl( control );
	return render(
		<Control
			field={ { key: 'general.example', label: 'Example', ...extra } }
			disabled={ false }
		/>
	);
};

describe( 'autofill suppression, as rendered', () => {
	it( 'is on the generic settings text field', () => {
		const { container } = renderShellControl( 'text' );
		expect(
			suppressionOn( container.querySelector( 'input[type="text"]' ) )
		).toEqual( SUPPRESSION );
	} );

	it( 'is on the generic settings number field', () => {
		const { container } = renderShellControl( 'number' );
		expect(
			suppressionOn( container.querySelector( 'input[type="number"]' ) )
		).toEqual( SUPPRESSION );
	} );

	it( 'is on the comma-separated list field', () => {
		const { container } = renderShellControl( 'region-codes' );
		expect(
			suppressionOn( container.querySelector( 'input[type="text"]' ) )
		).toEqual( SUPPRESSION );
	} );

	it( 'is on the settings search', () => {
		const { container } = render(
			<TopBar query="" onSearch={ () => {} } />
		);
		expect(
			suppressionOn( container.querySelector( 'input[type="search"]' ) )
		).toEqual( SUPPRESSION );
	} );

	it( 'reaches the input of a WordPress text control', () => {
		const { container } = render(
			<TextControl
				{ ...NO_AUTOFILL }
				label="Example"
				value=""
				onChange={ () => {} }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
		);
		expect( suppressionOn( container.querySelector( 'input' ) ) ).toEqual(
			SUPPRESSION
		);
	} );

	it( 'is not on radio buttons', () => {
		const { container } = renderShellControl( 'radio', {
			options: [
				{ value: 'a', label: 'A' },
				{ value: 'b', label: 'B' },
			],
		} );
		const radios = container.querySelectorAll( 'input[type="radio"]' );
		expect( radios ).toHaveLength( 2 );
		radios.forEach( ( radio ) => {
			expect( suppressionOn( radio ) ).toEqual( {} );
		} );
	} );
} );

const ADMIN_ROOT = path.resolve( __dirname, '../..' );

/**
 * Every shipped source file of the settings app.
 *
 * @param {string} dir Directory to walk.
 * @return {string[]} Absolute file paths.
 */
const sourceFiles = ( dir ) =>
	fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return entry.name === '__tests__' ? [] : sourceFiles( full );
		}
		return /\.jsx?$/.test( entry.name ) ? [ full ] : [];
	} );

/**
 * The opening JSX tags of an element in a source file, skipping any that sit
 * in a comment.
 *
 * Walks forward from each `<tag` to the `>` that closes it, stepping over
 * braced expressions and quoted strings so an arrow function or a string
 * containing `>` does not end the tag early.
 *
 * @param {string} source  File contents.
 * @param {string} tagName Element or component name.
 * @return {string[]} The opening tags.
 */
const openingTags = ( source, tagName ) => {
	const tags = [];
	const pattern = new RegExp( `<${ tagName }(?=[\\s/>])`, 'g' );
	let match;

	while ( ( match = pattern.exec( source ) ) !== null ) {
		const lineStart = source.lastIndexOf( '\n', match.index ) + 1;
		if ( /^\s*(\*|\/\/)/.test( source.slice( lineStart, match.index ) ) ) {
			continue;
		}

		let depth = 0;
		let quote = null;
		let end = match.index + match[ 0 ].length;

		for ( ; end < source.length; end++ ) {
			const char = source[ end ];
			if ( quote ) {
				if ( char === quote ) {
					quote = null;
				}
			} else if ( depth === 0 && ( char === '"' || char === "'" ) ) {
				quote = char;
			} else if ( char === '{' ) {
				depth++;
			} else if ( char === '}' ) {
				depth--;
			} else if ( depth === 0 && char === '>' ) {
				break;
			}
		}

		tags.push( source.slice( match.index, end + 1 ) );
	}

	return tags;
};

const SPREAD = /\{\s*\.\.\.NO_AUTOFILL\s*\}/;
const NOT_FILLED = /type="(checkbox|radio)"/;

/**
 * A short, readable pointer to a tag for a failure message.
 *
 * @param {{relative: string, tag: string}} found The file and the tag.
 * @return {string} The pointer.
 */
const describeTag = ( { relative, tag } ) =>
	`${ relative }: ${ tag.slice( 0, 80 ) }`;

describe( 'autofill suppression, across the source', () => {
	const inputs = [];
	const textControls = [];

	sourceFiles( ADMIN_ROOT ).forEach( ( file ) => {
		const source = fs.readFileSync( file, 'utf8' );
		const relative = path.relative( ADMIN_ROOT, file );
		openingTags( source, 'input' ).forEach( ( tag ) =>
			inputs.push( { relative, tag } )
		);
		openingTags( source, 'TextControl' ).forEach( ( tag ) =>
			textControls.push( { relative, tag } )
		);
	} );

	it( 'finds the inputs it is meant to guard', () => {
		expect( inputs.length ).toBeGreaterThan( 10 );
		expect( textControls.length ).toBeGreaterThan( 5 );
	} );

	it( 'puts it on every input a password manager can fill', () => {
		const missing = inputs
			.filter( ( { tag } ) => ! NOT_FILLED.test( tag ) )
			.filter( ( { tag } ) => ! SPREAD.test( tag ) )
			.map( describeTag );
		expect( missing ).toEqual( [] );
	} );

	it( 'puts it on every WordPress text control', () => {
		const missing = textControls
			.filter( ( { tag } ) => ! SPREAD.test( tag ) )
			.map( describeTag );
		expect( missing ).toEqual( [] );
	} );

	it( 'is imported wherever it is used', () => {
		const unimported = sourceFiles( ADMIN_ROOT )
			.filter( ( file ) => {
				const source = fs.readFileSync( file, 'utf8' );
				return (
					SPREAD.test( source ) &&
					! /import\s*\{\s*NO_AUTOFILL\s*\}/.test( source )
				);
			} )
			.map( ( file ) => path.relative( ADMIN_ROOT, file ) );
		expect( unimported ).toEqual( [] );
	} );

	it( 'leaves checkboxes and radios as they are', () => {
		const changed = inputs
			.filter( ( { tag } ) => NOT_FILLED.test( tag ) )
			.filter( ( { tag } ) => SPREAD.test( tag ) )
			.map( ( { relative } ) => relative );
		expect( changed ).toEqual( [] );
	} );
} );
