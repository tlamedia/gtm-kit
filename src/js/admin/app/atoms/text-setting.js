/*WordPress*/
import { TextControl } from '@wordpress/components';
import { memo } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';
import { useNotification } from '../../hooks/useNotification';

/**
 * Text setting component
 *
 * Refactored to use custom hooks (Phase 4 Component Improvements)
 * No longer requires prop drilling of context values
 *
 * @since Phase 4 Component Improvements (2026-01-27)
 */
const TextSetting = memo(
	( {
		title,
		placeholder,
		help,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4 gtmkit-max-w-md',
		optionGroup = 'general',
		optionName,
		isDisabled,
		notificationId = '',
		onBlur,
		transform, // Optional function to transform value on blur
		type, // Optional HTML input type (e.g. "number")
		min,
		max,
		step,
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );
		const { removeNotification } = useNotification();

		return (
			<TextControl
				label={ title }
				placeholder={ placeholder }
				help={ help }
				className={ className }
				value={ value ?? '' }
				type={ type }
				min={ min }
				max={ max }
				step={ step }
				onChange={ ( newVal ) => {
					setValue( newVal );
					if ( notificationId ) {
						removeNotification( notificationId );
					}
				} }
				onBlur={ ( e ) => {
					const currentValue = e.target.value;

					// Apply transformation if provided
					if ( transform ) {
						const transformed = transform( currentValue );
						if ( transformed !== currentValue ) {
							setValue( transformed );
						}
					}

					// Call custom onBlur if provided
					if ( onBlur ) {
						onBlur( currentValue );
					}
				} }
				disabled={ isDisabled }
			/>
		);
	}
);

export default TextSetting;
