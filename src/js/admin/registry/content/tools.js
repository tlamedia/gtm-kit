/**
 * Tools capability non-field content.
 */
import { __ } from '@wordpress/i18n';

export const TOOLS_CONTENT = {
	'tools/updates': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'New releases of GTM Kit may contain important updates to comply with changes in Google Tag Manager or analytics in general. We recommend enabling automatic plugin updates for GTM Kit to ensure it is always up to date.',
									'gtm-kit'
								),
							},
							{
								text: __(
									'You can, of course, manually update GTM Kit whenever it suits you, but we highly recommend that you regularly update your plugins and themes to the latest versions to keep your site secure.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'tools/telemetry': {
		inline: [
			{
				type: 'component',
				order: 1,
				component: 'share-anonymous-data',
			},
			{
				type: 'prose',
				order: 2,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									"GTM Kit will never transmit any domain names or container ID's.",
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},
};

export default TOOLS_CONTENT;
