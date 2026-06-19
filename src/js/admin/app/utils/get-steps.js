/*WordPress*/
import { __ } from '@wordpress/i18n';

export const getSteps = [
	{
		step: 1,
		path: '/welcome',
		element: 'Welcome',
		title: __( 'Welcome', 'gtm-kit' ),
	},
	{
		step: 2,
		path: '/essential-settings',
		element: 'EssentialSettings',
		title: __( 'Essential Settings', 'gtm-kit' ),
	},
	{
		step: 3,
		path: '/share-anonymous-data',
		element: 'ShareAnonymousData',
		title: __( 'Help improve GTM Kit', 'gtm-kit' ),
	},
	{
		step: 4,
		path: '/automatic-updates',
		element: 'Automatic Updates',
		title: __( 'Automatic Updates', 'gtm-kit' ),
	},
	{
		step: 5,
		path: '/getting-started',
		element: 'GettingStarted',
		title: __( 'Getting Started', 'gtm-kit' ),
	},
];
