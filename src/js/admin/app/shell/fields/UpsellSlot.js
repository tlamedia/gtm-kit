/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';

/**
 * The product the upsell points to. GTM Kit Premium is the superset that unlocks
 * every paid tier, so it is the right upgrade target regardless of whether the
 * locked field is a Woo-tier or Premium-tier setting.
 */
const PREMIUM_PRODUCT = __( 'GTM Kit Premium', 'gtm-kit' );

/**
 * Where the upgrade link sends the user.
 */
const UPGRADE_URL = 'https://jump.gtmkit.com/link/7-1CE79';

/**
 * The notice shown beneath a tier-locked field: a single link that explains the
 * control is locked and sends the user to GTM Kit Premium.
 *
 * @return {JSX.Element} The notice.
 */
const UpsellSlot = () => (
	<a
		href={ UPGRADE_URL }
		target="_blank"
		rel="noreferrer"
		className="gtmkit-m-0 gtmkit-mt-1 gtmkit-block gtmkit-text-xs gtmkit-text-[#9a6700] hover:gtmkit-underline"
	>
		{ sprintf(
			/* translators: %s: the product that unlocks this setting. */
			__( 'Upgrade to %s to manage this setting here.', 'gtm-kit' ),
			PREMIUM_PRODUCT
		) }
	</a>
);

export default UpsellSlot;
