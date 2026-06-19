/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { Link, useParams, Navigate } from 'react-router-dom';

/*Inbuilt Components*/
import Section from './Section';
import ContextPane from './ContextPane';

/*Registry*/
import {
	getIntegration,
	getIntegrationPrimaryCapability,
	getIntegrationSections,
	INTEGRATIONS_CONTEXT,
	STATUS,
} from '../../registry/integrationsData';

/**
 * Breadcrumb back to the Integrations hub.
 *
 * @param {Object} props       Component props.
 * @param {string} props.title The current integration title.
 * @return {JSX.Element} The breadcrumb.
 */
const Breadcrumb = ( { title } ) => (
	<div className="gtmkit-mb-2 gtmkit-flex gtmkit-items-center gtmkit-gap-1.5 gtmkit-text-[13px]">
		<Link
			to="/integrations"
			className="gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
		>
			{ __( 'Integrations', 'gtm-kit' ) }
		</Link>
		<span className="gtmkit-text-text-muted">/</span>
		<span className="gtmkit-text-text-muted">{ title }</span>
	</div>
);

/**
 * The section's display label on a config page. The page is already scoped to
 * one integration, so the integration prefix the capability pages need is
 * dropped here, and the bare enable section is named "<Integration> Integration".
 *
 * @param {string} label            The registry section label.
 * @param {string} integrationTitle The integration's name.
 * @return {string} The display label.
 */
const sectionLabel = ( label, integrationTitle ) => {
	if ( label === integrationTitle ) {
		return sprintf(
			/* translators: %s: integration name. */
			__( '%s Integration', 'gtm-kit' ),
			integrationTitle
		);
	}

	const prefix = `${ integrationTitle }: `;
	return label.startsWith( prefix ) ? label.slice( prefix.length ) : label;
};

/**
 * A single integration's config page: the integration's settings, narrowed from
 * the capability pages, reached from the Integrations hub.
 *
 * @return {JSX.Element} The page.
 */
const IntegrationConfig = () => {
	const { slug } = useParams();
	const integration = getIntegration( slug );

	if ( ! integration ) {
		return <Navigate replace to="/integrations" />;
	}

	const capability = getIntegrationPrimaryCapability( slug );
	const sections = getIntegrationSections( slug );

	return (
		<>
			<Breadcrumb title={ integration.title } />

			<div className="gtmkit-mb-8">
				<h2 className="gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ integration.title }
				</h2>
				{ capability && (
					<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
						{ sprintf(
							/* translators: %s: capability name. */
							__(
								'Reached from Integrations · settings live under %s',
								'gtm-kit'
							),
							capability.label
						) }
					</p>
				) }
			</div>

			<div className="min-[1440px]:gtmkit-flex min-[1440px]:gtmkit-items-start min-[1440px]:gtmkit-gap-6">
				<div className="min-[1440px]:gtmkit-min-w-0 min-[1440px]:gtmkit-flex-1">
					{ integration.status === STATUS.NOT_INSTALLED ? (
						<div className="gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-surface gtmkit-px-6 gtmkit-py-5 gtmkit-text-sm gtmkit-text-text-muted">
							{ sprintf(
								/* translators: %s: integration name. */
								__(
									'Install and activate the %s plugin to configure this integration.',
									'gtm-kit'
								),
								integration.title
							) }
						</div>
					) : (
						sections.map( ( section ) => (
							<Section
								key={ `${ section.capability }/${ section.id }` }
								section={ {
									...section,
									label: sectionLabel(
										section.label,
										integration.title
									),
								} }
								filter={ slug }
							/>
						) )
					) }
				</div>
				<aside className="gtmkit-hidden min-[1440px]:gtmkit-block min-[1440px]:gtmkit-w-[400px] min-[1440px]:gtmkit-shrink-0 min-[1440px]:gtmkit-sticky min-[1440px]:gtmkit-top-[112px] min-[1440px]:gtmkit-self-start">
					<ContextPane context={ INTEGRATIONS_CONTEXT } />
				</aside>
			</div>
		</>
	);
};

export default IntegrationConfig;
