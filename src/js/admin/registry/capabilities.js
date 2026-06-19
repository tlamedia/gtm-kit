/**
 * Capability and section definitions for the registry-driven shell.
 *
 * Capabilities are the top-level nav destinations, split into two zones:
 * tracking capabilities above the divider and plugin/meta pages below it.
 * Sections are the cards within a capability page. Both are pure data; the
 * nav and the page renderer read from the same source.
 */
import { __ } from '@wordpress/i18n';
import { getRegisteredSections } from './bridge';

export const ZONES = {
	CAPABILITY: 'capability',
	PLUGIN: 'plugin',
};

export const LAYOUTS = {
	SINGLE: 'single',
	TWO_COLUMN: 'two-column',
};

/**
 * Top-level nav destinations. `order` controls position within a zone;
 * `home` marks the overview item rendered above the capability list.
 */
export const CAPABILITIES = [
	{
		id: 'dashboard',
		label: __( 'Dashboard', 'gtm-kit' ),
		order: 0,
		zone: ZONES.CAPABILITY,
		home: true,
	},
	{
		id: 'setup',
		label: __( 'Setup', 'gtm-kit' ),
		description: __( 'Container, delivery and environment', 'gtm-kit' ),
		order: 10,
		zone: ZONES.CAPABILITY,
		context: {
			about: {
				text: __(
					'Setup controls how the GTM container loads, the data layer name, server-side tagging, and which pages and roles are excluded from tracking.',
					'gtm-kit'
				),
				link: {
					label: __( 'Read the setup guide', 'gtm-kit' ),
					href: 'https://gtmkit.com/documentation/',
				},
			},
		},
	},
	{
		id: 'gtm-templates',
		label: __( 'GTM Templates', 'gtm-kit' ),
		order: 15,
		zone: ZONES.CAPABILITY,
		flow: true,
	},
	{
		id: 'events',
		label: __( 'Events & data layer', 'gtm-kit' ),
		description: __(
			'Post data, user data and engagement events',
			'gtm-kit'
		),
		order: 20,
		zone: ZONES.CAPABILITY,
		context: {
			about: {
				text: __(
					'These settings control the page and user data GTM Kit adds to the data layer, plus the standard GA4 events it fires automatically, so your tags always have consistent variables to build on.',
					'gtm-kit'
				),
				link: {
					label: __( 'Read the events guide', 'gtm-kit' ),
					href: 'https://gtmkit.com/documentation/',
				},
			},
		},
	},
	{
		id: 'commerce',
		label: __( 'Commerce', 'gtm-kit' ),
		description: __(
			'WooCommerce and Easy Digital Downloads tracking',
			'gtm-kit'
		),
		order: 30,
		zone: ZONES.CAPABILITY,
		context: {
			about: {
				text: __(
					'Commerce tracking pushes view_item, add_to_cart, begin_checkout and purchase events to the data layer for WooCommerce and EDD, ready for GA4 and Google Ads.',
					'gtm-kit'
				),
				link: {
					label: __( 'Read the commerce docs', 'gtm-kit' ),
					href: 'https://gtmkit.com/documentation/',
				},
			},
		},
	},
	{
		id: 'consent',
		label: __( 'Consent & privacy', 'gtm-kit' ),
		description: __(
			'Consent Mode, script gating and CMP integration',
			'gtm-kit'
		),
		order: 40,
		zone: ZONES.CAPABILITY,
		context: {
			about: {
				title: __( 'You need a consent platform', 'gtm-kit' ),
				text: __(
					'These settings control the consent signals GTM Kit sends to Google Tag Manager. They do not ask visitors for consent. You need a Consent Management Platform (CMP) to collect and store consent choices.',
					'gtm-kit'
				),
				note: __(
					'GTM Kit wires up these signals but is not responsible for your privacy compliance. Configuring consent correctly for your region is your responsibility: consult your own legal guidance.',
					'gtm-kit'
				),
			},
		},
	},
	{
		id: 'tools',
		label: __( 'Tools', 'gtm-kit' ),
		description: __( 'Updates, logging and diagnostics', 'gtm-kit' ),
		order: 105,
		zone: ZONES.PLUGIN,
		context: {
			about: {
				text: __(
					'These tools help you keep GTM Kit healthy: control automatic updates, turn on logging when you need to debug, and choose whether to share anonymous usage data.',
					'gtm-kit'
				),
				link: {
					label: __( 'Read the troubleshooting guide', 'gtm-kit' ),
					href: 'https://gtmkit.com/documentation/',
				},
			},
		},
	},
	{
		id: 'integrations',
		label: __( 'Integrations', 'gtm-kit' ),
		order: 60,
		zone: ZONES.CAPABILITY,
	},
	{
		id: 'license',
		label: __( 'License', 'gtm-kit' ),
		order: 100,
		zone: ZONES.PLUGIN,
	},
	{
		id: 'support',
		label: __( 'Support', 'gtm-kit' ),
		order: 110,
		zone: ZONES.PLUGIN,
	},
];

