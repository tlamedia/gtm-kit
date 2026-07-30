/*WordPress*/
import { __ } from '@wordpress/i18n';

/*Registry / constants / components*/
import { getPremiumCards } from '../../registry/premiumSurface';
import { premiumLink } from '../../constants/premiumLinks';
import { PageHeader } from './InfoList';

const CARD =
	'gtmkit-flex gtmkit-flex-col gtmkit-gap-3.5 gtmkit-rounded-xl gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-6 gtmkit-pb-[22px] gtmkit-pt-6';

const CTA =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-self-start gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90 focus:gtmkit-outline focus:gtmkit-outline-2 focus:gtmkit-outline-offset-2 focus:gtmkit-outline-brand-primary';

/**
 * One Premium feature card: what the cluster does, the failure mode it
 * addresses with an optional link to the page documenting it, and a call to
 * action on that card's own short link.
 *
 * @param {Object} props      Component props.
 * @param {Object} props.card The card definition.
 * @return {JSX.Element} The card.
 */
const FeatureCard = ( { card } ) => (
	<div className={ CARD }>
		<h3 className="gtmkit-m-0 gtmkit-text-[17px] gtmkit-font-semibold gtmkit-text-text-primary">
			{ card.title }
		</h3>
		<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-leading-normal gtmkit-text-text-secondary">
			{ card.body }
		</p>
		{ card.claim && (
			<p className="gtmkit-m-0 gtmkit-flex-1 gtmkit-border-l-2 gtmkit-border-border-default gtmkit-pl-3 gtmkit-text-[13px] gtmkit-leading-normal gtmkit-text-text-muted">
				{ card.claim }
				{ card.source && (
					<>
						{ ' ' }
						<a
							href={ card.source }
							target="_blank"
							rel="noreferrer"
							className="gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
						>
							{ __( 'Source', 'gtm-kit' ) }
						</a>
					</>
				) }
			</p>
		) }
		{ ! card.claim && <span className="gtmkit-flex-1" /> }
		<a
			href={ premiumLink( card.link ) }
			target="_blank"
			rel="noreferrer"
			className={ CTA }
		>
			{ card.cta }
		</a>
	</div>
);

/**
 * The Premium overview page: what GTM Kit Premium adds on top of the free
 * plugin, one cluster per card, ordered for the site's setup.
 *
 * Rendered only on free-only installs. The route guard and the sidebar both
 * consult the same visibility rule, so a paying customer never reaches it.
 *
 * @return {JSX.Element} The page.
 */
const PremiumPage = () => {
	const cards = getPremiumCards();

	return (
		<>
			<PageHeader
				title={ __( 'GTM Kit Premium', 'gtm-kit' ) }
				subtitle={ __(
					'What Premium adds to the tracking you already have',
					'gtm-kit'
				) }
			/>

			<div className="gtmkit-mb-6 gtmkit-grid gtmkit-grid-cols-1 gtmkit-gap-4 md:gtmkit-grid-cols-2 xl:gtmkit-grid-cols-3">
				{ cards.map( ( card ) => (
					<FeatureCard key={ card.id } card={ card } />
				) ) }
			</div>

			<div className="gtmkit-flex gtmkit-flex-col gtmkit-items-start gtmkit-gap-3.5 gtmkit-rounded-xl gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-6 gtmkit-py-6 sm:gtmkit-flex-row sm:gtmkit-items-center sm:gtmkit-justify-between">
				<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-secondary">
					{ __(
						'GTM Kit Premium comes with a 14-day money-back guarantee. If it does not fit your setup, ask for a refund within 14 days.',
						'gtm-kit'
					) }
				</p>
				<a
					href={ premiumLink( 'pagePricing' ) }
					target="_blank"
					rel="noreferrer"
					className={ CTA }
				>
					{ __( 'See pricing', 'gtm-kit' ) }
				</a>
			</div>
		</>
	);
};

export default PremiumPage;
