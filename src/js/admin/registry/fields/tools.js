/**
 * Tools capability field schema.
 *
 * Free controls from the current Misc page: automatic updates, logging, and
 * the share-anonymous-data opt-in. The premium Event Inspector is intentionally
 * deferred to runtime registration.
 */
import { __ } from '@wordpress/i18n';

export const TOOLS_FIELDS = [
	// Automatic Updates.
	{
		key: 'misc.auto_update',
		capability: 'tools',
		section: 'updates',
		order: 10,
		control: 'toggle',
		label: __( 'Enable Automatic Updates', 'gtm-kit' ),
		description: __(
			'Automatically update the GTM Kit plugin when new releases are available.',
			'gtm-kit'
		),
		notificationId: 'gtmkit-auto-update',
		tier: 'free',
		integration: null,
	},

	// Logging and debugging.
	{
		key: 'general.console_log',
		capability: 'tools',
		section: 'logging',
		order: 10,
		control: 'toggle',
		label: __( 'Console log', 'gtm-kit' ),
		description: __(
			'Log helpful messages and warnings to the browser log.',
			'gtm-kit'
		),
		notificationId: 'gtmkit-log-active',
		tier: 'free',
		integration: null,
	},
	{
		key: 'general.debug_log',
		capability: 'tools',
		section: 'logging',
		order: 20,
		control: 'toggle',
		label: __( 'Debug log', 'gtm-kit' ),
		description: __(
			'Log the purchase event and the server-side webhooks GTM Kit sends to your tagging server.',
			'gtm-kit'
		),
		notificationId: 'gtmkit-log-active',
		tier: 'free',
		integration: null,
	},

	// Help improve GTM Kit.
	{
		key: 'general.analytics_active',
		capability: 'tools',
		section: 'telemetry',
		order: 10,
		control: 'toggle',
		label: __( 'Share anonymous data', 'gtm-kit' ),
		description: __(
			'I agree to share anonymous data with the development team to help improve GTM Kit.',
			'gtm-kit'
		),
		tier: 'free',
		integration: null,
	},
];

export default TOOLS_FIELDS;
