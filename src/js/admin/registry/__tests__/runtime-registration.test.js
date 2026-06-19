/**
 * Runtime registration contract.
 *
 * Guards the bridge that lets add-ons register fields/sections at runtime: the
 * schema-version gate (an incompatible payload is ignored) and real-over-stub
 * resolution (a registered field supersedes the core stub of the same key).
 */
import { SCHEMA_VERSION, getRegisteredFields } from '../bridge';
import { getAllFields } from '../assemble';

const STUB_KEY = 'premium.woocommerce_webhooks';

const setRegistry = ( registry ) => {
	global.window.gtmkitSettings = registry
		? { settingsRegistry: registry }
		: {};
};

afterEach( () => {
	setRegistry( null );
} );

describe( 'bridge: schema version gate', () => {
	it( 'returns no fields when the bridge is empty', () => {
		setRegistry( null );
		expect( getRegisteredFields() ).toEqual( [] );
	} );

	it( 'ignores a payload built for a different schema version', () => {
		setRegistry( {
			schemaVersion: SCHEMA_VERSION + 1,
			fields: [ { key: 'premium.x' } ],
		} );
		expect( getRegisteredFields() ).toEqual( [] );
		// eslint-disable-next-line no-console
		expect( console ).toHaveWarned();
	} );

	it( 'returns fields for a matching schema version', () => {
		setRegistry( {
			schemaVersion: SCHEMA_VERSION,
			fields: [ { key: 'premium.x' } ],
		} );
		expect( getRegisteredFields() ).toEqual( [ { key: 'premium.x' } ] );
	} );
} );

describe( 'assemble: real-over-stub resolution', () => {
	it( 'ships the field as a locked stub when no add-on registers it', () => {
		setRegistry( null );
		const field = getAllFields().find( ( f ) => f.key === STUB_KEY );
		expect( field ).toBeTruthy();
		expect( field.stub ).toBe( true );
	} );

	it( 'replaces the stub with a registered real field of the same key', () => {
		setRegistry( {
			schemaVersion: SCHEMA_VERSION,
			fields: [
				{
					key: STUB_KEY,
					capability: 'commerce',
					section: 'woo-webhooks',
					order: 10,
					control: 'toggle',
					label: 'Real webhooks field',
					tier: 'woo',
				},
			],
		} );

		const matches = getAllFields().filter( ( f ) => f.key === STUB_KEY );
		expect( matches ).toHaveLength( 1 );
		expect( matches[ 0 ].stub ).toBeUndefined();
		expect( matches[ 0 ].label ).toBe( 'Real webhooks field' );
	} );

	it( 'replaces the event-deferral stub with the registered composite field', () => {
		const key = 'premium.event_deferral_queue';

		setRegistry( null );
		const stub = getAllFields().find( ( f ) => f.key === key );
		expect( stub.stub ).toBe( true );
		expect( stub.control ).toBe( 'toggle' );

		setRegistry( {
			schemaVersion: SCHEMA_VERSION,
			fields: [
				{
					key,
					capability: 'consent',
					section: 'event-deferral',
					order: 10,
					control: 'event-deferral',
					label: 'Defer events until consent is granted',
					tier: 'premium',
					integration: null,
				},
			],
		} );

		const matches = getAllFields().filter( ( f ) => f.key === key );
		expect( matches ).toHaveLength( 1 );
		expect( matches[ 0 ].stub ).toBeUndefined();
		expect( matches[ 0 ].control ).toBe( 'event-deferral' );
	} );
} );
