/**
 * Map of legacy hash routes to their new capability routes.
 *
 * The bespoke pages used per-feature routes (`#/container`, `#/post-data`, …).
 * The capability-axis shell consolidates these, so old bookmarks and external
 * deep links are redirected to the route that now hosts that content. The query
 * string (e.g. `?focus=sgtm`) is preserved by the redirect component, so anchor
 * deep links keep working where the target section id is unchanged.
 */
export const LEGACY_ROUTES = {
	general: 'dashboard',
	container: 'setup',
	'post-data': 'events',
	'user-data': 'events',
	'engagement-events': 'events',
	cf7: 'events',
	'google-consent-mode': 'consent',
	misc: 'tools',
	templates: 'gtm-templates',
	woocommerce: 'commerce',
	edd: 'commerce',
	integrations: 'integrations',
	notifications: 'dashboard',
	upgrades: 'license',
	support: 'support',
	help: 'support',
};

export default LEGACY_ROUTES;
