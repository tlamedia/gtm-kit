/*WordPress*/
import { __ } from '@wordpress/i18n';

const CHIP =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-text-[11px] gtmkit-font-medium gtmkit-leading-none gtmkit-px-2 gtmkit-py-[3px] gtmkit-rounded-sm gtmkit-whitespace-nowrap';

/**
 * A small padlock glyph shown in the tier badge when the field is locked by the
 * current license.
 *
 * @return {JSX.Element} The icon.
 */
const LockIcon = () => (
	<svg
		width="9"
		height="9"
		viewBox="0 0 24 24"
		fill="none"
		aria-hidden="true"
		className="gtmkit-mr-1"
	>
		<path
			d="M6 10V8a6 6 0 0112 0v2m-9 0h6m-9 0a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2H6z"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

/**
 * The Premium tier badge rendered next to a field label when the field is gated
 * above the free tier. While the field is locked it is an upsell pill with a
 * padlock; once the license unlocks it the badge becomes a quiet, background-less
 * "(Premium)" that signals value rather than selling. Integration tags are
 * intentionally not shown: on a capability page every field tends to share the
 * same integration, so the tag is noise.
 *
 * @param {Object}  props        Component props.
 * @param {Object}  props.field  The field definition.
 * @param {boolean} props.locked Whether the field is locked by license tier.
 * @return {JSX.Element|null} The badge, or null when the field is free.
 */
const FieldTags = ( { field, locked = false } ) => {
	const isPremium = field.tier === 'premium' || field.tier === 'woo';

	if ( ! isPremium ) {
		return null;
	}

	// When the field is unlocked (the license is active) the badge's job is to
	// show value, not to upsell, so it drops the pill background and reads as a
	// quiet "(Premium)". While locked it stays an upsell pill with a padlock.
	if ( ! locked ) {
		return (
			<span className="gtmkit-text-[11px] gtmkit-font-medium gtmkit-leading-none gtmkit-text-tier-premium">
				{ __( '(Premium)', 'gtm-kit' ) }
			</span>
		);
	}

	return (
		<span
			className={ `${ CHIP } gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium` }
		>
			<LockIcon />
			{ __( 'Premium', 'gtm-kit' ) }
		</span>
	);
};

export default FieldTags;
