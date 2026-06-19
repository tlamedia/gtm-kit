/*WordPress*/
import { RadioControl } from '@wordpress/components';
import { memo } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

/**
 * Radio setting component
 *
 * Refactored to use custom hooks (Phase 4 Component Improvements)
 * No longer requires prop drilling of context values
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 *
 * Pass `valueType="string"` for enum-style options whose values are
 * strings; the default `"integer"` keeps backward compatibility with
 * the integer-valued options that originally used this atom.
 */
const RadioSetting = memo(
	( {
		title,
		options,
		help,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4',
		optionGroup = 'general',
		optionName,
		disabled,
		valueType = 'integer',
		defaultValue,
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );
		const effective =
			value === undefined || value === null || value === ''
				? defaultValue
				: value;
		const selected =
			valueType === 'string' ? effective : parseInt( effective );

		return (
			<RadioControl
				label={ title }
				options={ options }
				help={ help }
				className={ className }
				selected={ selected }
				onChange={ setValue }
				disabled={ disabled }
			/>
		);
	}
);

export default RadioSetting;
