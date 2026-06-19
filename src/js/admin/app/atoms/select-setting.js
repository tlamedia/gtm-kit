/*WordPress*/
import { memo } from '@wordpress/element';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

/**
 * Select setting component
 *
 * Refactored to use custom hooks (Phase 4 Component Improvements)
 * No longer requires prop drilling of context values
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 */
const SelectSetting = memo(
	( {
		title,
		options,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4',
		optionGroup = 'general',
		optionName,
		disabled = false,
		help = '',
		notSet = false,
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );

		const updatedOptions = notSet
			? [ { label: __( '(not set)', 'gtm-kit' ), value: '' }, ...options ]
			: options;

		return (
			<>
				<SelectControl
					label={ title }
					value={ value }
					options={ updatedOptions }
					className={ className }
					onChange={ setValue }
					disabled={ disabled }
					help={ help }
				/>
			</>
		);
	}
);

export default SelectSetting;
