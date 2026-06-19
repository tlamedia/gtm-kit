/**
 * Dashboard data: the status metrics and the "Needs attention" list shown on
 * the home page. The metrics summarise the live settings; the attention list is
 * the plugin's own notification feed (the same one that drives the admin menu
 * badge), parsed into clean rows.
 */
/* global DOMParser */
import { __, sprintf } from '@wordpress/i18n';

import { safeHref } from '../utils/safeUrl';

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

	return [
		{
			label: __( 'Container', 'gtm-kit' ),
			value: g.gtm_id || __( 'Not set', 'gtm-kit' ),
			badge: g.gtm_id && on( g.container_active ) ? 'active' : 'off',
			subtitle: __( 'Injected on all pages', 'gtm-kit' ),
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
 * Parse a raw notification ({ id, header, message }) into a clean row. The
 * message may carry an inline action link, which becomes the row's action; the
 * remaining text becomes the description.
 *
 * @param {Object} raw      Raw notification from the bridge.
 * @param {string} severity Row severity ('error' or 'warning').
 * @return {Object} A row ({ id, severity, title, description, action }).
 */
const parseNotification = ( raw, severity ) => {
	const doc = new DOMParser().parseFromString(
		`<div>${ raw.message || '' }</div>`,
		'text/html'
	);

	const anchor = doc.querySelector( 'a' );
	let action = null;
	if ( anchor ) {
		action = {
			label: anchor.textContent.trim(),
			href: safeHref( anchor.getAttribute( 'href' ) ),
		};
		anchor.remove();
	}

	return {
		id: raw.id,
		severity,
		title: ( raw.header || '' ).replace( /:\s*$/, '' ).trim(),
		description: ( doc.body.textContent || '' )
			.replace( /\s+/g, ' ' )
			.trim(),
		action,
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
