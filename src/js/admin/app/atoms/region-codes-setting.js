/*WordPress*/
import { TextControl } from '@wordpress/components';
import { memo, useState, useEffect } from '@wordpress/element';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

/**
 * Freeform comma-separated input for Consent Mode v2 region codes.
 *
 * The underlying option (`gcm_region`) is stored as an array of ISO
 * codes. This atom presents it as an editable comma-separated string
 * and splits back to an array on blur. Server-side sanitization
 * (uppercase, drop invalid codes) happens in
 * `OptionSchema::sanitize_region_codes()`.
 */
const RegionCodesSetting = memo(
	( {
		title,
		placeholder,
		help,
		className = 'gtmkit-settings-field-wrap gtmkit-py-4 gtmkit-max-w-md',
		optionGroup = 'general',
		optionName,
		isDisabled,
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );

		const toDisplay = ( arr ) =>
			Array.isArray( arr ) ? arr.join( ', ' ) : '';

		const [ text, setText ] = useState( toDisplay( value ) );

		useEffect( () => {
			setText( toDisplay( value ) );
		}, [ value ] );

		const commitToStore = ( raw ) => {
			const parsed = raw
				.split( ',' )
				.map( ( code ) => code.trim().toUpperCase() )
				.filter( ( code ) => code.length > 0 );
			setValue( parsed );
		};

		return (
			<TextControl
				label={ title }
				placeholder={ placeholder }
				help={ help }
				className={ className }
				value={ text }
				onChange={ ( newVal ) => setText( newVal ) }
				onBlur={ () => commitToStore( text ) }
				disabled={ isDisabled }
			/>
		);
	}
);

export default RegionCodesSetting;
