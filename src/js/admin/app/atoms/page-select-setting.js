/*WordPress*/
import { useContext, useState, useMemo, memo } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';

/*Inbuilt Context*/
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { __ } from '@wordpress/i18n';
import SettingsService from '../../services/SettingsService';

const PageSelectSetting = memo(
	( {
		title,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4',
		optionGroup = 'general',
		optionName,
		disabled = false,
		help = '',
		notSet = true,
		maxResults = 15,
	} ) => {
		const { useSettings, updateStateSettings } =
			useContext( SettingsDataContext );
		const [ searchTerm, setSearchTerm ] = useState( '' );

		// Get current value
		const currentValue =
			useSettings && useSettings[ optionGroup ][ optionName ];

		// Filter pages based on search term and limit results
		const filteredOptions = useMemo( () => {
			// Get all pages from SettingsService
			const allPages = SettingsService.getPageOptions();
			let filtered = allPages;

			if ( searchTerm ) {
				filtered = allPages.filter( ( page ) =>
					page.label
						.toLowerCase()
						.includes( searchTerm.toLowerCase() )
				);
			}

			// Limit to maxResults
			const limited = filtered.slice( 0, maxResults );

			// Prepare options for ComboboxControl
			const options = notSet
				? [
						{ label: __( '(not set)', 'gtm-kit' ), value: '' },
						...limited,
				  ]
				: limited;

			// If current value is set and not in filtered results, add it to the list
			if ( currentValue && currentValue !== '' ) {
				const currentPage = allPages.find(
					( page ) => page.value === currentValue
				);
				if (
					currentPage &&
					! options.find( ( opt ) => opt.value === currentValue )
				) {
					options.unshift( currentPage );
				}
			}

			return options;
		}, [ searchTerm, currentValue, maxResults, notSet ] );

		return (
			<>
				<ComboboxControl
					label={ title }
					value={ currentValue || '' }
					options={ filteredOptions }
					className={ className }
					onChange={ ( newVal ) =>
						updateStateSettings( optionGroup, optionName, newVal )
					}
					onFilterValueChange={ ( value ) => setSearchTerm( value ) }
					disabled={ disabled }
					help={ help }
				/>
			</>
		);
	}
);

export default PageSelectSetting;
