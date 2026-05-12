import { __ } from '@wordpress/i18n';

/**
 * Placeholder body for an intro whose id has no registered component.
 * The modal still renders so the dismissal flow stays usable instead of
 * leaving the user with a blank screen.
 *
 * @return {JSX.Element}
 */
const UntitledIntroFallback = () => (
	<div>
		<h2 className="gtmkit-text-2xl gtmkit-font-medium gtmkit-mb-4 gtmkit-text-color-heading">
			{ __( 'Untitled introduction', 'gtm-kit' ) }
		</h2>
		<p className="gtmkit-text-base gtmkit-text-color-grey">
			{ __(
				'This introduction does not have a registered component yet.',
				'gtm-kit'
			) }
		</p>
	</div>
);

export default UntitledIntroFallback;
