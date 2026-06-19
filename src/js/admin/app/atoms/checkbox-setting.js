/*WordPress*/
import { BaseControl, CheckboxControl } from '@wordpress/components';
import { useId, memo } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

/**
 * Checkbox setting component
 *
 * Refactored to use custom hooks (Phase 4 Component Improvements)
 * No longer requires prop drilling of context values
 * Removed local state management - now directly uses context
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 */
const CheckboxSetting = memo(
	( {
		title,
		help,
		items = [],
		optionGroup = 'general',
		optionName,
		disabled = false,
	} ) => {
		const uniqueId = useId();
		const [ value, setValue ] = useSettingField( optionGroup, optionName );

		// Ensure value is always an array
		const selectedItems = Array.isArray( value ) ? value : [];

		const handleChange = ( isChecked, itemValue ) => {
			let newSelectedItems;
			if ( isChecked ) {
				newSelectedItems = [ ...selectedItems, itemValue ];
			} else {
				newSelectedItems = selectedItems.filter(
					( item ) => item !== itemValue
				);
			}
			setValue( newSelectedItems );
		};

		return (
			<>
				<div className="gtmkit-settings-field-wrap gtmkit-py-4">
					<BaseControl label={ title } help={ help } id={ uniqueId }>
						{ items.map( ( item ) => (
							<CheckboxControl
								key={ item.role }
								label={ item.name }
								disabled={ disabled }
								checked={ selectedItems.includes( item.role ) }
								onChange={ ( isChecked ) =>
									handleChange( isChecked, item.role )
								}
							/>
						) ) }
					</BaseControl>
				</div>
			</>
		);
	}
);

export default CheckboxSetting;
