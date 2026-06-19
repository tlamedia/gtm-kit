/**
 * Setup capability non-field content.
 *
 * Neutral help (examples, syntax docs, warnings, environment notes) is core
 * content: declarative, bundled, translatable. The sGTM panel is bundled
 * marketing in free core. Content is keyed by `${capability}/${section}` and
 * split into `inline` blocks (interleaved with fields by `order` in the main
 * column) and `aside` blocks (right column when the section is two-column).
 */
import { __ } from '@wordpress/i18n';

export const SETUP_CONTENT = {
	'setup/container': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'To start collecting data with Google Tag manager you must register the Container ID of your Google Tag Manager container.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'setup/exclude-pages': {
		inline: [
			{
				type: 'prose',
				order: 5,
				nodes: [
					{
						paragraphs: [
							{
								text: __(
									'Add URL patterns to suppress GTM Kit on matching pages. The container, noscript iframe, and dataLayer scripts all stay off when the current request path matches one of the patterns below.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
		aside: [
			{
				type: 'examples',
				heading: __( 'Examples', 'gtm-kit' ),
				items: [
					{
						code: '/checkout-embed/*',
						text: __( 'third-party checkout', 'gtm-kit' ),
					},
					{
						code: '/partners/*',
						text: __( 'partner-hosted subpages', 'gtm-kit' ),
					},
					{
						code: '/app/webview/*',
						text: __( 'in-app webview routes', 'gtm-kit' ),
					},
				],
			},
			{
				type: 'prose',
				nodes: [
					{
						heading: __( 'Pattern syntax', 'gtm-kit' ),
						paragraphs: [
							{
								text: __(
									'Glob mode (default): * matches any run of characters including /; ? matches a single character; all other characters are matched literally.',
									'gtm-kit'
								),
							},
							{
								text: __(
									'Regex mode: enter a raw PCRE pattern without delimiters. The matcher adds ~ delimiters and the i flag. Invalid patterns are rejected on save.',
									'gtm-kit'
								),
							},
						],
					},
				],
			},
		],
	},

	'setup/sgtm': {
		aside: [
			{
				type: 'promo',
				nodes: [
					{
						heading: __( 'sGTM Hosting', 'gtm-kit' ),
						paragraphs: [
							{
								text: __(
									'Server-side tagging improves data accuracy, performance, privacy, and flexibility.',
									'gtm-kit'
								),
							},
							{
								text: __(
									'Stape.io offers an affordable and user-friendly hosting solution for server-side Google Tag Manager containers.',
									'gtm-kit'
								),
								link: {
									label: __(
										'Learn more about Stape.io',
										'gtm-kit'
									),
									href: 'https://jump.gtmkit.com/link/1-AC1E5',
								},
							},
						],
					},
				],
			},
		],
	},

	'setup/page-speed': {
		aside: [
			{
				type: 'prose',
				nodes: [
					{
						heading: __( 'Delay JavaScript execution', 'gtm-kit' ),
						paragraphs: [
							{
								text: __(
									"Page optimization plugins can delay the 'load_delayed_js' event and this can be used to delay the triggering og tags in Google Tag Manager.",
									'gtm-kit'
								),
								link: {
									label: __( 'Learn more', 'gtm-kit' ),
									href: 'https://gtmkit.com/guides/delay-javascript-execution-in-gtm/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=delay-js&utm_content=dashboard-container',
								},
							},
						],
					},
				],
			},
		],
	},

	'setup/environment': {
		aside: [
			{
				type: 'prose',
				nodes: [
					{
						heading: __( 'Environments', 'gtm-kit' ),
						paragraphs: [
							{
								text: __(
									'Use GTM environments such as Live, Dev, or QA by entering the environment’s gtm_auth and gtm_preview values. If left empty, the default environment is used.',
									'gtm-kit'
								),
							},
							{
								text: __(
									'These values can also be defined in wp-config.php',
									'gtm-kit'
								),
								link: {
									label: __( 'Learn more', 'gtm-kit' ),
									href: 'https://gtmkit.com/documentation/settings-actions-and-filters-for-developers/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=container-settings&utm_content=dashboard-container',
								},
							},
						],
					},
				],
			},
		],
	},

	'setup/exclude-roles': {
		aside: [
			{
				type: 'callout',
				variant: 'warning',
				heading: __( 'Warning!', 'gtm-kit' ),
				paragraphs: [
					{
						text: __(
							'Excluding user roles is not compatible with all full-page cache solutions. Some full-page cache solutions may cache the page identically for all users, regardless of their user role. This could result in users being excluded who should not be.',
							'gtm-kit'
						),
					},
					{
						text: __(
							'Please ensure thorough and proper testing of this.',
							'gtm-kit'
						),
					},
				],
			},
		],
	},

	'setup/implementation': {
		inline: [
			{
				type: 'prose',
				order: 30,
				nodes: [
					{
						code: "<?php if ( function_exists( 'gtmkit_the_noscript_tag' ) ) { gtmkit_the_noscript_tag(); } ?>",
					},
				],
			},
		],
	},
};

export default SETUP_CONTENT;
