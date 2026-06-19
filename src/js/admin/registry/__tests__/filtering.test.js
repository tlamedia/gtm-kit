/**
 * Integration filter logic.
 *
 * Runs against the real bundled registry (free fields + stubs), with the active
 * plugins mocked on the settings service so the plugin-active gating can be
 * exercised both ways.
 */
import {
	fieldMatchesFilter,
	isIntegrationPluginActive,
	countCapabilityIntegrationFields,
	getCapabilitySectionsView,
} from '../filtering';
import SettingsService from '../../services/SettingsService';

const setPlugins = ( plugins ) => {
	SettingsService.data = { plugins };
};

afterEach( () => {
	setPlugins( {} );
} );

describe( 'fieldMatchesFilter', () => {
	it( 'passes every field when no filter is active', () => {
		expect( fieldMatchesFilter( { integration: 'edd' }, null ) ).toBe(
			true
		);
	} );

	it( 'always passes universal (untagged) fields', () => {
		expect(
			fieldMatchesFilter( { integration: null }, 'woocommerce' )
		).toBe( true );
	} );

	it( 'drops a field tagged with a different integration', () => {
		expect(
			fieldMatchesFilter( { integration: 'edd' }, 'woocommerce' )
		).toBe( false );
	} );
} );

describe( 'isIntegrationPluginActive', () => {
	it( 'treats universal (untagged) fields as active', () => {
		setPlugins( {} );
		expect( isIntegrationPluginActive( null ) ).toBe( true );
	} );

	it( 'reflects the plugin-active map for a tagged integration', () => {
		setPlugins( { woocommerce: true, edd: false } );
		expect( isIntegrationPluginActive( 'woocommerce' ) ).toBe( true );
		expect( isIntegrationPluginActive( 'edd' ) ).toBe( false );
	} );
} );

describe( 'getCapabilitySectionsView', () => {
	it( 'hides EDD as a placeholder under a WooCommerce filter when EDD is active', () => {
		setPlugins( { woocommerce: true, edd: true } );
		const view = getCapabilitySectionsView( 'commerce', 'woocommerce' );
		const visibleIds = view.visibleSections.map( ( s ) => s.id );

		expect( view.hiddenIntegrations ).toContain( 'edd' );
		expect( visibleIds ).toContain( 'woocommerce' );
		expect( visibleIds.some( ( id ) => id.startsWith( 'edd' ) ) ).toBe(
			false
		);
	} );

	it( 'drops EDD sections entirely (no placeholder) when EDD is inactive', () => {
		setPlugins( { woocommerce: true, edd: false } );
		const view = getCapabilitySectionsView( 'commerce', null );
		const visibleIds = view.visibleSections.map( ( s ) => s.id );

		expect( visibleIds.some( ( id ) => id.startsWith( 'edd' ) ) ).toBe(
			false
		);
		expect( view.hiddenIntegrations ).not.toContain( 'edd' );
		expect( visibleIds ).toContain( 'woocommerce' );
	} );
} );

describe( 'countCapabilityIntegrationFields', () => {
	it( 'counts WooCommerce fields in Commerce', () => {
		expect(
			countCapabilityIntegrationFields( 'commerce', 'woocommerce' )
		).toBeGreaterThan( 0 );
	} );
} );
