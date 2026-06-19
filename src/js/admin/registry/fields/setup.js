/**
 * Setup capability field schema.
 *
 * Mirrors the controls of the current Container page, expressed as data. Each
 * field's `key` is its storage identity (`group.key`) and is independent of
 * the capability/section it renders under.
 */
import { __ } from '@wordpress/i18n';

export const SETUP_FIELDS = [
	// Container.
	{
		key: 'general.gtm_id',
		capability: 'setup',
		section: 'container',
		order: 10,
		control: 'text',
		label: __( 'GTM Container ID:', 'gtm-kit' ),
		placeholder: __( 'Enter GTM Container ID', 'gtm-kit' ),
		tier: 'free',
		integration: null,
		transform: 'normalizeGtmId',
		notificationId: 'gtmkit-container-injection',
		help: 'gtm-id-help',
	},
	{
		key: 'general.container_active',
		capability: 'setup',
		section: 'container',
		order: 20,
		control: 'toggle',
		label: __( 'Inject Container Code', 'gtm-kit' ),
		description: __(
			'Setting this to Off will remove the Google Tag Manager container code but the data layer will remain.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
		notificationId: 'gtmkit-container-injection',
	},
	{
		key: 'general.just_the_container',
		capability: 'setup',
		section: 'container',
		order: 30,
		control: 'toggle',
		label: __( 'Just the container', 'gtm-kit' ),
		description: __(
			'Setting this to On will reduce the functionality to just the GTM container code. No additional data will be pushed to the datalayer regardless of any other settings.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.datalayer_name',
		capability: 'setup',
		section: 'container',
		order: 40,
		control: 'text',
		label: __( 'dataLayer variable name:', 'gtm-kit' ),
		placeholder: 'dataLayer',
		help: __(
			'The default name of the data layer object is dataLayer. If you prefer to use a different name for your data layer, you may do so.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},

	// Exclude pages from GTM.
	{
		key: 'general.excluded_url_patterns',
		capability: 'setup',
		section: 'exclude-pages',
		order: 10,
		control: 'excluded-url-patterns',
		label: '',
		help: __(
			'Matching is on the URL path only: query string is ignored, casing is ignored, and a single trailing slash is normalised. Empty list = GTM Kit loads on every page. On subdirectory installs, patterns must include the full path (e.g. /shop/checkout-embed/*).',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},

	// Server-side Tagging (sGTM).
	{
		key: 'general.sgtm_domain',
		capability: 'setup',
		section: 'sgtm',
		order: 10,
		control: 'text',
		label: __( 'sGTM Container Domain:', 'gtm-kit' ),
		placeholder: __( 'Enter domain', 'gtm-kit' ),
		help: __(
			'Enter your custom domain name if you are using a custom server side GTM container for tracking.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.sgtm_container_identifier',
		capability: 'setup',
		section: 'sgtm',
		order: 20,
		control: 'text',
		label: __( 'sGTM container identifier:', 'gtm-kit' ),
		placeholder: __( 'Enter loader name', 'gtm-kit' ),
		help: __( 'Only use if you are using a custom loader.', 'gtm-kit' ),
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.sgtm_cookie_keeper',
		capability: 'setup',
		section: 'sgtm',
		order: 30,
		control: 'toggle',
		label: __( 'Cookie Keeper (for Stape users only)', 'gtm-kit' ),
		description: __(
			'Prolong cookie lifetime in Safari and other browsers with ITP. This only works if you use Stape sGTM hosting and have set up the Cookie Keeper power up.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
		enabledWhen: {
			truthy: [
				'general.sgtm_domain',
				'general.sgtm_container_identifier',
			],
		},
	},

	// Page Speed Optimization.
	{
		key: 'general.load_js_event',
		capability: 'setup',
		section: 'page-speed',
		order: 10,
		control: 'toggle',
		label: __( 'load_delayed_js event', 'gtm-kit' ),
		description: __(
			"Setting this to On will push the event 'load_delayed_js' on page load.",
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},

	// GTM Environment.
	{
		key: 'general.gtm_auth',
		capability: 'setup',
		section: 'environment',
		order: 10,
		control: 'text',
		label: __( 'gtm_auth:', 'gtm-kit' ),
		placeholder: __( 'Enter gtm_auth code', 'gtm-kit' ),
		help: __(
			'Enter the gtm_auth code for your GTM environment.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.gtm_preview',
		capability: 'setup',
		section: 'environment',
		order: 20,
		control: 'text',
		label: __( 'gtm_preview:', 'gtm-kit' ),
		placeholder: __( 'Enter gtm_preview code', 'gtm-kit' ),
		help: __(
			'Enter the gtm_preview code for your GTM environment.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},

	// Exclude User Roles.
	{
		key: 'general.exclude_user_roles',
		capability: 'setup',
		section: 'exclude-roles',
		order: 10,
		control: 'checkbox-group',
		label: __( 'Exclude user roles', 'gtm-kit' ),
		help: __(
			'Select the roles that you want to exclude from tracking.',
			'gtm-kit'
		),
		itemsSource: 'userRoles',
		tier: 'free',
		integration: null,
	},

	// Container Code Implementation.
	{
		key: 'general.script_implementation',
		capability: 'setup',
		section: 'implementation',
		order: 10,
		control: 'radio',
		label: __( 'Container code implementation:', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					'Standard implementation as recommended by Google (no delay)',
					'gtm-kit'
				),
				value: 0,
			},
			{
				label: __(
					'Load container when the browser is idle (requestIdleCallback)',
					'gtm-kit'
				),
				value: 1,
			},
		],
		help: __(
			'Depending on how you use Google Tag Manager you can delay the loading of the container script until the browser is idle.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.noscript_implementation',
		capability: 'setup',
		section: 'implementation',
		order: 20,
		control: 'radio',
		label: __( 'Container code noscript implementation:', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __( 'Just after the opening <body> tag', 'gtm-kit' ),
				value: 0,
			},
			{
				label: __(
					'Footer of the page (not recommended by Google)',
					'gtm-kit'
				),
				value: 1,
			},
			{
				label: __(
					'Custom (insert function in your template)',
					'gtm-kit'
				),
				value: 2,
			},
			{
				label: __( 'Disable <noscript> implementation', 'gtm-kit' ),
				value: 3,
			},
		],
		help: __(
			'The preferred method to implement the <noscript> container code is just after the opening <body> tag. This requires that your theme uses the "body_open" hook. If your theme does not support this the script can be injected in the footer or you can use the function below.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
];

export default SETUP_FIELDS;
