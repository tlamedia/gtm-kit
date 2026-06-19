/**
 * Events & data layer capability non-field content.
 */
import { __ } from '@wordpress/i18n';

export const EVENTS_CONTENT = {
	'events/post-data': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'Specify which post data elements you wish to include in the dataLayer for use in Google Tag Manager.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'events/user-data': {
		inline: [
			{
				type: 'callout',
				order: 5,
				variant: 'warning',
				heading: __( 'Warning!', 'gtm-kit' ),
				paragraphs: [
					{
						text: __(
							'Including user data is not compatible with full page caching.',
							'gtm-kit'
						),
					},
					{
						text: __(
							'Full page caching will cache user data making it the same for all users. There are ways around this, but it depends on the chosen cache solution and is only for advanced users.',
							'gtm-kit'
						),
					},
				],
			},
		],
	},

	'events/engagement': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'Fire GA4 standard engagement events for logins, sign-ups, on-site search, and Contact Form 7 lead submissions. Each event can be disabled independently if your GTM container or GA4 setup already covers it.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'events/cf7': {
		inline: [
			{
				type: 'component',
				order: 1,
				component: 'plugin-inactive',
				props: {
					pluginName: 'Contact Form 7',
					pluginSlug: 'cf7',
				},
			},
		],
	},
};

export default EVENTS_CONTENT;
