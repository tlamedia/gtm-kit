/**
 * Commerce capability non-field content.
 */
import { __ } from '@wordpress/i18n';

export const COMMERCE_CONTENT = {
	'commerce/woocommerce': {
		inline: [
			{
				type: 'component',
				order: 5,
				component: 'plugin-inactive',
				props: {
					pluginName: 'WooCommerce',
					pluginSlug: 'woocommerce',
				},
			},
		],
	},

	'commerce/woo-user-data': {
		aside: [
			{
				type: 'prose',
				nodes: [
					{
						heading: __( 'User-Provided Data', 'gtm-kit' ),
						paragraphs: [
							{
								text: __(
									"The user data is available in the datalayer in 'ecommerce.customer' and a subset of the user data formatted for the 'User-Provided Data' variable is available in 'user-data'.",
									'gtm-kit'
								),
								link: {
									label: __( 'Learn more', 'gtm-kit' ),
									href: 'https://support.google.com/google-ads/answer/13262500?sjid=9465166023214753583-EU#Code_snippet',
								},
							},
						],
					},
				],
			},
		],
	},

	'commerce/edd': {
		inline: [
			{
				type: 'component',
				order: 5,
				component: 'plugin-inactive',
				props: {
					pluginName: 'Easy Digital Downloads',
					pluginSlug: 'edd',
				},
			},
		],
	},
};

export default COMMERCE_CONTENT;
