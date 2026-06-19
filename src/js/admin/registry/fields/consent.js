/**
 * Consent & privacy capability field schema.
 *
 * Mirrors the free controls of the current Google Consent Mode page: script
 * gating, CMP script attributes, the activation master toggle, and the default
 * Consent Mode settings (gated on the master toggle).
 */
import { __ } from '@wordpress/i18n';

const GCM_ENABLED = { truthy: [ 'general.gcm_default_settings' ] };

const gcmToggle = ( key, order, label, description ) => ( {
	key: `general.${ key }`,
	capability: 'consent',
	section: 'defaults',
	order,
	control: 'toggle',
	label,
	description,
	tier: 'free',
	integration: null,
	enabledWhen: GCM_ENABLED,
} );

export const CONSENT_FIELDS = [
	// Script gating.
	{
		key: 'general.consent_gating_mode',
		capability: 'consent',
		section: 'gating',
		order: 10,
		control: 'select',
		label: __( 'Gating mode', 'gtm-kit' ),
		valueType: 'string',
		defaultValue: 'always_load',
		options: [
			{
				label: __( 'Always load (default)', 'gtm-kit' ),
				value: 'always_load',
			},
			{
				label: __( 'Weak block (Consent Mode)', 'gtm-kit' ),
				value: 'weak_block',
			},
			{ label: __( 'Strong block', 'gtm-kit' ), value: 'strong_block' },
		],
		tier: 'free',
		integration: null,
	},

	// CMP script attributes.
	{
		key: 'general.cmp_script_attributes',
		capability: 'consent',
		section: 'cmp',
		order: 10,
		control: 'cmp-attributes',
		label: __( 'CMP script attributes', 'gtm-kit' ),
		tier: 'free',
		integration: null,
	},

	// Activation master toggle.
	{
		key: 'general.gcm_default_settings',
		capability: 'consent',
		section: 'activation',
		order: 10,
		control: 'toggle',
		label: __( 'Activate GCM settings', 'gtm-kit' ),
		description: __(
			'Choose this option if you would like to activate the default settings below',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},

	// Default settings (gated on the master toggle).
	gcmToggle(
		'gcm_ad_personalization',
		10,
		__( 'Ad Personalization', 'gtm-kit' ),
		__( 'Enables personalized advertising', 'gtm-kit' )
	),
	gcmToggle(
		'gcm_ad_storage',
		20,
		__( 'Ad Storage', 'gtm-kit' ),
		__(
			'Enables storage, such as cookies, related to advertising',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_ad_user_data',
		30,
		__( 'Ad User Data', 'gtm-kit' ),
		__(
			'Enables sending user data related to advertising to Google',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_analytics_storage',
		40,
		__( 'Analytics Storage', 'gtm-kit' ),
		__(
			'Enables storage, such as cookies, related to analytics (for example, visit duration)',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_functionality_storage',
		50,
		__( 'Functionality Storage', 'gtm-kit' ),
		__(
			'Enables storage that supports the functionality of the website or app such as language settings',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_personalization_storage',
		60,
		__( 'Personalization Storage', 'gtm-kit' ),
		__(
			'Enables storage related to personalization such as video recommendations',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_security_storage',
		70,
		__( 'Security Storage', 'gtm-kit' ),
		__(
			'Enables storage related to security such as authentication functionality, fraud prevention, and other user protection',
			'gtm-kit'
		)
	),
	gcmToggle(
		'gcm_ads_data_redaction',
		90,
		__( 'Redact Ads Data', 'gtm-kit' ),
		__( 'Redact advertising data', 'gtm-kit' )
	),
	gcmToggle(
		'gcm_url_passthrough',
		100,
		__( 'Pass through URL parameters', 'gtm-kit' ),
		__(
			'Pass through ad click, client ID, and session ID information in URLs',
			'gtm-kit'
		)
	),
	{
		key: 'general.gcm_wait_for_update',
		capability: 'consent',
		section: 'defaults',
		order: 110,
		control: 'number',
		label: __( 'Wait For Update', 'gtm-kit' ),
		placeholder: '500',
		min: 0,
		max: 30000,
		step: 100,
		help: __(
			"Milliseconds to wait for a CMP to call gtag('consent', 'update', …) before tags fire with default state. 500 ms is a safe baseline. Set to 0 to disable the wait.",
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
		enabledWhen: GCM_ENABLED,
	},
	{
		key: 'general.gcm_region',
		capability: 'consent',
		section: 'defaults',
		order: 120,
		control: 'region-codes',
		label: __( 'Region', 'gtm-kit' ),
		placeholder: __( 'e.g. DK, DE, US-CA', 'gtm-kit' ),
		help: __(
			'Limit defaults to specific countries or regions (ISO codes, e.g. DK, DE-BY, US-CA). Leave empty to apply globally. Separate multiple codes with commas.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
		enabledWhen: GCM_ENABLED,
	},
];

export default CONSENT_FIELDS;
