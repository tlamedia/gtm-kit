/**
 * Premium overview surface: visibility and card ordering.
 *
 * The visibility rule decides whether a paying customer is shown marketing for
 * something they already own, so it is covered across every tier combination.
 */
import {
	PREMIUM_CARDS,
	isPremiumSurfaceVisible,
	isPremiumSurfaceVisibleForTier,
	orderPremiumCards,
	getPremiumCards,
} from '../premiumSurface';
import {
	isCapabilityAvailable,
	getCapabilitiesByZone,
	ZONES,
} from '../capabilities';
import { TIERS } from '../../constants/tiers';
import { CTA_SURFACES, premiumLink } from '../../constants/premiumLinks';
import SettingsService from '../../services/SettingsService';

const setBridge = ( data = {} ) => {
	SettingsService.data = data;
};

afterEach( () => {
	SettingsService.data = {};
} );

const ids = ( cards ) => cards.map( ( card ) => card.id );

describe( 'isPremiumSurfaceVisibleForTier', () => {
	it( 'shows the surface on a free-only install', () => {
		expect( isPremiumSurfaceVisibleForTier( TIERS.FREE ) ).toBe( true );
	} );

	it( 'hides the surface for a Woo Add-On customer', () => {
		expect( isPremiumSurfaceVisibleForTier( TIERS.WOO ) ).toBe( false );
	} );

	it( 'hides the surface for a Premium customer', () => {
		expect( isPremiumSurfaceVisibleForTier( TIERS.PREMIUM ) ).toBe( false );
	} );
} );

describe( 'isPremiumSurfaceVisible', () => {
	it( 'is visible when neither add-on is active', () => {
		setBridge( { isPremium: false, isPremiumPlugin: false } );
		expect( isPremiumSurfaceVisible() ).toBe( true );
	} );

	it( 'is hidden when the Woo Add-On is active', () => {
		setBridge( { isPremium: true, isPremiumPlugin: false } );
		expect( isPremiumSurfaceVisible() ).toBe( false );
	} );

	it( 'is hidden when Premium is active', () => {
		setBridge( { isPremium: true, isPremiumPlugin: true } );
		expect( isPremiumSurfaceVisible() ).toBe( false );
	} );

	it( 'is hidden when both add-ons are active', () => {
		setBridge( { isPremium: true, isPremiumPlugin: true } );
		expect( isPremiumSurfaceVisible() ).toBe( false );
	} );
} );

describe( 'the Premium nav entry', () => {
	const premium = { id: 'premium', zone: ZONES.PLUGIN, freeOnly: true };

	it( 'is offered on a free-only install', () => {
		setBridge( {} );
		expect( isCapabilityAvailable( premium ) ).toBe( true );
		expect( ids( getCapabilitiesByZone( ZONES.PLUGIN ) ) ).toContain(
			'premium'
		);
	} );

	it( 'is absent for a Woo Add-On customer', () => {
		setBridge( { isPremium: true } );
		expect( isCapabilityAvailable( premium ) ).toBe( false );
		expect( ids( getCapabilitiesByZone( ZONES.PLUGIN ) ) ).not.toContain(
			'premium'
		);
	} );

	it( 'is absent for a Premium customer', () => {
		setBridge( { isPremium: true, isPremiumPlugin: true } );
		expect( ids( getCapabilitiesByZone( ZONES.PLUGIN ) ) ).not.toContain(
			'premium'
		);
	} );

	it( 'leaves the other plugin-zone entries alone', () => {
		setBridge( { isPremium: true, isPremiumPlugin: true } );
		const plugin = ids( getCapabilitiesByZone( ZONES.PLUGIN ) );
		expect( plugin ).toEqual( [ 'license', 'tools', 'support' ] );
	} );

	it( 'sorts above License in the plugin zone', () => {
		setBridge( {} );
		const plugin = ids( getCapabilitiesByZone( ZONES.PLUGIN ) );
		expect( plugin.indexOf( 'premium' ) ).toBeLessThan(
			plugin.indexOf( 'license' )
		);
	} );
} );

describe( 'orderPremiumCards', () => {
	it( 'uses the default order when nothing is detected', () => {
		expect( ids( orderPremiumCards() ) ).toEqual( [
			'server-side',
			'purchase-accuracy',
			'consent',
			'forms',
			'debug',
			'calculator',
		] );
	} );

	it( 'leads with the commerce cards when WooCommerce is active', () => {
		const ordered = ids( orderPremiumCards( { hasWoo: true } ) );
		expect( ordered.slice( 0, 2 ) ).toEqual( [
			'server-side',
			'purchase-accuracy',
		] );
	} );

	it( 'surfaces the forms card higher when a form plugin is active', () => {
		const ordered = ids( orderPremiumCards( { hasForms: true } ) );
		expect( ordered.indexOf( 'forms' ) ).toBeLessThan(
			ordered.indexOf( 'consent' )
		);
		expect( ordered ).toEqual( [
			'server-side',
			'forms',
			'purchase-accuracy',
			'consent',
			'debug',
			'calculator',
		] );
	} );

	it( 'keeps commerce ahead of forms when both are active', () => {
		const ordered = ids(
			orderPremiumCards( { hasWoo: true, hasForms: true } )
		);
		expect( ordered ).toEqual( [
			'server-side',
			'purchase-accuracy',
			'forms',
			'consent',
			'debug',
			'calculator',
		] );
	} );

	it( 'always ends on the calculator card', () => {
		[
			{},
			{ hasWoo: true },
			{ hasForms: true },
			{ hasWoo: true, hasForms: true },
		].forEach( ( context ) => {
			const ordered = ids( orderPremiumCards( context ) );
			expect( ordered[ ordered.length - 1 ] ).toBe( 'calculator' );
		} );
	} );

	it( 'never drops or duplicates a card', () => {
		const ordered = ids(
			orderPremiumCards( { hasWoo: true, hasForms: true } )
		);
		expect( ordered.sort() ).toEqual( ids( PREMIUM_CARDS ).sort() );
	} );
} );

