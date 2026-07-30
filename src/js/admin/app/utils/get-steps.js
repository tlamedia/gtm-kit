/*WordPress*/
import { __ } from '@wordpress/i18n';

import { isWizardUpsellEnabled } from '../../registry/premiumWizard';

/**
 * Every step the wizard can show, in flow order.
 *
 * A step marked `upsell` is only part of the flow on installs that see Premium
 * marketing at all. Step numbers are assigned when the flow is resolved, so the
 * progress timeline counts what the user will actually walk through rather than
 * leaving a gap where a skipped step would have been.
 */
const WIZARD_STEPS = [
	{
		path: '/welcome',
		element: 'Welcome',
		title: __( 'Welcome', 'gtm-kit' ),
	},
	{
		path: '/essential-settings',
		element: 'EssentialSettings',
		title: __( 'Essential Settings', 'gtm-kit' ),
	},
	{
		path: '/share-anonymous-data',
		element: 'ShareAnonymousData',
		title: __( 'Help improve GTM Kit', 'gtm-kit' ),
	},
	{
		path: '/automatic-updates',
		element: 'Automatic Updates',
		title: __( 'Automatic Updates', 'gtm-kit' ),
	},
	{
		path: '/premium',
		element: 'PremiumUpsell',
		title: __( 'GTM Kit Premium', 'gtm-kit' ),
		upsell: true,
	},
	{
		path: '/getting-started',
		element: 'GettingStarted',
		title: __( 'Getting Started', 'gtm-kit' ),
	},
];

/**
 * Resolve the flow for a site, numbering the steps it contains.
 *
 * @param {boolean} [withUpsell] Whether upsell steps are part of the flow.
 * @return {Array} The ordered, numbered steps.
 */
export const resolveWizardSteps = ( withUpsell ) =>
	WIZARD_STEPS.filter( ( step ) => ! step.upsell || withUpsell ).map(
		( step, index ) => ( { ...step, step: index + 1 } )
	);

/**
 * The wizard flow for this install.
 *
 * @return {Array} The ordered, numbered steps.
 */
export const getWizardSteps = () =>
	resolveWizardSteps( isWizardUpsellEnabled() );

/**
 * The path of the step following a given one.
 *
 * Lets a step hand off to whatever comes next without hardcoding a sibling,
 * so removing a step from the flow cannot strand the one before it.
 *
 * @param {string} currentPath The current step's path.
 * @param {Array}  [steps]     The resolved flow. Defaults to this install's.
 * @return {string} The next step's path, or the last step's path at the end.
 */
export const getNextStepPath = ( currentPath, steps = getWizardSteps() ) => {
	const index = steps.findIndex( ( step ) => step.path === currentPath );
	if ( index === -1 ) {
		return steps[ steps.length - 1 ].path;
	}
	return steps[ Math.min( index + 1, steps.length - 1 ) ].path;
};
