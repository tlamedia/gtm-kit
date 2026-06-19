/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';
import { useLocation } from 'react-router-dom';

/*Context / registry*/
import { FilterContext } from '../../context/FilterContext';
import { getCapabilityIntegrations } from '../../registry/integrations';

const CHIP =
	'gtmkit-px-3 gtmkit-py-[5px] gtmkit-rounded-[6px] gtmkit-text-xs gtmkit-whitespace-nowrap';
const CHIP_ACTIVE =
	'gtmkit-bg-brand-primary gtmkit-text-white gtmkit-font-medium';
const CHIP_INACTIVE =
	'gtmkit-border gtmkit-border-border-default gtmkit-text-text-secondary hover:gtmkit-text-text-primary';

/**
 * A single "Viewing" filter chip.
 *
 * @param {Object}   props          Component props.
 * @param {boolean}  props.active   Whether this chip is the active filter.
 * @param {Function} props.onSelect Select handler.
 * @param {string}   props.label    Chip label.
 * @return {JSX.Element} The chip.
 */
const Chip = ( { active, onSelect, label } ) => (
	<button
		type="button"
		onClick={ onSelect }
		className={ `${ CHIP } ${ active ? CHIP_ACTIVE : CHIP_INACTIVE }` }
	>
		{ label }
	</button>
);

/**
 * The "Viewing: [integration]" filter chips, shown in the top bar beside the
 * search. Offered only on the current capability and only for active
 * integrations; with fewer than two integrations to choose between there is
 * nothing to filter, so the control hides itself.
 *
 * @return {JSX.Element|null} The chips, or null.
 */
const FilterChips = () => {
	const { activeFilter, setActiveFilter } = useContext( FilterContext );
	const { pathname } = useLocation();

	const capabilityId = pathname.replace( /^\//, '' ).split( /[?#]/ )[ 0 ];
	const integrations = getCapabilityIntegrations( capabilityId );

	if ( integrations.length < 2 ) {
		return null;
	}

	const effectiveFilter = integrations.some(
		( integration ) => integration.slug === activeFilter
	)
		? activeFilter
		: null;

	return (
		<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2">
			<span className="gtmkit-text-[13px] gtmkit-text-text-secondary">
				{ __( 'Viewing:', 'gtm-kit' ) }
			</span>
			<Chip
				active={ effectiveFilter === null }
				onSelect={ () => setActiveFilter( null ) }
				label={ __( 'All integrations', 'gtm-kit' ) }
			/>
			{ integrations.map( ( integration ) => (
				<Chip
					key={ integration.slug }
					active={ effectiveFilter === integration.slug }
					onSelect={ () => setActiveFilter( integration.slug ) }
					label={ integration.label }
				/>
			) ) }
		</div>
	);
};

export default FilterChips;
