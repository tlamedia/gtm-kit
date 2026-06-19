/**
 * Integration-active gating used by FieldRow to disable fields whose backing
 * plugin or integration is not active. A `requiresIntegration` field is only
 * interactive when the plugin is active AND the integration option is enabled.
 */
import { isIntegrationActive } from '../FieldRow';
import SettingsService from '../../../services/SettingsService';

const setPlugins = ( plugins ) => {
	SettingsService.data = { plugins };
};

afterEach( () => {
	SettingsService.data = {};
} );

describe( 'isIntegrationActive', () => {
	it( 'is false when the plugin is inactive, regardless of the option', () => {
		setPlugins( { woocommerce: false } );
		expect(
			isIntegrationActive(
				{ integrations: { woocommerce_integration: '1' } },
				'woocommerce',
				'woocommerce_integration'
			)
		).toBe( false );
	} );

	it( 'is true when the plugin is active and the option is enabled', () => {
		setPlugins( { woocommerce: true } );
		expect(
			isIntegrationActive(
				{ integrations: { woocommerce_integration: '1' } },
				'woocommerce',
				'woocommerce_integration'
			)
		).toBe( true );
	} );

	it( 'accepts the option as the string "1", boolean true, or number 1', () => {
		setPlugins( { woocommerce: true } );
		for ( const value of [ '1', true, 1 ] ) {
			expect(
				isIntegrationActive(
					{ integrations: { opt: value } },
					'woocommerce',
					'opt'
				)
			).toBe( true );
		}
	} );

	it( 'is false when the plugin is active but the option is unset or falsy', () => {
		setPlugins( { woocommerce: true } );
		for ( const value of [ '', '0', 0, false, undefined ] ) {
			expect(
				isIntegrationActive(
					{ integrations: { opt: value } },
					'woocommerce',
					'opt'
				)
			).toBe( false );
		}
	} );

	it( 'does not throw when the settings store has no integrations', () => {
		setPlugins( { woocommerce: true } );
		expect( isIntegrationActive( {}, 'woocommerce', 'opt' ) ).toBe( false );
		expect( isIntegrationActive( undefined, 'woocommerce', 'opt' ) ).toBe(
			false
		);
	} );
} );
