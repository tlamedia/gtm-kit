/**
 * Setup-wizard Premium step: variant selection, skip conditions and flow.
 *
 * The skip condition keeps paying customers out of the step, and the flow
 * helpers make sure removing the step cannot strand the step before it.
 */
import {
	WIZARD_VARIANTS,
	countryFromLocale,
	isEuSite,
	selectWizardVariant,
	isWizardUpsellEnabled,
	getWizardVariant,
	getWizardContent,
} from '../premiumWizard';
import {
	resolveWizardSteps,
	getNextStepPath,
	getWizardSteps,
} from '../../app/utils/get-steps';
import { CTA_SURFACES } from '../../constants/premiumLinks';
import SettingsService from '../../services/SettingsService';

const setBridge = ( data = {} ) => {
	SettingsService.data = data;
};

afterEach( () => {
	SettingsService.data = {};
} );

const paths = ( steps ) => steps.map( ( step ) => step.path );

describe( 'countryFromLocale', () => {
	it( 'reads the country segment', () => {
		expect( countryFromLocale( 'da_DK' ) ).toBe( 'DK' );
		expect( countryFromLocale( 'pt_BR' ) ).toBe( 'BR' );
	} );

	it( 'ignores a variant suffix', () => {
		expect( countryFromLocale( 'de_DE_formal' ) ).toBe( 'DE' );
	} );

	it( 'is empty for a bare language or junk', () => {
		expect( countryFromLocale( 'da' ) ).toBe( '' );
		expect( countryFromLocale( '' ) ).toBe( '' );
		expect( countryFromLocale( undefined ) ).toBe( '' );
		expect( countryFromLocale( 'xx_123' ) ).toBe( '' );
	} );
} );

describe( 'isEuSite', () => {
	it( 'trusts the store country over the locale', () => {
		expect( isEuSite( { storeCountry: 'DK', locale: 'en_US' } ) ).toBe(
			true
		);
		expect( isEuSite( { storeCountry: 'US', locale: 'da_DK' } ) ).toBe(
			false
		);
	} );

	it( 'counts EEA countries outside the EU', () => {
		expect( isEuSite( { storeCountry: 'NO' } ) ).toBe( true );
		expect( isEuSite( { storeCountry: 'IS' } ) ).toBe( true );
	} );

	it( 'does not count the UK or Switzerland', () => {
		expect( isEuSite( { storeCountry: 'GB' } ) ).toBe( false );
		expect( isEuSite( { storeCountry: 'CH' } ) ).toBe( false );
	} );

	it( 'falls back to the locale country', () => {
		expect( isEuSite( { locale: 'da_DK' } ) ).toBe( true );
		expect( isEuSite( { locale: 'en_US' } ) ).toBe( false );
		expect( isEuSite( { locale: 'en_IE' } ) ).toBe( true );
	} );

	it( 'falls back to an unambiguous EU language', () => {
		expect( isEuSite( { locale: 'da' } ) ).toBe( true );
		expect( isEuSite( { locale: 'pl' } ) ).toBe( true );
	} );

	it( 'does not guess the EU from a global language', () => {
		expect( isEuSite( { locale: 'en' } ) ).toBe( false );
		expect( isEuSite( { locale: 'es' } ) ).toBe( false );
		expect( isEuSite( { locale: 'pt' } ) ).toBe( false );
		expect( isEuSite( { locale: 'fr' } ) ).toBe( false );
	} );

	it( 'is false with no data at all', () => {
		expect( isEuSite() ).toBe( false );
		expect( isEuSite( {} ) ).toBe( false );
	} );
} );

describe( 'selectWizardVariant', () => {
	it( 'picks the Woo variant for a shop', () => {
		expect( selectWizardVariant( { hasWoo: true } ) ).toBe(
			WIZARD_VARIANTS.WOO
		);
	} );

	it( 'prefers the Woo variant over the EU variant', () => {
		expect(
			selectWizardVariant( { hasWoo: true, storeCountry: 'DK' } )
		).toBe( WIZARD_VARIANTS.WOO );
	} );

	it( 'picks the EU variant for an EU site without a shop', () => {
		expect( selectWizardVariant( { locale: 'da_DK' } ) ).toBe(
			WIZARD_VARIANTS.EU
		);
	} );

	it( 'falls back to generic outside the EU without a shop', () => {
		expect( selectWizardVariant( { locale: 'en_US' } ) ).toBe(
			WIZARD_VARIANTS.GENERIC
		);
		expect( selectWizardVariant() ).toBe( WIZARD_VARIANTS.GENERIC );
	} );
} );