/**
 * Section definitions, keyed implicitly by `${capability}/${id}`. The page
 * renderer pulls the sections for a capability, sorts by `order`, and renders
 * each as a card. Fields and content blocks attach to a section by id.
 */
export const SECTIONS = [
	// Setup.
	{
		capability: 'setup',
		id: 'container',
		label: __( 'General Container Settings', 'gtm-kit' ),
		order: 10,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'setup',
		id: 'exclude-pages',
		label: __( 'Exclude pages from GTM', 'gtm-kit' ),
		order: 20,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'setup',
		id: 'sgtm',
		label: __( 'Server-side Tagging (sGTM)', 'gtm-kit' ),
		order: 30,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'setup',
		id: 'page-speed',
		label: __( 'Page Speed Optimization', 'gtm-kit' ),
		order: 40,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'setup',
		id: 'environment',
		label: __( 'Google Tag Manager Environment', 'gtm-kit' ),
		order: 50,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'setup',
		id: 'exclude-roles',
		label: __( 'Exclude User Roles', 'gtm-kit' ),
		order: 60,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'setup',
		id: 'implementation',
		label: __( 'Container Code Implementation', 'gtm-kit' ),
		order: 70,
		layout: LAYOUTS.SINGLE,
	},

	// Events & data layer.
	{
		capability: 'events',
		id: 'post-data',
		label: __( 'Post Data Settings', 'gtm-kit' ),
		order: 10,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'events',
		id: 'user-data',
		label: __( 'User Data Settings', 'gtm-kit' ),
		order: 20,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'events',
		id: 'engagement',
		label: __( 'GA4 standard events', 'gtm-kit' ),
		order: 30,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'events',
		id: 'cf7',
		label: __( 'Contact Form 7', 'gtm-kit' ),
		order: 40,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
	},
	{
		capability: 'events',
		id: 'woo-page-data',
		label: __( 'WooCommerce: Page data', 'gtm-kit' ),
		order: 50,
		layout: LAYOUTS.SINGLE,
	},

	// Consent & privacy.
	{
		capability: 'consent',
		id: 'gating',
		label: __( 'Script gating', 'gtm-kit' ),
		order: 10,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'consent',
		id: 'cmp',
		label: __( 'CMP script attributes', 'gtm-kit' ),
		order: 20,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'consent',
		id: 'activation',
		label: __( 'Google Consent Mode Activation', 'gtm-kit' ),
		order: 30,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'consent',
		id: 'defaults',
		label: __( 'Google Consent Mode Default Settings', 'gtm-kit' ),
		order: 40,
		layout: LAYOUTS.SINGLE,
		disabledWhen: { falsy: [ 'general.gcm_default_settings' ] },
	},
	{
		capability: 'consent',
		id: 'event-deferral',
		label: __( 'Event deferral', 'gtm-kit' ),
		order: 5,
		layout: LAYOUTS.SINGLE,
	},

	// Tools.
	{
		capability: 'tools',
		id: 'logging',
		label: __( 'Logging and debugging', 'gtm-kit' ),
		order: 10,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'tools',
		id: 'updates',
		label: __( 'Automatic Updates', 'gtm-kit' ),
		order: 20,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'tools',
		id: 'telemetry',
		label: __( 'Help improve GTM Kit', 'gtm-kit' ),
		order: 30,
		layout: LAYOUTS.SINGLE,
	},

	// Commerce.
	{
		capability: 'commerce',
		id: 'woocommerce',
		label: __( 'WooCommerce', 'gtm-kit' ),
		order: 10,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
	},
	{
		capability: 'commerce',
		id: 'woo-basic',
		label: __( 'WooCommerce: Basic Settings', 'gtm-kit' ),
		order: 20,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'woo-user-data',
		label: __( 'WooCommerce: User Data', 'gtm-kit' ),
		order: 47,
		layout: LAYOUTS.TWO_COLUMN,
	},
	{
		capability: 'commerce',
		id: 'woo-events',
		label: __( 'WooCommerce: Event Customization', 'gtm-kit' ),
		order: 40,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'woo-webhooks',
		label: __(
			'WooCommerce: Webhooks for server-side tracking',
			'gtm-kit'
		),
		order: 45,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'woo-google-ads',
		label: __( 'WooCommerce: Google Ads Settings', 'gtm-kit' ),
		order: 50,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'woo-advanced',
		label: __( 'WooCommerce: Advanced Settings', 'gtm-kit' ),
		order: 60,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
		hideOnCapability: true,
	},
	{
		capability: 'commerce',
		id: 'woo-css-selectors',
		label: __( 'WooCommerce: Custom CSS Selectors', 'gtm-kit' ),
		order: 65,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
	},
	{
		capability: 'commerce',
		id: 'edd',
		label: __( 'Easy Digital Downloads', 'gtm-kit' ),
		order: 70,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
	},
	{
		capability: 'commerce',
		id: 'edd-basic',
		label: __( 'Easy Digital Downloads: Basic Settings', 'gtm-kit' ),
		order: 80,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'edd-google-ads',
		label: __( 'Easy Digital Downloads: Google Ads Settings', 'gtm-kit' ),
		order: 90,
		layout: LAYOUTS.SINGLE,
	},
	{
		capability: 'commerce',
		id: 'edd-advanced',
		label: __( 'Easy Digital Downloads: Advanced Settings', 'gtm-kit' ),
		order: 100,
		layout: LAYOUTS.SINGLE,
		integrationConfig: true,
		hideOnCapability: true,
	},
];

