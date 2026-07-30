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
 * Each surface carries the target its short link points at initially. Until a
 * code is minted, `premiumLink()` returns that target directly, so a call to
 * action always lands somewhere correct and only the attribution is missing.
 */

const SHORT_LINK_BASE = 'https://jump.gtmkit.com/link/';

const PRODUCT_URL = 'https://gtmkit.com/pricing/';
const PRICING_URL = 'https://gtmkit.com/pricing/';

/**
 * Call-to-action surfaces: the short-link code and the destination that code
 * resolves to today. A `code` of `null` has not been minted yet.
 */
export const CTA_SURFACES = {
	cardServerSide: { code: '8-01F58', target: PRODUCT_URL },
	cardPurchaseAccuracy: { code: '9-10C87', target: PRODUCT_URL },
	cardConsent: { code: '10-B66ED', target: PRODUCT_URL },
	cardForms: { code: '11-010E5', target: PRODUCT_URL },
	cardDebug: { code: '12-7E262', target: PRODUCT_URL },
	// Retargets to the business-case calculator once that page ships; the
	// change is made app-side, so no plugin release is involved.
	cardCalculator: { code: '13-FDA99', target: PRICING_URL },
	pagePricing: { code: '14-1C82F', target: PRICING_URL },
	wizardWoo: { code: '15-D352B', target: PRODUCT_URL },
	wizardConsent: { code: '16-8C659', target: PRODUCT_URL },
	wizardGeneric: { code: '17-6ABCC', target: PRICING_URL },
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
		return PRODUCT_URL;
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
