/*WordPress*/
import { memo, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { NavLink } from 'react-router-dom';

/*Registry / hooks*/
import BrandLogo from './BrandLogo';
import { getCapabilitiesByZone, ZONES } from '../../registry/capabilities';
import { countCapabilityIntegrationFields } from '../../registry/filtering';
import { FilterContext } from '../../context/FilterContext';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import SettingsService from '../../services/SettingsService';

const ITEM_BASE =
	'gtmkit-flex gtmkit-items-center gtmkit-pl-5 gtmkit-pr-4 gtmkit-py-[9px] gtmkit-text-sm gtmkit-border-l-[3px]';

const ITEM_DEFAULT =
	'gtmkit-border-transparent gtmkit-text-text-secondary hover:gtmkit-text-color-heading';

const ITEM_ACTIVE =
	'gtmkit-border-brand-primary gtmkit-bg-brand-surface-subtle gtmkit-text-brand-primary gtmkit-font-medium';

/**
 * A sidebar nav item. When the integration filter is active, a count badge
 * shows how many of the capability's settings match the filter.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.capability The capability definition.
 * @param {number}   props.count      Matching-field count under the active filter.
 * @param {Function} props.onNavigate Called on click (clears the search).
 * @return {JSX.Element} The nav item.
 */
const NavItem = ( { capability, count, onNavigate } ) => (
	<NavLink
		to={ `/${ capability.id }` }
		onClick={ onNavigate }
		className={ ( { isActive } ) =>
			`${ ITEM_BASE } ${ isActive ? ITEM_ACTIVE : ITEM_DEFAULT }`
		}
	>
		{ ( { isActive } ) => (
			<>
				<span>{ capability.label }</span>
				{ count > 0 && (
					<span
						className={ `gtmkit-ml-auto gtmkit-text-xs gtmkit-tabular-nums ${
							isActive
								? 'gtmkit-text-brand-primary'
								: 'gtmkit-text-text-muted'
						}` }
					>
						{ count }
					</span>
				) }
			</>
		) }
	</NavLink>
);

/**
 * GTM Kit brand lockup. When the Premium plugin is active, a "Premium" badge
 * sits beside the logo behind a divider, with a state dot: green when the
 * license is valid, red when it is not. No badge without the Premium plugin.
 *
 * @param {Object}  props                 Component props.
 * @param {boolean} props.isPremiumPlugin Whether the Premium plugin is active.
 * @param {boolean} props.hasValidLicense Whether the license is currently valid.
 * @return {JSX.Element} The brand lockup.
 */
const BrandLockup = ( { isPremiumPlugin, hasValidLicense } ) => (
	<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-4 gtmkit-pb-[14px] gtmkit-px-5">
		<BrandLogo />
		{ isPremiumPlugin && (
			<>
				<span
					aria-hidden="true"
					className="gtmkit-h-5 gtmkit-w-px gtmkit-bg-border-default"
				/>
				<span className="gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1.5">
					<span
						aria-hidden="true"
						className={ `gtmkit-h-[7px] gtmkit-w-[7px] gtmkit-rounded-full ${
							hasValidLicense
								? 'gtmkit-bg-tier-premium'
								: 'gtmkit-bg-[#d63638]'
						}` }
					/>
					<span className="gtmkit-text-xs gtmkit-font-medium gtmkit-text-text-secondary">
						{ __( 'Premium', 'gtm-kit' ) }
					</span>
				</span>
			</>
		) }
	</div>
);

/**
 * Two-zone left navigation rendered from the capability registry: tracking
 * capabilities above the divider, plugin/meta pages below a "Plugin" label.
 *
 * @return {JSX.Element} The sidebar.
 */
const Sidebar = memo( ( { onNavigate } ) => {
	const capabilities = getCapabilitiesByZone( ZONES.CAPABILITY );
	const plugin = getCapabilitiesByZone( ZONES.PLUGIN );
	const { hasValidLicense } = useFeatureFlags();
	const { activeFilter } = useContext( FilterContext );

	const countFor = ( capability ) =>
		activeFilter
			? countCapabilityIntegrationFields( capability.id, activeFilter )
			: 0;

	return (
		<nav className="gtmkit-w-[248px] gtmkit-shrink-0 gtmkit-bg-surface gtmkit-border-r gtmkit-border-border-default gtmkit-flex gtmkit-flex-col gtmkit-gap-0.5 gtmkit-py-5 gtmkit-min-h-screen">
			<BrandLockup
				isPremiumPlugin={ SettingsService.isPremiumPlugin() }
				hasValidLicense={ hasValidLicense }
			/>

			{ capabilities.map( ( capability ) => (
				<NavItem
					key={ capability.id }
					capability={ capability }
					count={ countFor( capability ) }
					onNavigate={ onNavigate }
				/>
			) ) }

			<div className="gtmkit-px-5 gtmkit-pt-3 gtmkit-pb-2">
				<div className="gtmkit-h-px gtmkit-w-full gtmkit-bg-border-default" />
			</div>

			<div className="gtmkit-pl-5 gtmkit-pb-1.5">
				<span className="gtmkit-text-[11px] gtmkit-font-medium gtmkit-uppercase gtmkit-text-text-muted">
					{ __( 'Plugin', 'gtm-kit' ) }
				</span>
			</div>

			{ plugin.map( ( capability ) => (
				<NavItem
					key={ capability.id }
					capability={ capability }
					onNavigate={ onNavigate }
				/>
			) ) }
		</nav>
	);
} );

export default Sidebar;