/**
 * Capability lookup by id.
 *
 * @param {string} id Capability id.
 * @return {Object|undefined} The capability definition.
 */
export const getCapability = ( id ) =>
	CAPABILITIES.find( ( capability ) => capability.id === id );

/**
 * Capabilities in a zone, ordered.
 *
 * @param {string} zone One of ZONES.
 * @return {Array} Ordered capability definitions in the zone.
 */
export const getCapabilitiesByZone = ( zone ) =>
	CAPABILITIES.filter( ( capability ) => capability.zone === zone ).sort(
		( a, b ) => a.order - b.order
	);

/**
 * All sections, deduplicated by `${capability}/${id}` with last-wins
 * precedence, so an add-on may add a section or override a bundled one.
 *
 * @return {Array} The merged section list.
 */
const getAllSections = () => {
	const byId = new Map();
	[ ...SECTIONS, ...getRegisteredSections() ].forEach( ( section ) => {
		byId.set( `${ section.capability }/${ section.id }`, section );
	} );
	return [ ...byId.values() ];
};

/**
 * Sections for a capability, ordered.
 *
 * @param {string} capabilityId Capability id.
 * @return {Array} Ordered section definitions.
 */
export const getSections = ( capabilityId ) =>
	getAllSections()
		.filter( ( section ) => section.capability === capabilityId )
		.sort( ( a, b ) => a.order - b.order );
