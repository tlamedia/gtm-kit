/**
 * Outbound links for the Premium overview page and the setup-wizard Premium
 * step.
 *
 * Two kinds of link, deliberately kept apart:
 *
 * 1. **Calls to action** go through jump.gtmkit.com short links, one per
 *    surface, so each is individually attributable and can be retargeted
 *    without shipping a plugin release. Components read them from here rather
 *    than hardcoding a URL.
 * 2. **Evidence links** on a claim are direct documentation URLs. They are
 *    references rather than conversion targets and are not part of the
 *    attribution model, so they are not shortened.
 *
 * Each surface also carries a fallback target, used only if its short-link
 * code is ever missing, so a call to action can never render a dead link.
 */

const SHORT_LINK_BASE = 'https://jump.gtmkit.com/link/';

/**
 * Where a call to action lands if its short link is ever missing.
 *
 * Pricing suits every surface as a fallback: it is the page a reader who
 * wanted any of these topics can act from.
 */
const FALLBACK_URL = 'https://gtmkit.com/pricing/';

/**
 * Call-to-action surfaces and their short-link codes.
 *
 * `target` is only a safety net for a surface whose code is missing. It is not
 * where the link goes: a minted code always wins, and its destination is set
 * on the short link itself, so a call to action can be repointed without a
 * plugin release. The calculator card, for example, keeps its code and is
 * retargeted to the business-case calculator when that page ships.
 */
export const CTA_SURFACES = {
	cardServerSide: { code: '8-01F58', target: FALLBACK_URL },
	cardPurchaseAccuracy: { code: '9-10C87', target: FALLBACK_URL },
	cardConsent: { code: '10-B66ED', target: FALLBACK_URL },
	cardForms: { code: '11-010E5', target: FALLBACK_URL },
	cardDebug: { code: '12-7E262', target: FALLBACK_URL },
	cardCalculator: { code: '13-FDA99', target: FALLBACK_URL },
	pagePricing: { code: '14-1C82F', target: FALLBACK_URL },
	wizardWoo: { code: '15-D352B', target: FALLBACK_URL },
	wizardConsent: { code: '16-8C659', target: FALLBACK_URL },
	wizardGeneric: { code: '17-6ABCC', target: FALLBACK_URL },
};

/**
 * Resolve a surface's outbound URL.
 *
 * @param {string} surface A key of CTA_SURFACES.
 * @return {string} The short-link URL, or the initial target when unminted.
 */
export const premiumLink = ( surface ) => {
	const entry = CTA_SURFACES[ surface ];
	if ( ! entry ) {
		return FALLBACK_URL;
	}
	return entry.code ? `${ SHORT_LINK_BASE }${ entry.code }` : entry.target;
};

/**
 * Documentation pages cited as the source for a claim.
 */
export const EVIDENCE = {
	safariItp:
		'https://gtmkit.com/documentation/safari-itp-and-cookie-lifetime/',
	duplicateGa4:
		'https://gtmkit.com/documentation/duplicate-ga4-configuration/',
	consentOrdering:
		'https://gtmkit.com/documentation/consent-default-must-run-before-the-container/',
	subscriptionRenewals:
		'https://gtmkit.com/documentation/subscription-renewals-missing-from-analytics/',
	testWithoutOrders:
		'https://gtmkit.com/documentation/test-your-tracking-without-real-orders/',
};
