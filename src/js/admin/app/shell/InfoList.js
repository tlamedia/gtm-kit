/*WordPress*/
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';

const CARD =
	'gtmkit-mb-6 gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white';
const ROW =
	'gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-4 gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-3.5';
const ACTION =
	'gtmkit-shrink-0 gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline';

/**
 * A titled card containing a stack of divided rows.
 *
 * @param {Object}      props               Component props.
 * @param {string}      props.title         Card title.
 * @param {JSX.Element} [props.headerAction] An optional right-aligned header action.
 * @param {JSX.Element} props.children      Card rows.
 * @return {JSX.Element} The card.
 */
export const InfoCard = ( { title, headerAction, children } ) => (
	<div className={ CARD }>
		<div className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-4 gtmkit-px-5 gtmkit-py-4">
			<h3 className="gtmkit-m-0 gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary">
				{ title }
			</h3>
			{ headerAction }
		</div>
		{ children }
	</div>
);

/**
 * A full-width prose row inside a card (e.g. a description).
 *
 * @param {Object}      props          Component props.
 * @param {JSX.Element} props.children The text.
 * @return {JSX.Element} The note row.
 */
export const InfoNote = ( { children } ) => (
	<div className="gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-3.5">
		<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-secondary">
			{ children }
		</p>
	</div>
);

/**
 * A list row: a title (with optional badge) and subtitle on the left, and an
 * action or value on the right.
 *
 * @param {Object}      props            Component props.
 * @param {string}      props.title      Row title.
 * @param {string}      [props.subtitle] Row subtitle.
 * @param {JSX.Element} [props.badge]    A badge beside the title.
 * @param {JSX.Element} [props.right]    The right-hand action or value.
 * @return {JSX.Element} The row.
 */
export const InfoRow = ( { title, subtitle, badge, right } ) => (
	<div className={ ROW }>
		<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
			<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2">
				<span className="gtmkit-text-sm gtmkit-font-medium gtmkit-text-text-primary">
					{ title }
				</span>
				{ badge }
			</div>
			{ subtitle && (
				<span className="gtmkit-text-xs gtmkit-text-text-muted">
					{ subtitle }
				</span>
			) }
		</div>
		{ right }
	</div>
);

/**
 * An external "{label} →" link for a row's right side.
 *
 * @param {Object} props       Component props.
 * @param {string} props.href  Target URL.
 * @param {string} props.label Link label.
 * @return {JSX.Element} The link.
 */
export const ExternalAction = ( { href, label } ) => (
	<a href={ href } target="_blank" rel="noreferrer" className={ ACTION }>
		{ label } →
	</a>
);

/**
 * An in-app "{label} →" route link for a row's right side.
 *
 * @param {Object} props       Component props.
 * @param {string} props.to    Target route.
 * @param {string} props.label Link label.
 * @return {JSX.Element} The link.
 */
export const RouteAction = ( { to, label } ) => (
	<Link to={ to } className={ ACTION }>
		{ label } →
	</Link>
);

/**
 * A plain value for a row's right side (e.g. a version number).
 *
 * @param {Object} props          Component props.
 * @param {string} props.children The value.
 * @return {JSX.Element} The value.
 */
export const InfoValue = ( { children } ) => (
	<span className="gtmkit-shrink-0 gtmkit-text-sm gtmkit-text-text-secondary">
		{ children }
	</span>
);

/**
 * The green Premium tier badge.
 *
 * @return {JSX.Element} The badge.
 */
export const PremiumBadge = () => (
	<span className="gtmkit-inline-flex gtmkit-items-center gtmkit-rounded-sm gtmkit-bg-tier-premium-bg gtmkit-px-2 gtmkit-py-0.5 gtmkit-text-[11px] gtmkit-font-medium gtmkit-text-tier-premium">
		{ __( 'Premium', 'gtm-kit' ) }
	</span>
);

/**
 * A page heading with a subtitle, shared by the meta pages.
 *
 * @param {Object} props          Component props.
 * @param {string} props.title    Page title.
 * @param {string} props.subtitle Page subtitle.
 * @return {JSX.Element} The header.
 */
export const PageHeader = ( { title, subtitle } ) => (
	<div className="gtmkit-mb-8">
		<h2 className="gtmkit-m-0 gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
			{ title }
		</h2>
		<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
			{ subtitle }
		</p>
	</div>
);
