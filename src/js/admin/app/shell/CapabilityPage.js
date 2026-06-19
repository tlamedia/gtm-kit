/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useContext } from '@wordpress/element';
import { useLocation } from 'react-router-dom';

/*Inbuilt Components*/
import Section from './Section';
import ContextPane from './ContextPane';
import { FilterNotice, HiddenIntegrationNotice } from './FilterNotices';

/*Registry / context*/
import { getCapability } from '../../registry/capabilities';
import {
	getCapabilitySectionsView,
	countCapabilityIntegrationFields,
} from '../../registry/filtering';
import { effectiveFilterFor } from '../../registry/integrations';
import { FilterContext } from '../../context/FilterContext';
import SettingsService from '../../services/SettingsService';

/**
 * Strip a leading "<Integration>: " prefix from a section label. The integration
 * is implied by the page grouping, so the prefix is redundant on capability
 * pages.
 *
 * @param {string}        label  The section label.
 * @param {Array<string>} titles The integration enable-section titles.
 * @return {string} The display label.
 */
const stripIntegrationPrefix = ( label, titles ) => {
	const prefix = titles.find( ( title ) =>
		label.startsWith( `${ title }: ` )
	);
	return prefix ? label.slice( prefix.length + 2 ) : label;
};

/**
 * A small, muted plugin-version line shown at the bottom of the Tools page,
 * mirroring the version the legacy Misc page displayed. Renders nothing when
 * the version is unavailable.
 *
 * @return {JSX.Element|null} The version line, or null.
 */
const VersionFooter = () => {
	const version = SettingsService.getVersion();
	if ( ! version ) {
		return null;
	}
	return (
		<p className="gtmkit-mt-8 gtmkit-text-xs gtmkit-text-text-muted">
			{ sprintf(
				/* translators: %s: GTM Kit plugin version. */
				__( 'GTM Kit version %s', 'gtm-kit' ),
				version
			) }
		</p>
	);
};

/**
 * Render a capability page entirely from the registry: a heading plus each of
 * the capability's sections in order. Each section is wrapped in an anchor so
 * deep links (`?focus=<section-id>`) scroll to it, preserving the bespoke
 * pages' in-page anchoring.
 *
 * @param {Object} props              Component props.
 * @param {string} props.capabilityId The capability id to render.
 * @return {JSX.Element|null} The rendered page.
 */
const CapabilityPage = ( { capabilityId } ) => {
	const location = useLocation();

	useEffect( () => {
		const focusId = new URLSearchParams( location.search ).get( 'focus' );
		if ( focusId ) {
			document.getElementById( focusId )?.scrollIntoView();
		}
	}, [ location ] );

	const { activeFilter } = useContext( FilterContext );

	const capability = getCapability( capabilityId );

	if ( ! capability ) {
		return null;
	}

	const effectiveFilter = effectiveFilterFor( capabilityId, activeFilter );

	const { visibleSections, hiddenIntegrations } = getCapabilitySectionsView(
		capabilityId,
		effectiveFilter
	);

	// The integration enable toggle lives on the Integrations page, so drop its
	// section here (the enable section is the integrationConfig section labelled
	// exactly with the integration name); sections flagged `hideOnCapability`
	// are likewise integration-config-only. The redundant "<Integration>: "
	// prefix is dropped from the remaining section labels.
	const integrationTitles = Object.values( SettingsService.getIntegrations() )
		.map( ( meta ) => meta.title )
		.filter( Boolean );
	const renderableSections = visibleSections.filter(
		( section ) =>
			! section.hideOnCapability &&
			! (
				section.integrationConfig &&
				integrationTitles.includes( section.label )
			)
	);

	const sectionList = (
		<>
			{ renderableSections.map( ( section ) => (
				<div key={ section.id } id={ section.id }>
					<Section
						section={ {
							...section,
							label: stripIntegrationPrefix(
								section.label,
								integrationTitles
							),
						} }
						filter={ effectiveFilter }
					/>
				</div>
			) ) }
			{ hiddenIntegrations.map( ( integration ) => (
				<HiddenIntegrationNotice
					key={ integration }
					integration={ integration }
					filter={ effectiveFilter }
				/>
			) ) }
		</>
	);

	return (
		<>
			<div className="gtmkit-mb-8">
				<h2 className="gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ capability.label }
				</h2>
				{ effectiveFilter ? (
					<FilterNotice
						filter={ effectiveFilter }
						capabilityLabel={ capability.label }
						matchingCount={ countCapabilityIntegrationFields(
							capabilityId,
							effectiveFilter
						) }
					/>
				) : (
					capability.description && (
						<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
							{ capability.description }
						</p>
					)
				) }
			</div>

			{ capability.context ? (
				<div className="min-[1440px]:gtmkit-flex min-[1440px]:gtmkit-items-start min-[1440px]:gtmkit-gap-6">
					<div className="min-[1440px]:gtmkit-min-w-0 min-[1440px]:gtmkit-flex-1">
						{ sectionList }
					</div>
					<aside className="gtmkit-hidden min-[1440px]:gtmkit-block min-[1440px]:gtmkit-w-[400px] min-[1440px]:gtmkit-shrink-0 min-[1440px]:gtmkit-sticky min-[1440px]:gtmkit-top-[112px] min-[1440px]:gtmkit-self-start">
						<ContextPane context={ capability.context } />
					</aside>
				</div>
			) : (
				sectionList
			) }

			{ capabilityId === 'tools' && <VersionFooter /> }
		</>
	);
};

export default CapabilityPage;
