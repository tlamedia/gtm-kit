/**
 * Events & data layer capability field schema.
 *
 * Combines the controls of the current Post data, User data, Engagement events,
 * and Contact Form 7 pages.
 */
import { __ } from '@wordpress/i18n';

const toggle = ( key, section, order, label, description, extra = {} ) => ( {
	key: `general.${ key }`,
	capability: 'events',
	section,
	order,
	control: 'toggle',
	label,
	description,
	tier: 'free',
	integration: null,
	...extra,
} );

export const EVENTS_FIELDS = [
	// Post data.
	toggle(
		'datalayer_post_type',
		'post-data',
		10,
		__( 'Post type', 'gtm-kit' ),
		__( 'Include the type of the current post or archive page.', 'gtm-kit' )
	),
	toggle(
		'datalayer_page_type',
		'post-data',
		20,
		__( 'Page type', 'gtm-kit' ),
		__(
			'Include the defined page type. I.e. post, page, product, category, cart, checkout etc. You may override this on page-level and set you own page type i.e. "campaign".',
			'gtm-kit'
		)
	),
	toggle(
		'datalayer_categories',
		'post-data',
		30,
		__( 'Categories', 'gtm-kit' ),
		__(
			'Include the categories of the current post or archive page.',
			'gtm-kit'
		)
	),
	toggle(
		'datalayer_tags',
		'post-data',
		40,
		__( 'Tags', 'gtm-kit' ),
		__( 'Include the tags of the current post or archive page.', 'gtm-kit' )
	),
	toggle(
		'datalayer_post_title',
		'post-data',
		50,
		__( 'Post title', 'gtm-kit' ),
		__( 'Include the post title of the current post.', 'gtm-kit' )
	),
	toggle(
		'datalayer_post_id',
		'post-data',
		60,
		__( 'Post ID', 'gtm-kit' ),
		__( 'Include the Post ID of the current post.', 'gtm-kit' )
	),
	toggle(
		'datalayer_post_date',
		'post-data',
		70,
		__( 'Post date', 'gtm-kit' ),
		__( 'Include the post date.', 'gtm-kit' )
	),
	toggle(
		'datalayer_post_author_name',
		'post-data',
		80,
		__( 'Post author name', 'gtm-kit' ),
		__( 'Include the post author name.', 'gtm-kit' )
	),
	toggle(
		'datalayer_post_author_id',
		'post-data',
		90,
		__( 'Post author ID', 'gtm-kit' ),
		__( 'Include the post author ID.', 'gtm-kit' )
	),

	// User data.
	toggle(
		'datalayer_logged_in',
		'user-data',
		10,
		__( 'Logged in', 'gtm-kit' ),
		__( 'Include whether the user is logged in.', 'gtm-kit' )
	),
	toggle(
		'datalayer_user_id',
		'user-data',
		20,
		__( 'User ID', 'gtm-kit' ),
		__( 'Include the user ID if the user is logged in.', 'gtm-kit' )
	),
	toggle(
		'datalayer_user_role',
		'user-data',
		30,
		__( 'User role', 'gtm-kit' ),
		__( 'Include the user role if the user is logged in.', 'gtm-kit' )
	),

	// Engagement events.
	toggle(
		'engagement_event_login_enabled',
		'engagement',
		10,
		__( 'Login', 'gtm-kit' ),
		__(
			'Fires `login` on successful WordPress and WooCommerce account logins.',
			'gtm-kit'
		)
	),
	toggle(
		'engagement_event_signup_enabled',
		'engagement',
		20,
		__( 'Sign-up', 'gtm-kit' ),
		__(
			'Fires `sign_up` on new account registrations, including accounts created during WooCommerce checkout.',
			'gtm-kit'
		)
	),
	toggle(
		'engagement_event_search_enabled',
		'engagement',
		30,
		__( 'Search', 'gtm-kit' ),
		__(
			'Fires `search` on WordPress and WooCommerce product search results pages.',
			'gtm-kit'
		)
	),
	toggle(
		'engagement_event_generate_lead_enabled',
		'engagement',
		40,
		__( 'Generate lead (CF7)', 'gtm-kit' ),
		__(
			'Fires `generate_lead` alongside the existing CF7 form event on successful Contact Form 7 submissions. Requires the Contact Form 7 integration to be enabled.',
			'gtm-kit'
		),
		{
			integration: 'cf7',
			requiresIntegration: { plugin: 'cf7', option: 'cf7_integration' },
		}
	),

	// Contact Form 7.
	{
		key: 'integrations.cf7_integration',
		capability: 'events',
		section: 'cf7',
		order: 5,
		control: 'toggle',
		label: __( 'Track Contact Form 7', 'gtm-kit' ),
		description: __( 'Enable the Contact Form 7 integration.', 'gtm-kit' ),
		tier: 'free',
		integration: 'cf7',
		requiresPlugin: 'cf7',
	},
	{
		key: 'integrations.cf7_load_js',
		capability: 'events',
		section: 'cf7',
		order: 10,
		control: 'select',
		label: __( 'Load JavaScript', 'gtm-kit' ),
		valueType: 'integer',
		options: [
			{
				label: __(
					'Only on pages where the Contact Form 7 script is registered (recommended).',
					'gtm-kit'
				),
				value: 1,
			},
			{
				label: __( 'On all pages', 'gtm-kit' ),
				value: 2,
			},
		],
		help: __( 'Where do you want load the JavaScript?', 'gtm-kit' ),
		defaultValue: 1,
		tier: 'free',
		integration: 'cf7',
		requiresIntegration: { plugin: 'cf7', option: 'cf7_integration' },
	},
];

export default EVENTS_FIELDS;
