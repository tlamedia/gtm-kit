/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';
import { Link } from 'react-router-dom';

/*Registry / context*/
import {
	getIntegrations,
	INTEGRATIONS_CONTEXT,
	STATUS,
} from '../../registry/integrationsData';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import ContextPane from './ContextPane';

const BADGE =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-px-2 gtmkit-py-0.5 gtmkit-rounded-sm gtmkit-text-[11px] gtmkit-font-medium gtmkit-whitespace-nowrap';

const STATUS_BADGE = {
	[ STATUS.ACTIVE ]: {
		label: __( 'Active', 'gtm-kit' ),
		className: 'gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium',
	},
	[ STATUS.OFF ]: {
		label: __( 'Off', 'gtm-kit' ),
		className: 'gtmkit-bg-[#ececed] gtmkit-text-text-muted',
	},
	[ STATUS.NOT_INSTALLED ]: {
		label: __( 'Not installed', 'gtm-kit' ),
		className: 'gtmkit-bg-[#fcf3d6] gtmkit-text-[#8a6d00]',
	},
};

/**
 * A single integration row: name (and Premium badge), description, status and a
 * Configure link to the integration's config page.
 *
 * @param {Object} props             Component props.
 * @param {Object} props.integration The integration descriptor.
 * @return {JSX.Element} The row.
 */
const IntegrationRow = ( { integration } ) => {
	const status = STATUS_BADGE[ integration.status ];

	return (
		<div className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-5 gtmkit-py-[18px]">
			<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-[5px]">
				<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2">
					<span className="gtmkit-text-[15px] gtmkit-font-medium gtmkit-text-text-primary">
						{ integration.title }
					</span>
					{ integration.isPremium && (
						<span
							className={ `${ BADGE } gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium` }
						>
							{ __( 'Premium', 'gtm-kit' ) }
						</span>
					) }
				</div>
				<span className="gtmkit-text-[13px] gtmkit-text-text-muted">
					{ integration.description }
				</span>
			</div>
			<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-[14px]">
				{ status && (
					<span className={ `${ BADGE } ${ status.className }` }>
						{ status.label }
					</span>
				) }
				<Link
					to={ `/integrations/${ integration.slug }` }
					className="gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
				>
					{ __( 'Configure', 'gtm-kit' ) } →
				</Link>
			</div>
		</div>
	);
};

/**
 * The Integrations hub: a list of integrations the user can configure. Enabling
 * happens here; the settings themselves live on the capability pages and on
 * each integration's config page.
 *
 * @return {JSX.Element} The hub.
 */
const IntegrationsHub = () => {
	const { settings } = useContext( SettingsDataContext );
	const integrations = getIntegrations( settings );

	return (
		<>
			<div className="gtmkit-mb-8">
				<h2 className="gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ __( 'Integrations', 'gtm-kit' ) }
				</h2>
				<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
					{ __(
						'Enable integrations here. Their settings live on the capability pages.',
						'gtm-kit'
					) }
				</p>
			</div>

			<div className="min-[1440px]:gtmkit-flex min-[1440px]:gtmkit-items-start min-[1440px]:gtmkit-gap-6">
				<div className="gtmkit-space-y-3.5 min-[1440px]:gtmkit-min-w-0 min-[1440px]:gtmkit-flex-1">
					{ integrations.map( ( integration ) => (
						<IntegrationRow
							key={ integration.slug }
							integration={ integration }
						/>
					) ) }
				</div>
				<aside className="gtmkit-hidden min-[1440px]:gtmkit-block min-[1440px]:gtmkit-w-[400px] min-[1440px]:gtmkit-shrink-0 min-[1440px]:gtmkit-sticky min-[1440px]:gtmkit-top-[112px] min-[1440px]:gtmkit-self-start">
					<ContextPane context={ INTEGRATIONS_CONTEXT } />
				</aside>
			</div>
		</>
	);
};

export default IntegrationsHub;
