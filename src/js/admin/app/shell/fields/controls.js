/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

/*Hooks / registry*/
import Pill from './Pill';
import { useSettingField } from '../../../hooks/useSettingField';
import { useNotification } from '../../../hooks/useNotification';
import { parseKey, resolveOptions } from '../../../registry/controls';
import { getTransform } from '../../../registry/transforms';

export const BOX =
	'gtmkit-h-[36px] gtmkit-border gtmkit-border-border-default gtmkit-rounded-md gtmkit-bg-white gtmkit-px-3 gtmkit-text-[13px] gtmkit-text-text-primary focus:gtmkit-border-brand-primary focus:gtmkit-outline-none disabled:gtmkit-opacity-50';

/**
 * Pill toggle. Brand-colored when on, neutral when off.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The toggle.
 */
const ShellToggle = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const { removeNotification } = useNotification();
	const on = ! disabled && Boolean( value );

	return (
		<Pill
			on={ on }
			disabled={ disabled }
			label={ field.label }
			onClick={ () => {
				setValue( ! Boolean( value ) );
				if ( field.notificationId ) {
					removeNotification( field.notificationId );
				}
			} }
		/>
	);
};

/**
 * Text input box.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The input.
 */
const ShellText = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const { removeNotification } = useNotification();
	const transform = getTransform( field.transform );

	return (
		<input
			type="text"
			value={ value ?? '' }
			placeholder={ field.placeholder }
			disabled={ disabled }
			className={ `${ BOX } gtmkit-w-[360px] gtmkit-max-w-full` }
			onChange={ ( e ) => {
				setValue( e.target.value );
				if ( field.notificationId ) {
					removeNotification( field.notificationId );
				}
			} }
			onBlur={ ( e ) => {
				if ( transform ) {
					const next = transform( e.target.value );
					if ( next !== e.target.value ) {
						setValue( next );
					}
				}
			} }
		/>
	);
};

/**
 * Number input box.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The input.
 */
const ShellNumber = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );

	return (
		<input
			type="number"
			value={ value ?? '' }
			placeholder={ field.placeholder }
			min={ field.min }
			max={ field.max }
			step={ field.step }
			disabled={ disabled }
			className={ `${ BOX } gtmkit-w-[120px]` }
			onChange={ ( e ) => setValue( e.target.value ) }
		/>
	);
};

/**
 * Select box. Coerces integer-valued options back to numbers on change.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The select.
 */
const ShellSelect = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const options = resolveOptions( field );
	const isInteger = field.valueType === 'integer';
	const effective =
		value === undefined || value === null || value === ''
			? field.defaultValue ?? ''
			: value;

	return (
		<select
			value={ effective }
			disabled={ disabled }
			className={ `${ BOX } gtmkit-w-[320px] gtmkit-max-w-full gtmkit-cursor-pointer` }
			onChange={ ( e ) =>
				setValue(
					isInteger ? parseInt( e.target.value, 10 ) : e.target.value
				)
			}
		>
			{ field.notSet && (
				<option value="">{ __( '(not set)', 'gtm-kit' ) }</option>
			) }
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	);
};

/**
 * Region-codes box: an array option presented as a comma-separated string,
 * split back to uppercase ISO codes on blur.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The input.
 */
const ShellRegionCodes = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const toDisplay = ( arr ) =>
		Array.isArray( arr ) ? arr.join( ', ' ) : '';
	const [ text, setText ] = useState( toDisplay( value ) );

	useEffect( () => {
		setText( toDisplay( value ) );
	}, [ value ] );

	return (
		<input
			type="text"
			value={ text }
			placeholder={ field.placeholder }
			disabled={ disabled }
			className={ `${ BOX } gtmkit-w-[320px] gtmkit-max-w-full` }
			onChange={ ( e ) => setText( e.target.value ) }
			onBlur={ () =>
				setValue(
					text
						.split( ',' )
						.map( ( code ) => code.trim().toUpperCase() )
						.filter( ( code ) => code.length > 0 )
				)
			}
		/>
	);
};

/**
 * Radio-button group: each option on its own line, with the field's help text
 * below. Used where the choice and its trade-offs benefit from being visible at
 * once rather than hidden in a dropdown.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const ShellRadio = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const options = resolveOptions( field );
	const isInteger = field.valueType === 'integer';
	const effective =
		value === undefined || value === null || value === ''
			? field.defaultValue ?? ''
			: value;

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2.5">
			<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2">
				{ options.map( ( option ) => {
					const inputId = `${ group }-${ name }-${ option.value }`;
					return (
						<div
							key={ option.value }
							className="gtmkit-flex gtmkit-items-center gtmkit-gap-2"
						>
							<input
								id={ inputId }
								type="radio"
								name={ `${ group }-${ name }` }
								checked={
									String( effective ) ===
									String( option.value )
								}
								disabled={ disabled }
								onChange={ () =>
									setValue(
										isInteger
											? parseInt( option.value, 10 )
											: option.value
									)
								}
							/>
							<label
								htmlFor={ inputId }
								className="gtmkit-cursor-pointer gtmkit-text-[13px] gtmkit-text-text-primary"
							>
								{ option.label }
							</label>
						</div>
					);
				} ) }
			</div>
			{ field.help && (
				<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-text-secondary">
					{ field.help }
				</p>
			) }
		</div>
	);
};

const SHELL_CONTROLS = {
	toggle: ShellToggle,
	text: ShellText,
	number: ShellNumber,
	select: ShellSelect,
	radio: ShellRadio,
	'page-select': ShellSelect,
	'region-codes': ShellRegionCodes,
};

/**
 * Resolve a new-design control renderer by id, or undefined when the control
 * does not yet have a redesigned component (the caller falls back to the
 * shared control map).
 *
 * @param {string} control The control id.
 * @return {Function|undefined} The renderer.
 */
export const getShellControl = ( control ) => SHELL_CONTROLS[ control ];

export default SHELL_CONTROLS;
