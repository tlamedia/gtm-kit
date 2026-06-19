/**
 * Consent & privacy capability non-field content.
 */
import { __ } from '@wordpress/i18n';

export const CONSENT_CONTENT = {
	'consent/gating': {
		inline: [
			{
				type: 'component',
				order: 1,
				component: 'consent-badges',
			},
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'Choose whether GTM Kit loads the Google Tag Manager container unconditionally, lets it load but hands consent to Consent Mode v2, or holds the container script back until consent is granted.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
			{
				type: 'callout',
				order: 20,
				variant: 'info',
				visibleWhen: {
					'general.consent_gating_mode': 'strong_block',
					falsy: [ 'general.gcm_default_settings' ],
				},
				paragraphs: [
					{
						text: __(
							'Strong-block mode requires a consent signal to load GTM. With the Consent Mode master toggle off, GTM Kit emits no consent code itself; your CMP or custom code must fire the gtmkit:consent:updated event when consent is granted, otherwise GTM will not load.',
							'gtm-kit'
						),
					},
				],
			},
		],
	},

	'consent/cmp': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'If your consent platform blocks scripts via HTML attributes, enable the matching option below. GTM Kit will add the attribute to its inline scripts so your CMP recognizes them.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'consent/activation': {
		inline: [
			{
				type: 'callout',
				order: 5,
				variant: 'warning',
				heading: __( 'Warning!', 'gtm-kit' ),
				paragraphs: [
					{
						text: __(
							"Many consent management platforms (Cookiebot, Complianz, CookieYes, Cookie Information, etc.) handle Google Consent Mode themselves — either via their own client script or via tags inside your GTM container. If your CMP already does this, leave this setting off. Turning it on alongside a CMP that also emits gtag('consent', 'default', …) will cause double-firing and unpredictable consent state.",
							'gtm-kit'
						),
					},
					{
						text: __(
							'Only turn this on if you have no CMP or if your CMP explicitly does not implement Consent Mode itself.',
							'gtm-kit'
						),
					},
					{
						text: __(
							'GTM Kit will only set the default Consent Mode settings and you must update the settings yourself when the user has given consent. When this setting is on, GTM Kit also exposes window.gtmkit.consent.update() and the gtmkit:consent:updated window event for partner scripts.',
							'gtm-kit'
						),
						link: {
							label: __(
								'See an example of how consent is updated',
								'gtm-kit'
							),
							href: 'https://developers.google.com/tag-platform/security/guides/consent#implementation_example',
						},
					},
				],
			},
		],
	},

	'consent/defaults': {
		inline: [
			{
				type: 'prose',
				order: 80,
				nodes: [ { heading: __( 'Advanced', 'gtm-kit' ) } ],
			},
		],
	},
};

export default CONSENT_CONTENT;
