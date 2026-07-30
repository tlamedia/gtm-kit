/**
 * Content and rules for the Premium overview surfaces.
 *
 * The card copy, the claims and their evidence links live here rather than in
 * the page component, so the wording is editable in one place and the page
 * stays a renderer. Visibility and ordering are pure functions over the tier
 * and plugin data already in the settings payload, which keeps them testable
 * without mounting React.
 */
import { __ } from '@wordpress/i18n';
import { TIERS } from '../constants/tiers';
import { EVIDENCE } from '../constants/premiumLinks';
import SettingsService from '../services/SettingsService';

/**
 * The Premium overview cards in their default order.
 *
 * `claim` states the failure mode the feature addresses. `source` is the
 * documentation page backing that claim, and is omitted where no published
 * page covers it yet rather than pointed at an approximate one.
 */
export const PREMIUM_CARDS = [
	{
		id: 'server-side',
		order: 10,
		link: 'cardServerSide',
		title: __( 'Server-side tracking', 'gtm-kit' ),
		body: __(
			'Send purchases and page data from your server to GA4, Meta CAPI and Google Ads instead of depending on the browser. Webhooks fire from the order itself, with per-site measurement IDs for shared server containers.',
			'gtm-kit'
		),
		claim: __(
			'Safari caps the lifetime of any cookie set by JavaScript at 7 days, and at 24 hours when the visitor arrives from an ad click. Cookies set by your own server over HTTP are not capped.',
			'gtm-kit'
		),
		source: EVIDENCE.safariItp,
		cta: __( 'See how server-side tracking works', 'gtm-kit' ),
	},
	{
		id: 'purchase-accuracy',
		order: 20,
		link: 'cardPurchaseAccuracy',
		title: __( 'Purchase accuracy', 'gtm-kit' ),
		body: __(
			'A stable event ID lets GA4 deduplicate the same purchase arriving from both the browser and the server. Refunds and order-status changes are sent as their own events, and order attribution is surfaced where you can act on it.',
			'gtm-kit'
		),
		claim: __(
			'A payment gateway that redirects the customer away from your thank-you page never fires the browser-side purchase event. Sending it from both sides without deduplication counts the revenue twice.',
			'gtm-kit'
		),
		source: EVIDENCE.duplicateGa4,
		cta: __( 'See how purchase accuracy works', 'gtm-kit' ),
	},
	{
		id: 'consent',
		order: 30,
		link: 'cardConsent',
		title: __( 'Consent-safe measurement', 'gtm-kit' ),
		body: __(
			'Events that occur before the visitor answers the banner are queued and replayed once consent is granted, rather than discarded. Webhooks are consent-gated server-side, and the WP Consent API is wired up for you.',
			'gtm-kit'
		),
		claim: __(
			'Google Consent Mode requires the consent default to run before the container loads. When it does not, tags can initialise and fire with no consent instruction at all.',
			'gtm-kit'
		),
		source: EVIDENCE.consentOrdering,
		cta: __( 'See how consent-safe measurement works', 'gtm-kit' ),
	},
	{
		id: 'forms',
		order: 40,
		link: 'cardForms',
		title: __( 'Forms and subscriptions', 'gtm-kit' ),
		body: __(
			'Gravity Forms submissions and WooCommerce Subscriptions renewals become tracked conversions, so lead generation and recurring revenue land in GA4 next to one-off orders.',
			'gtm-kit'
		),
		claim: __(
			'A subscription renewal is charged in the background and never touches the checkout page, so browser-side tracking never sees it.',
			'gtm-kit'
		),
		source: EVIDENCE.subscriptionRenewals,
		cta: __( 'See form and subscription tracking', 'gtm-kit' ),
	},
	{
		id: 'debug',
		order: 50,
		link: 'cardDebug',
		title: __( 'Debug and QA', 'gtm-kit' ),
		body: __(
			'Event Inspector records what GTM Kit actually pushed on a given request, and sGTM Preview test send fires a sample event straight into your server container.',
			'gtm-kit'
		),
		claim: __(
			'Verify a tracking change without placing a real order and without waiting for GA4 to process the data.',
			'gtm-kit'
		),
		source: EVIDENCE.testWithoutOrders,
		cta: __( 'See the debugging tools', 'gtm-kit' ),
	},
	{
		id: 'calculator',
		order: 60,
		link: 'cardCalculator',
		title: __( 'What is broken tracking costing you?', 'gtm-kit' ),
		body: __(
			'Put your own order volume and traffic mix into the calculator and see what the gaps are worth on your site, before you decide anything.',
			'gtm-kit'
		),
		cta: __( 'Run the numbers', 'gtm-kit' ),
	},
];

// How far a card moves up its default position when the site's setup makes it
// more relevant. Commerce outranks forms, so its boost is the larger one.
const COMMERCE_BOOST = 100;
const FORMS_BOOST = 25;

// Cards that lead when the site runs a shop.
const COMMERCE_CARDS = [ 'server-side', 'purchase-accuracy' ];

// Plugin keys in the settings payload that count as a supported form plugin.
const FORM_PLUGINS = [ 'cf7', 'gravityforms' ];

/**
 * Whether the Premium surfaces should be shown for an access tier.
 *
 * Only free-only installs see Premium marketing. A Woo Add-On customer already
 * pays for an add-on, and a Premium customer already owns what is being
 * described, so both tiers suppress the surface entirely.
 *
 * @param {string} activeTier The resolved active tier.
 * @return {boolean} True when the surfaces should render.
 */
export const isPremiumSurfaceVisibleForTier = ( activeTier ) =>
	activeTier === TIERS.FREE;

/**
 * Whether the Premium surfaces should be shown on this install.
 *
 * @return {boolean} True when the surfaces should render.
 */
export const isPremiumSurfaceVisible = () =>
	isPremiumSurfaceVisibleForTier( SettingsService.getActiveTier() );

/**
 * Order the cards for a site's setup.
 *
 * Commerce cards lead on a shop; the forms card moves up when a supported form
 * plugin is active. Everything else keeps its default position, and the
 * calculator card carries no boost so it stays last.
 *
 * @param {Object}  context          The site context.
 * @param {boolean} context.hasWoo   Whether WooCommerce is active.
 * @param {boolean} context.hasForms Whether a supported form plugin is active.
 * @param {Array}   [context.cards]  The cards to order. Defaults to PREMIUM_CARDS.
 * @return {Array} The ordered cards.
 */
export const orderPremiumCards = ( {
	hasWoo = false,
	hasForms = false,
	cards = PREMIUM_CARDS,
} = {} ) =>
	[ ...cards ]
		.map( ( card ) => {
			let weight = card.order;
			if ( hasWoo && COMMERCE_CARDS.includes( card.id ) ) {
				weight -= COMMERCE_BOOST;
			}
			if ( hasForms && card.id === 'forms' ) {
				weight -= FORMS_BOOST;
			}
			return { card, weight };
		} )
		.sort( ( a, b ) => a.weight - b.weight )
		.map( ( entry ) => entry.card );

/**
 * The card order for this install, read from the settings payload.
 *
 * @return {Array} The ordered cards.
 */
export const getPremiumCards = () =>
	orderPremiumCards( {
		hasWoo: SettingsService.isPluginActive( 'woocommerce' ),
		hasForms: FORM_PLUGINS.some( ( slug ) =>
			SettingsService.isPluginActive( slug )
		),
	} );
