/**
 * Variant selection for the setup wizard's Premium step.
 *
 * The step adapts to the site it is running on, using only data the wizard is
 * already given. The selection is a pure function of that data so it can be
 * reasoned about and tested without rendering the wizard.
 */
import { __ } from '@wordpress/i18n';
import SettingsService from '../services/SettingsService';
import { isPremiumSurfaceVisible, PREMIUM_CARDS } from './premiumSurface';

export const WIZARD_VARIANTS = {
	WOO: 'woo',
	EU: 'eu',
	GENERIC: 'generic',
};

/**
 * EU and EEA country codes. A store or site based here is subject to the
 * consent rules the EU variant speaks to.
 */
const EU_EEA_COUNTRIES = [
	'AT',
	'BE',
	'BG',
	'HR',
	'CY',
	'CZ',
	'DK',
	'EE',
	'FI',
	'FR',
	'DE',
	'GR',
	'HU',
	'IE',
	'IT',
	'LV',
	'LT',
	'LU',
	'MT',
	'NL',
	'PL',
	'PT',
	'RO',
	'SK',
	'SI',
	'ES',
	'SE',
	'IS',
	'LI',
	'NO',
];

/**
 * Languages that imply an EU or EEA site on their own.
 *
 * Only used when no country is available. Globally shared languages (English,
 * French, Spanish, Portuguese) are deliberately absent: guessing the EU from
 * them is wrong more often than it is right, and the generic variant is the
 * safe outcome.
 */
const EU_ONLY_LANGUAGES = [
	'bg',
	'cs',
	'da',
	'de',
	'el',
	'et',
	'fi',
	'ga',
	'hr',
	'hu',
	'is',
	'it',
	'lt',
	'lv',
	'mt',
	'nb',
	'nl',
	'nn',
	'no',
	'pl',
	'ro',
	'sk',
	'sl',
	'sv',
];

/**
 * Extract the country segment of a WordPress locale.
 *
 * Locales are `lang_COUNTRY`, sometimes with a variant suffix (`de_DE_formal`).
 * Anything that is not a two-letter country segment yields an empty string.
 *
 * @param {string} locale A WordPress locale.
 * @return {string} An upper-case country code, or an empty string.
 */
export const countryFromLocale = ( locale ) => {
	const segment = String( locale || '' ).split( '_' )[ 1 ] || '';
	return /^[A-Za-z]{2}$/.test( segment ) ? segment.toUpperCase() : '';
};

/**
 * Whether the site looks like it is based in the EU or EEA.
 *
 * The store's own base country is the strongest signal and wins outright. The
 * site locale's country is the fallback, and a bare language code is the last
 * resort.
 *
 * @param {Object} context                The site context.
 * @param {string} [context.storeCountry] WooCommerce base country code.
 * @param {string} [context.locale]       The WordPress locale.
 * @return {boolean} True when the site is likely EU or EEA based.
 */
export const isEuSite = ( { storeCountry = '', locale = '' } = {} ) => {
	const store = String( storeCountry || '' ).toUpperCase();
	if ( store ) {
		return EU_EEA_COUNTRIES.includes( store );
	}

	const localeCountry = countryFromLocale( locale );
	if ( localeCountry ) {
		return EU_EEA_COUNTRIES.includes( localeCountry );
	}

	const language = String( locale || '' )
		.split( '_' )[ 0 ]
		.toLowerCase();
	return language !== '' && EU_ONLY_LANGUAGES.includes( language );
};

/**
 * Choose the variant for a site.
 *
 * A shop takes the reliability pitch even in the EU: a store owner's first
 * concern is orders arriving, and the consent story is part of that pitch
 * anyway.
 *
 * @param {Object}  context                The site context.
 * @param {boolean} [context.hasWoo]       Whether WooCommerce is active.
 * @param {string}  [context.storeCountry] WooCommerce base country code.
 * @param {string}  [context.locale]       The WordPress locale.
 * @return {string} One of WIZARD_VARIANTS.
 */