describe( 'getPremiumCards', () => {
	it( 'reads WooCommerce from the settings payload', () => {
		setBridge( { plugins: { woocommerce: true } } );
		expect( ids( getPremiumCards() ).slice( 0, 2 ) ).toEqual( [
			'server-side',
			'purchase-accuracy',
		] );
	} );

	it( 'treats Contact Form 7 as a supported form plugin', () => {
		setBridge( { plugins: { cf7: true } } );
		expect( ids( getPremiumCards() )[ 1 ] ).toBe( 'forms' );
	} );

	it( 'treats Gravity Forms as a supported form plugin', () => {
		setBridge( { plugins: { gravityforms: true } } );
		expect( ids( getPremiumCards() )[ 1 ] ).toBe( 'forms' );
	} );

	it( 'falls back to the default order with no plugin data', () => {
		setBridge( {} );
		expect( ids( getPremiumCards() ) ).toEqual( ids( PREMIUM_CARDS ) );
	} );
} );

describe( 'card definitions', () => {
	it( 'gives every card its own short link', () => {
		const links = PREMIUM_CARDS.map( ( card ) => card.link );
		expect( new Set( links ).size ).toBe( links.length );
	} );

	it( 'points every card link at a known short-link surface', () => {
		PREMIUM_CARDS.forEach( ( card ) => {
			expect( CTA_SURFACES ).toHaveProperty( card.link );
		} );
	} );

	it( 'gives every card a title, body and call to action', () => {
		PREMIUM_CARDS.forEach( ( card ) => {
			expect( card.title ).toBeTruthy();
			expect( card.body ).toBeTruthy();
			expect( card.cta ).toBeTruthy();
		} );
	} );

	it( 'backs every claim with a source', () => {
		const unsourced = PREMIUM_CARDS.filter(
			( card ) => card.claim && ! card.source
		).map( ( card ) => card.id );
		expect( unsourced ).toEqual( [] );
	} );

	it( 'makes no claim on the calculator card', () => {
		const calculator = PREMIUM_CARDS.find(
			( card ) => card.id === 'calculator'
		);
		expect( calculator.claim ).toBeUndefined();
		expect( calculator.source ).toBeUndefined();
	} );

	it( 'cites a distinct documentation page per claim', () => {
		const sources = PREMIUM_CARDS.map( ( card ) => card.source ).filter(
			Boolean
		);
		expect( sources ).toHaveLength( 5 );
		expect( new Set( sources ).size ).toBe( sources.length );
		sources.forEach( ( source ) => {
			expect( source ).toMatch(
				/^https:\/\/gtmkit\.com\/documentation\/[a-z0-9-]+\/$/
			);
		} );
	} );
} );

describe( 'link resolution', () => {
	it( 'resolves every surface to an absolute gtmkit.com URL', () => {
		Object.keys( CTA_SURFACES ).forEach( ( surface ) => {
			expect( premiumLink( surface ) ).toMatch(
				/^https:\/\/(jump\.)?gtmkit\.com\//
			);
		} );
	} );

	it( 'gives every surface a pricing fallback target', () => {
		Object.values( CTA_SURFACES ).forEach( ( entry ) => {
			expect( entry.target ).toBe( 'https://gtmkit.com/pricing/' );
		} );
	} );

	it( 'ignores the fallback target while a code is present', () => {
		expect( premiumLink( 'cardServerSide' ) ).toBe(
			'https://jump.gtmkit.com/link/8-01F58'
		);
		expect( premiumLink( 'cardServerSide' ) ).not.toBe(
			CTA_SURFACES.cardServerSide.target
		);
	} );

	it( 'has a minted short-link code for every surface', () => {
		const unminted = Object.entries( CTA_SURFACES )
			.filter( ( [ , entry ] ) => ! entry.code )
			.map( ( [ surface ] ) => surface );
		expect( unminted ).toEqual( [] );
	} );

	it( 'gives every surface a distinct code', () => {
		const codes = Object.values( CTA_SURFACES ).map(
			( entry ) => entry.code
		);
		expect( new Set( codes ).size ).toBe( codes.length );
	} );

	it( 'routes every call to action through jump.gtmkit.com', () => {
		Object.keys( CTA_SURFACES ).forEach( ( surface ) => {
			expect( premiumLink( surface ) ).toMatch(
				/^https:\/\/jump\.gtmkit\.com\/link\/[0-9]+-[A-Z0-9]+$/
			);
		} );
	} );

	it( 'uses the short link once a code is minted', () => {
		const original = CTA_SURFACES.pagePricing.code;
		CTA_SURFACES.pagePricing.code = '9-ABC12';
		expect( premiumLink( 'pagePricing' ) ).toBe(
			'https://jump.gtmkit.com/link/9-ABC12'
		);
		CTA_SURFACES.pagePricing.code = original;
	} );

	it( 'falls back to pricing for an unknown surface', () => {
		expect( premiumLink( 'nope' ) ).toBe( 'https://gtmkit.com/pricing/' );
	} );
} );