describe( 'getWizardVariant', () => {
	it( 'reads WooCommerce from the payload', () => {
		setBridge( { plugins: { woocommerce: true }, wpLocale: 'en_US' } );
		expect( getWizardVariant() ).toBe( WIZARD_VARIANTS.WOO );
	} );

	it( 'reads the store country from the payload', () => {
		setBridge( { storeCountry: 'DK', wpLocale: 'en_US' } );
		expect( getWizardVariant() ).toBe( WIZARD_VARIANTS.EU );
	} );

	it( 'reads the locale from the payload', () => {
		setBridge( { wpLocale: 'da_DK' } );
		expect( getWizardVariant() ).toBe( WIZARD_VARIANTS.EU );
	} );

	it( 'is generic on an empty payload', () => {
		setBridge( {} );
		expect( getWizardVariant() ).toBe( WIZARD_VARIANTS.GENERIC );
	} );
} );

describe( 'isWizardUpsellEnabled', () => {
	it( 'is enabled on a free-only install', () => {
		setBridge( {} );
		expect( isWizardUpsellEnabled() ).toBe( true );
	} );

	it( 'is disabled when the Woo Add-On is active', () => {
		setBridge( { isPremium: true } );
		expect( isWizardUpsellEnabled() ).toBe( false );
	} );

	it( 'is disabled when Premium is active', () => {
		setBridge( { isPremium: true, isPremiumPlugin: true } );
		expect( isWizardUpsellEnabled() ).toBe( false );
	} );
} );

describe( 'the wizard flow', () => {
	it( 'places the step between Automatic Updates and Getting Started', () => {
		expect( paths( resolveWizardSteps( true ) ) ).toEqual( [
			'/welcome',
			'/essential-settings',
			'/share-anonymous-data',
			'/automatic-updates',
			'/premium',
			'/getting-started',
		] );
	} );

	it( 'omits the step when the upsell is off', () => {
		expect( paths( resolveWizardSteps( false ) ) ).toEqual( [
			'/welcome',
			'/essential-settings',
			'/share-anonymous-data',
			'/automatic-updates',
			'/getting-started',
		] );
	} );

	it( 'numbers steps contiguously in both flows', () => {
		[ true, false ].forEach( ( withUpsell ) => {
			const steps = resolveWizardSteps( withUpsell );
			expect( steps.map( ( step ) => step.step ) ).toEqual(
				steps.map( ( _, index ) => index + 1 )
			);
		} );
	} );

	it( 'reads the flow for this install', () => {
		setBridge( { isPremium: true } );
		expect( paths( getWizardSteps() ) ).not.toContain( '/premium' );
		setBridge( {} );
		expect( paths( getWizardSteps() ) ).toContain( '/premium' );
	} );
} );

describe( 'getNextStepPath', () => {
	const withStep = resolveWizardSteps( true );
	const withoutStep = resolveWizardSteps( false );

	it( 'sends Automatic Updates to the Premium step when it exists', () => {
		expect( getNextStepPath( '/automatic-updates', withStep ) ).toBe(
			'/premium'
		);
	} );

	it( 'sends Automatic Updates straight on when the step is skipped', () => {
		expect( getNextStepPath( '/automatic-updates', withoutStep ) ).toBe(
			'/getting-started'
		);
	} );

	it( 'sends the Premium step to Getting Started', () => {
		expect( getNextStepPath( '/premium', withStep ) ).toBe(
			'/getting-started'
		);
	} );

	it( 'stays put on the last step', () => {
		expect( getNextStepPath( '/getting-started', withStep ) ).toBe(
			'/getting-started'
		);
	} );

	it( 'falls back to the last step for an unknown path', () => {
		expect( getNextStepPath( '/nope', withStep ) ).toBe(
			'/getting-started'
		);
	} );
} );

describe( 'variant content', () => {
	const variants = Object.values( WIZARD_VARIANTS );

	it( 'gives every variant a title, copy, link and call to action', () => {
		variants.forEach( ( variant ) => {
			const content = getWizardContent( variant );
			expect( content.title ).toBeTruthy();
			expect( content.paragraphs.length ).toBeGreaterThan( 0 );
			expect( content.cta ).toBeTruthy();
			expect( CTA_SURFACES ).toHaveProperty( content.link );
		} );
	} );

	it( 'gives every variant its own short link', () => {
		const links = variants.map(
			( variant ) => getWizardContent( variant ).link
		);
		expect( new Set( links ).size ).toBe( links.length );
	} );

	it( 'never reuses a Premium page short link on the wizard', () => {
		const pageLinks = [
			'cardServerSide',
			'cardConsent',
			'cardCalculator',
			'pagePricing',
		];
		variants.forEach( ( variant ) => {
			expect( pageLinks ).not.toContain(
				getWizardContent( variant ).link
			);
		} );
	} );

	it( 'cites a source wherever it makes a claim', () => {
		const claimedWithoutSource = variants
			.map( ( variant ) => getWizardContent( variant ) )
			.filter( ( content ) => content.claim && ! content.source );
		expect( claimedWithoutSource ).toEqual( [] );
	} );

	it( 'names the five clusters on the generic variant', () => {
		const content = getWizardContent( WIZARD_VARIANTS.GENERIC );
		expect( content.clusters ).toHaveLength( 5 );
		expect( content.clusters.every( Boolean ) ).toBe( true );
	} );
} );
