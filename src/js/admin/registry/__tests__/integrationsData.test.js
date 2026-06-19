/**
 * Integrations hub data helpers.
 */
import {
	STATUS,
	getIntegrationStatus,
	getIntegrations,
	getIntegrationPrimaryCapability,
	getIntegrationSections,
} from '../integrationsData';
import SettingsService from '../../services/SettingsService';

const setBridge = ( { plugins = {}, integrations = {}, settings = {} } ) => {
	SettingsService.data = { plugins, integrations, settings };
};

afterEach( () => {
	SettingsService.data = {};
} );

const META = {
	woocommerce: {
		title: 'WooCommerce',
		option: 'woocommerce_integration',
		description: 'Shop',
		type: 'core',
	},
	cf7: {
		title: 'Contact Form 7',
		option: 'cf7_integration',
		description: 'Forms',
		type: 'core',
	},
};

describe( 'getIntegrationStatus', () => {
	it( 'is Active when the plugin is active and the integration is on', () => {
		setBridge( {
			plugins: { woocommerce: true },
			settings: { integrations: { woocommerce_integration: true } },
		} );
		expect(
			getIntegrationStatus( 'woocommerce', 'woocommerce_integration' )
		).toBe( STATUS.ACTIVE );
	} );

	it( 'is Off when the plugin is active but the integration is off', () => {
		setBridge( {
			plugins: { woocommerce: true },
			settings: { integrations: { woocommerce_integration: false } },
		} );
		expect(
			getIntegrationStatus( 'woocommerce', 'woocommerce_integration' )
		).toBe( STATUS.OFF );
	} );

	it( 'is Not installed when the plugin is inactive', () => {
		setBridge( { plugins: { woocommerce: false } } );
		expect(
			getIntegrationStatus( 'woocommerce', 'woocommerce_integration' )
		).toBe( STATUS.NOT_INSTALLED );
	} );
} );

describe( 'getIntegrations', () => {
	it( 'lists the bridge integrations with resolved status', () => {
		setBridge( {
			integrations: META,
			plugins: { woocommerce: true, cf7: false },
			settings: { integrations: { woocommerce_integration: true } },
		} );

		const list = getIntegrations();
		expect( list.map( ( i ) => i.slug ) ).toEqual( [
			'woocommerce',
			'cf7',
		] );
		expect( list[ 0 ].status ).toBe( STATUS.ACTIVE );
		expect( list[ 1 ].status ).toBe( STATUS.NOT_INSTALLED );
	} );
} );

describe( 'getIntegrationPrimaryCapability', () => {
	it( 'maps CF7 to the Events capability where its settings live', () => {
		setBridge( {} );
		expect( getIntegrationPrimaryCapability( 'cf7' )?.id ).toBe( 'events' );
	} );

	it( 'maps WooCommerce to the Commerce capability', () => {
		setBridge( {} );
		expect( getIntegrationPrimaryCapability( 'woocommerce' )?.id ).toBe(
			'commerce'
		);
	} );
} );

describe( 'getIntegrationSections', () => {
	it( 'returns the dedicated CF7 section but not the mixed GA4 events one', () => {
		setBridge( {} );
		const ids = getIntegrationSections( 'cf7' ).map( ( s ) => s.id );
		expect( ids ).toContain( 'cf7' );
		expect( ids ).not.toContain( 'engagement' );
	} );

	it( 'returns only the flagged WooCommerce config sections', () => {
		setBridge( {} );
		const ids = getIntegrationSections( 'woocommerce' ).map(
			( s ) => s.id
		);
		expect( ids ).toEqual( [
			'woocommerce',
			'woo-advanced',
			'woo-css-selectors',
		] );
		expect( ids ).not.toContain( 'woo-basic' );
		expect( ids ).not.toContain( 'woo-webhooks' );
	} );
} );
