/**
 * Registry consistency checks.
 *
 * These guard the declarative registry the shell renders from: a typo in a
 * control id, a field pointing at a missing section, or a duplicate storage key
 * would silently drop or misplace a setting in the UI. Catch it here instead.
 */
import { CAPABILITIES, SECTIONS, getSections } from '../capabilities';
import { getAllFields, getSectionItems } from '../assemble';
import { getControl } from '../controls';
import { getBlock } from '../blocks';

const CAPABILITY_IDS = new Set( CAPABILITIES.map( ( c ) => c.id ) );
const SECTION_KEYS = new Set(
	SECTIONS.map( ( s ) => `${ s.capability }/${ s.id }` )
);
const FIELDS = getAllFields();

describe( 'registry: capabilities and sections', () => {
	it( 'every section belongs to a known capability', () => {
		SECTIONS.forEach( ( section ) => {
			expect( CAPABILITY_IDS.has( section.capability ) ).toBe( true );
		} );
	} );

	it( 'capability ids are unique', () => {
		const ids = CAPABILITIES.map( ( c ) => c.id );
		expect( new Set( ids ).size ).toBe( ids.length );
	} );
} );

describe( 'registry: fields', () => {
	it( 'storage keys are unique', () => {
		const keys = FIELDS.map( ( f ) => f.key );
		const duplicates = keys.filter(
			( key, i ) => keys.indexOf( key ) !== i
		);
		expect( duplicates ).toEqual( [] );
	} );

	it( 'every field key is a dotted group.key', () => {
		FIELDS.forEach( ( field ) => {
			expect( field.key ).toMatch( /^[a-z]+\.[a-z0-9_.]+$/ );
		} );
	} );

	it( 'every field resolves to a registered control type', () => {
		FIELDS.forEach( ( field ) => {
			expect( getControl( field.control ) ).toBeTruthy();
		} );
	} );

	it( 'every field lands in an existing capability/section', () => {
		FIELDS.forEach( ( field ) => {
			expect( CAPABILITY_IDS.has( field.capability ) ).toBe( true );
			expect(
				SECTION_KEYS.has( `${ field.capability }/${ field.section }` )
			).toBe( true );
		} );
	} );
} );

describe( 'registry: composed section content', () => {
	it( 'every composed item resolves to a control or block renderer', () => {
		CAPABILITIES.forEach( ( capability ) => {
			getSections( capability.id ).forEach( ( section ) => {
				const { items, aside } = getSectionItems(
					capability.id,
					section.id
				);

				items.forEach( ( item ) => {
					const renderer = item.isBlock
						? getBlock( item.type )
						: getControl( item.control );
					expect( renderer ).toBeTruthy();
				} );

				aside.forEach( ( block ) => {
					expect( getBlock( block.type ) ).toBeTruthy();
				} );
			} );
		} );
	} );
} );
