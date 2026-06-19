/**
 * Integration registry for the filter chips.
 */
import {
	integrationLabel,
	getCapabilityIntegrations,
	effectiveFilterFor,
} from '../integrations';
import SettingsService from '../../services/SettingsService';

const setPlugins = ( plugins ) => {
	SettingsService.data = { plugins };
};

afterEach( () => {
	setPlugins( {} );
} );

describe( 'integrationLabel', () => {
	it( 'returns the full product name for a known slug', () => {
		expect( integrationLabel( 'edd' ) ).toBe( 'Easy Digital Downloads' );
		expect( integrationLabel( 'woocommerce' ) ).toBe( 'WooCommerce' );
	} );

	it( 'falls back to the slug for an unknown integration', () => {
		expect( integrationLabel( 'mystery' ) ).toBe( 'mystery' );
	} );
} );

describe( 'getCapabilityIntegrations', () => {
	it( 'offers WooCommerce and EDD on Commerce when both are active', () => {
		setPlugins( { woocommerce: true, edd: true } );
		expect(
			getCapabilityIntegrations( 'commerce' ).map( ( i ) => i.slug )
		).toEqual( [ 'woocommerce', 'edd' ] );
	} );

	it( 'omits an integration whose plugin is inactive', () => {
		setPlugins( { woocommerce: true, edd: false } );
		expect(
			getCapabilityIntegrations( 'commerce' ).map( ( i ) => i.slug )
		).toEqual( [ 'woocommerce' ] );
	} );

	it( 'does not offer Contact Form 7 on Commerce (no CF7 fields there)', () => {
		setPlugins( { woocommerce: true, edd: true, cf7: true } );
		expect(
			getCapabilityIntegrations( 'commerce' ).map( ( i ) => i.slug )
		).not.toContain( 'cf7' );
	} );
} );

describe( 'effectiveFilterFor', () => {
	it( 'applies a filter that is available on the capability', () => {
		setPlugins( { woocommerce: true, edd: true } );
		expect( effectiveFilterFor( 'commerce', 'woocommerce' ) ).toBe(
			'woocommerce'
		);
	} );

	it( 'ignores a filter that is not available on the capability', () => {
		setPlugins( { woocommerce: true, cf7: true } );
		// CF7 has no fields on Commerce, so the stored filter does not apply.
		expect( effectiveFilterFor( 'commerce', 'cf7' ) ).toBeNull();
	} );
} );
