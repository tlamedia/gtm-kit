/**
 * Dashboard data: the status metrics and the "Needs attention" list shown on
 * the home page. The metrics summarise the live settings; the attention list is
 * the plugin's own notification feed (the same one that drives the admin menu
 * badge), parsed into clean rows.
 */
/* global DOMParser */
import { __, sprintf } from '@wordpress/i18n';

import { safeHref } from '../utils/safeUrl';
import SettingsService from '../services/SettingsService';

const on = ( value ) => value === true || value === 1 || value === '1';

/**
 * The headline status metric cards, derived from the live settings.
 *
 * @param {Object} settings The live settings store.
 * @return {Array<Object>} Metric descriptors ({ label, value, subtitle, badge }).
 */
export const getDashboardMetrics = ( settings ) => {
	const g = settings?.general || {};
	const region = Array.isArray( g.gcm_region ) ? g.gcm_region : [];
	const consentOn = on( g.gcm_default_settings );

	// A site WordPress reports as staging, development or local gets no
	// container unless it asked for one, so the card must not claim otherwise
	// while the notice beside it explains the opposite.
	const siteKind = SettingsService.getSiteEnvironment();
	const withheld =
		! siteKind.isProduction && ! on( g.load_on_non_production );

	return [
		{
			label: __( 'Container', 'gtm-kit' ),
			value: g.gtm_id || __( 'Not set', 'gtm-kit' ),
			badge:
				g.gtm_id && on( g.container_active ) && ! withheld
					? 'active'
					: 'off',
			subtitle: withheld
				? sprintf(
						/* translators: %s: the kind of site WordPress reports, for example "staging". */
						__( 'Not loaded while this site is %s', 'gtm-kit' ),
						siteKind.type
				  )
				: __( 'Injected on all pages', 'gtm-kit' ),
		},
		{
			label: __( 'Consent Mode v2', 'gtm-kit' ),
			value: consentOn ? __( 'On', 'gtm-kit' ) : __( 'Off', 'gtm-kit' ),
			badge: consentOn ? 'active' : 'off',
			subtitle: region.length
				? sprintf(
						/* translators: %s: comma-separated region codes. */
						__( 'Region: %s', 'gtm-kit' ),
						region.join( ', ' )
				  )
				: __( 'All regions', 'gtm-kit' ),
		},
		{
			label: __( 'Server-side', 'gtm-kit' ),
			value: g.sgtm_domain
				? __( 'On', 'gtm-kit' )
				: __( 'Off', 'gtm-kit' ),
			badge: 'premium',
			subtitle: __( 'Webhook queue available', 'gtm-kit' ),
		},
	];
};

/**
 * Parse a raw notification ({ id, header, message }) into a clean row. Every
 * link the message carries becomes one of the row's actions; the remaining text
 * becomes the description. A message with more than one destination therefore
 * keeps all of them clickable, rather than silently flattening the extras into
 * unclickable words.
 *
 * @param {Object} raw      Raw notification from the bridge.
 * @param {string} severity Row severity ('error' or 'warning').
 * @return {Object} A row ({ id, severity, title, description, actions }).
 */
const parseNotification = ( raw, severity ) => {
	const doc = new DOMParser().parseFromString(
		`<div>${ raw.message || '' }</div>`,
		'text/html'
	);

	const actions = [ ...doc.querySelectorAll( 'a' ) ]
		.map( ( anchor ) => {
			const action = {
				label: anchor.textContent.trim(),
				href: safeHref( anchor.getAttribute( 'href' ) ),
			};
			anchor.remove();
			return action;
		} )
		.filter( ( action ) => action.label && action.href );

	return {
		id: raw.id,
		severity,
		title: ( raw.header || '' ).replace( /:\s*$/, '' ).trim(),
		description: ( doc.body.textContent || '' )
			.replace( /\s+/g, ' ' )
			.trim(),
		actions,
	};
};

/**
 * The "Needs attention" rows: the plugin's active notifications, problems
 * first, each parsed into a title, description and action.
 *
 * @param {Object} notifications The notifications store (problem/notice groups).
 * @return {Array<Object>} Attention rows.
 */
export const getDashboardNotifications = ( notifications ) => {
	const problems = notifications?.problem?.active || [];
	const notices = notifications?.notice?.active || [];

	return [
		...problems.map( ( raw ) => parseNotification( raw, 'error' ) ),
		...notices.map( ( raw ) => parseNotification( raw, 'warning' ) ),
	];
};

/**
 * The dismissed notifications, parsed like the active ones, so the dashboard can
 * list them under a "show dismissed" toggle with a restore control.
 *
 * @param {Object} notifications The plugin's notification feed.
 * @return {Array<Object>} Parsed dismissed rows.
 */
export const getDashboardDismissed = ( notifications ) => {
	const problems = notifications?.problem?.dismissed || [];
	const notices = notifications?.notice?.dismissed || [];

	return [
		...problems.map( ( raw ) => parseNotification( raw, 'error' ) ),
		...notices.map( ( raw ) => parseNotification( raw, 'warning' ) ),
	];
};
