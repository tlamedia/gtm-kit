/*WordPress*/
import { createContext, useState } from '@wordpress/element';

/**
 * The active integration filter ("Viewing" control), shared across the sidebar,
 * the filter bar and the capability pages so the choice persists as the user
 * moves between capabilities. `null` means "All integrations".
 */
export const FilterContext = createContext( {
	activeFilter: null,
	setActiveFilter: () => {},
} );

/**
 * Provider for the integration filter state.
 *
 * @param {Object}      props          Component props.
 * @param {JSX.Element} props.children Wrapped tree.
 * @return {JSX.Element} The provider.
 */
export const FilterProvider = ( { children } ) => {
	const [ activeFilter, setActiveFilter ] = useState( null );

	return (
		<FilterContext.Provider value={ { activeFilter, setActiveFilter } }>
			{ children }
		</FilterContext.Provider>
	);
};