export const selectWizardVariant = ( {
	hasWoo = false,
	storeCountry = '',
	locale = '',
} = {} ) => {
	if ( hasWoo ) {
		return WIZARD_VARIANTS.WOO;
	}
	if ( isEuSite( { storeCountry, locale } ) ) {
		return WIZARD_VARIANTS.EU;
	}
	return WIZARD_VARIANTS.GENERIC;
};

/**
 * Whether the wizard should include the Premium step.
 *
 * Shares the settings page's rule, so a paying customer is never shown the
 * step in either place.
 *
 * @return {boolean} True when the step belongs in the flow.
 */
export const isWizardUpsellEnabled = () => isPremiumSurfaceVisible();

/**
 * The variant for this install, read from the wizard's settings payload.
 *
 * @return {string} One of WIZARD_VARIANTS.
 */
export const getWizardVariant = () =>
	selectWizardVariant( {
		hasWoo: SettingsService.isPluginActive( 'woocommerce' ),
		storeCountry: SettingsService.getStoreCountry(),
		locale: SettingsService.getWpLocale(),
	} );

/**
 * A card definition by id, so the step can borrow a claim and its source
 * instead of restating them.
 *
 * @param {string} id A card id.
 * @return {Object} The card definition.
 */
const card = ( id ) => PREMIUM_CARDS.find( ( entry ) => entry.id === id ) || {};

/**
 * Build the copy for a variant.
 *
 * Returned as data rather than markup so the step component stays a renderer
 * and the wording is reviewable in one place.
 *
 * @param {string} variant One of WIZARD_VARIANTS.
 * @return {Object} The variant's content.
 */
export const getWizardContent = ( variant ) => {
	if ( variant === WIZARD_VARIANTS.WOO ) {
		const source = card( 'server-side' );
		return {
			title: __( 'Make sure every order reaches GA4', 'gtm-kit' ),
			paragraphs: [
				__(
					'GTM Kit tracks your WooCommerce purchases from the browser. When a payment gateway redirects the customer away from your thank-you page, or the browser blocks the request, that order is never measured.',
					'gtm-kit'
				),
				__(
					'GTM Kit Premium sends purchases from your server as well, matched by a stable event ID so nothing is counted twice, and adds refund and order-status events.',
					'gtm-kit'
				),
			],
			claim: source.claim,
			source: source.source,
			link: 'wizardWoo',
			cta: __( 'See what Premium adds for stores', 'gtm-kit' ),
		};
	}

	if ( variant === WIZARD_VARIANTS.EU ) {
		const source = card( 'consent' );
		return {
			title: __( 'Keep measuring around the consent banner', 'gtm-kit' ),
			paragraphs: [
				__(
					'Events that happen before a visitor answers your consent banner are usually dropped rather than delayed, so the start of the visit is lost even when consent is granted a moment later.',
					'gtm-kit'
				),
				__(
					'GTM Kit Premium queues those events and replays them once consent arrives, gates server-side webhooks on consent, and integrates with the WP Consent API.',
					'gtm-kit'
				),
			],
			claim: source.claim,
			source: source.source,
			link: 'wizardConsent',
			cta: __( 'See how consent-safe measurement works', 'gtm-kit' ),
		};
	}

	return {
		title: __( 'What GTM Kit Premium adds', 'gtm-kit' ),
		paragraphs: [
			__(
				'GTM Kit covers the standard GA4 events on your site. Premium is for the cases the browser cannot handle on its own.',
				'gtm-kit'
			),
		],
		clusters: [
			card( 'server-side' ).title,
			card( 'purchase-accuracy' ).title,
			card( 'consent' ).title,
			card( 'forms' ).title,
			card( 'debug' ).title,
		],
		link: 'wizardGeneric',
		cta: __( 'See pricing', 'gtm-kit' ),
	};
};
