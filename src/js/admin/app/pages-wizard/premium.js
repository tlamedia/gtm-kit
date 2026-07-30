/*WordPress*/
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { useNavigate } from 'react-router-dom';

/*Registry / constants / utils*/
import {
	getWizardVariant,
	getWizardContent,
	WIZARD_VARIANTS,
} from '../../registry/premiumWizard';
import { premiumLink } from '../../constants/premiumLinks';
import { getNextStepPath } from '../utils/get-steps';
import { getAdminLink } from '../utils/get-admin-link';

/**
 * The wizard's Premium step.
 *
 * Informational only: the primary button continues the wizard whether or not
 * the user looks at Premium, and the Premium call to action is a secondary
 * link that opens in a new tab. Declining is a single click.
 *
 * @return {JSX.Element} The step.
 */
const PremiumUpsell = () => {
	const navigate = useNavigate();
	const variant = getWizardVariant();
	const content = getWizardContent( variant );

	return (
		<>
			<h1 className="gtmkit-text-4xl gtmkit-font-medium gtmkit-mb-8 gtmkit-text-color-heading gtmkit-text-center">
				{ content.title }
			</h1>

			{ content.paragraphs.map( ( paragraph ) => (
				<p
					key={ paragraph }
					className="gtmkit-text-sm gtmkit-mb-4 gtmkit-text-color-grey"
				>
					{ paragraph }
				</p>
			) ) }

			{ content.clusters && (
				<ul className="gtmkit-text-sm gtmkit-mb-4 gtmkit-text-color-grey gtmkit-list-disc gtmkit-pl-6">
					{ content.clusters.map( ( cluster ) => (
						<li key={ cluster }>{ cluster }</li>
					) ) }
				</ul>
			) }

			{ content.claim && (
				<p className="gtmkit-text-sm gtmkit-mb-8 gtmkit-text-color-grey gtmkit-border-l-2 gtmkit-border-color-border gtmkit-pl-3">
					{ content.claim }
					{ content.source && (
						<>
							{ ' ' }
							<a
								href={ content.source }
								target="_blank"
								rel="noreferrer"
								className="gtmkit-text-color-primary"
							>
								{ __( 'Source', 'gtm-kit' ) }
							</a>
						</>
					) }
				</p>
			) }

			<p className="gtmkit-text-sm gtmkit-mb-12 gtmkit-text-color-grey">
				<a
					href={ premiumLink( content.link ) }
					target="_blank"
					rel="noreferrer"
					className="gtmkit-text-color-primary"
				>
					{ content.cta }
				</a>
				{ variant === WIZARD_VARIANTS.GENERIC && (
					<>
						{ ' · ' }
						<a
							href={ getAdminLink( 'general', 'premium' ) }
							rel="noreferrer"
							className="gtmkit-text-color-primary"
						>
							{ __( 'See what Premium adds', 'gtm-kit' ) }
						</a>
					</>
				) }
			</p>

			<div className="gtmkit-flex gtmkit-mt-12">
				<Button
					variant={ 'primary' }
					className="gtmkit-mx-auto gtmkit-rounded-md !gtmkit-py-6 !gtmkit-px-8 gtmkit-text-base disabled:!gtmkit-bg-color-button-disabled disabled:!gtmkit-text-color-grey"
					onClick={ () => {
						navigate( getNextStepPath( '/premium' ), {
							replace: true,
						} );
					} }
				>
					{ __( 'Continue', 'gtm-kit' ) }
				</Button>
			</div>
		</>
	);
};

export default PremiumUpsell;
