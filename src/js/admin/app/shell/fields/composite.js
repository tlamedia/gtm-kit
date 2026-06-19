/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

/*Hooks / registry*/
import Pill from './Pill';
import RowLabel from './RowLabel';
import ShellEventDeferral from './event-deferral';
import { BOX } from './controls';
import { useSettingField } from '../../../hooks/useSettingField';
import { parseKey, resolveItems } from '../../../registry/controls';

const LINK =
	'gtmkit-text-sm gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline disabled:gtmkit-opacity-50';

const CMP_DEFAULT = {
	cookiebot: false,
	iubenda: false,
	cookieyes: false,
	custom: { name: '', value: '' },
};

const CMP_TOGGLES = [
	{
		key: 'cookiebot',
		label: 'Cookiebot',
		attribute: 'data-cookieconsent="ignore"',
	},
	{ key: 'iubenda', label: 'Iubenda', attribute: 'data-cmp-ab="1"' },
	{
		key: 'cookieyes',
		label: 'CookieYes',
		attribute: 'data-cookie-consent="ignore"',
	},
];

/**
 * Build the read-only preview of the rendered script tag's attribute set.
 *
 * @param {Object} cmp Current cmp_script_attributes value.
 * @return {string} Space-separated attribute pairs.
 */
const buildAttributePreview = ( cmp ) => {
	const attrs = [ 'data-cfasync="false"', 'data-nowprocket=""' ];
	if ( cmp?.cookiebot ) {
		attrs.push( 'data-cookieconsent="ignore"' );
	}
	if ( cmp?.iubenda ) {
		attrs.push( 'data-cmp-ab="1"' );
	}
	if ( cmp?.cookieyes ) {
		attrs.push( 'data-cookie-consent="ignore"' );
	}
	const customName = ( cmp?.custom?.name || '' ).replace(
		/[^a-zA-Z0-9_-]/g,
		''
	);
	if ( customName ) {
		attrs.push( `${ customName }="${ cmp?.custom?.value ?? '' }"` );
	}
	return attrs.join( ' ' );
};

/**
 * CMP script attributes: known-CMP toggle rows, a custom name/value pair, and a
 * read-only preview of the resulting script-tag attributes.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const ShellCmpAttributes = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );

	const cmp = {
		...CMP_DEFAULT,
		...( value || {} ),
		custom: { ...CMP_DEFAULT.custom, ...( value?.custom || {} ) },
	};
	const update = ( patch ) => setValue( { ...cmp, ...patch } );
	const updateCustom = ( patch ) =>
		setValue( { ...cmp, custom: { ...cmp.custom, ...patch } } );

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-6">
			{ CMP_TOGGLES.map( ( item ) => (
				<div
					key={ item.key }
					className="gtmkit-flex gtmkit-items-start gtmkit-gap-3"
				>
					<div className="gtmkit-flex gtmkit-h-5 gtmkit-shrink-0 gtmkit-items-center">
						<Pill
							on={ ! disabled && Boolean( cmp[ item.key ] ) }
							disabled={ disabled }
							label={ item.label }
							onClick={ () =>
								update( { [ item.key ]: ! cmp[ item.key ] } )
							}
						/>
					</div>
					<RowLabel
						label={ item.label }
						description={
							<>
								{ __( 'Adds', 'gtm-kit' ) }{ ' ' }
								<code>{ item.attribute }</code>{ ' ' }
								{ __( 'to GTM Kit scripts.', 'gtm-kit' ) }
							</>
						}
					/>
				</div>
			) ) }

			<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
				<RowLabel
					label={ __( 'Custom attribute', 'gtm-kit' ) }
					description={ __(
						'Name and value added as an attribute. Leave the name empty to omit it.',
						'gtm-kit'
					) }
				/>
				<div className="gtmkit-flex gtmkit-gap-2">
					<input
						type="text"
						className={ `${ BOX } gtmkit-w-[150px]` }
						placeholder="data-my-cmp"
						value={ cmp.custom.name }
						disabled={ disabled }
						onChange={ ( e ) =>
							updateCustom( { name: e.target.value } )
						}
					/>
					<input
						type="text"
						className={ `${ BOX } gtmkit-w-[110px]` }
						placeholder="ignore"
						value={ cmp.custom.value }
						disabled={ disabled }
						onChange={ ( e ) =>
							updateCustom( { value: e.target.value } )
						}
					/>
				</div>
			</div>

			<div>
				<p className="gtmkit-mb-1 gtmkit-text-xs gtmkit-font-medium gtmkit-text-text-muted">
					{ __( 'Resulting script tag attributes', 'gtm-kit' ) }
				</p>
				<pre className="gtmkit-overflow-x-auto gtmkit-rounded-sm gtmkit-bg-chip-bg gtmkit-p-2 gtmkit-text-[11px]">
					<code>{ buildAttributePreview( cmp ) }</code>
				</pre>
			</div>
		</div>
	);
};

const PATTERN_MAX_LENGTH = 500;
const PATTERN_LIST_MAX = 100;

/**
 * Probe a regex pattern client-side using the matcher's flags.
 *
 * @param {string} pattern Raw regex pattern.
 * @return {string|null} Error message, or null when valid.
 */
const probeRegexPattern = ( pattern ) => {
	try {
		// eslint-disable-next-line no-new
		new RegExp( pattern, 'i' );
		return null;
	} catch ( err ) {
		return err?.message || __( 'Invalid regular expression.', 'gtm-kit' );
	}
};

/**
 * Strip scheme/host so a pasted full URL becomes a path-only glob pattern.
 *
 * @param {string} pattern Raw pattern.
 * @return {string} Path-only pattern.
 */
const extractPathFromUrlPattern = ( pattern ) => {
	if ( ! pattern ) {
		return '';
	}
	if ( ! /^https?:\/\//i.test( pattern ) && ! pattern.startsWith( '//' ) ) {
		return pattern;
	}
	try {
		const parsed = new URL(
			pattern.startsWith( '//' ) ? `https:${ pattern }` : pattern
		);
		return parsed.pathname || '/';
	} catch ( _ ) {
		return pattern;
	}
};

/**
 * Repeatable URL-exclusion patterns. Each row is `{ pattern, mode }`.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const ShellExcludedUrlPatterns = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const rows = useMemo(
		() => ( Array.isArray( value ) ? value : [] ),
		[ value ]
	);
	const atLimit = rows.length >= PATTERN_LIST_MAX;

	const updateRow = ( index, patch ) =>
		setValue(
			rows.map( ( row, i ) =>
				i === index ? { ...row, ...patch } : row
			)
		);
	const removeRow = ( index ) =>
		setValue( rows.filter( ( _, i ) => i !== index ) );
	const addRow = () => {
		if ( ! atLimit ) {
			setValue( [ ...rows, { pattern: '', mode: 'glob' } ] );
		}
	};

	return (
		<div>
			{ rows.length === 0 && (
				<p className="gtmkit-mb-3 gtmkit-text-xs gtmkit-italic gtmkit-text-text-muted">
					{ __(
						'No patterns configured. GTM Kit loads on every frontend page.',
						'gtm-kit'
					) }
				</p>
			) }

			<div className="gtmkit-space-y-2">
				{ rows.map( ( row, index ) => {
					const mode = row?.mode === 'regex' ? 'regex' : 'glob';
					const pattern =
						typeof row?.pattern === 'string' ? row.pattern : '';
					const regexError =
						mode === 'regex' && pattern !== ''
							? probeRegexPattern( pattern )
							: null;

					return (
						<div
							key={ index }
							className="gtmkit-flex gtmkit-items-start gtmkit-gap-2"
						>
							<div className="gtmkit-flex-1">
								<input
									type="text"
									className={ `${ BOX } gtmkit-w-full` }
									value={ pattern }
									maxLength={ PATTERN_MAX_LENGTH }
									disabled={ disabled }
									placeholder={
										mode === 'regex'
											? '^/api/v\\d+/'
											: '/checkout-embed/*'
									}
									onChange={ ( e ) =>
										updateRow( index, {
											pattern: e.target.value,
										} )
									}
									onBlur={ ( e ) => {
										if ( mode !== 'glob' ) {
											return;
										}
										const cleaned =
											extractPathFromUrlPattern(
												e.target.value
											);
										if ( cleaned !== e.target.value ) {
											updateRow( index, {
												pattern: cleaned,
											} );
										}
									} }
								/>
								{ regexError && (
									<p className="gtmkit-mb-0 gtmkit-mt-1 gtmkit-text-xs gtmkit-text-red-600">
										{ __(
											'Invalid regular expression:',
											'gtm-kit'
										) }{ ' ' }
										{ regexError }
									</p>
								) }
							</div>
							<select
								className={ `${ BOX } gtmkit-w-[110px]` }
								value={ mode }
								disabled={ disabled }
								onChange={ ( e ) =>
									updateRow( index, {
										mode:
											e.target.value === 'regex'
												? 'regex'
												: 'glob',
									} )
								}
							>
								<option value="glob">
									{ __( 'Glob', 'gtm-kit' ) }
								</option>
								<option value="regex">
									{ __( 'Regex', 'gtm-kit' ) }
								</option>
							</select>
							<button
								type="button"
								className="gtmkit-h-[34px] gtmkit-px-3 gtmkit-text-sm gtmkit-text-red-600 hover:gtmkit-underline disabled:gtmkit-opacity-50"
								disabled={ disabled }
								onClick={ () => removeRow( index ) }
								aria-label={ __( 'Remove pattern', 'gtm-kit' ) }
							>
								{ __( 'Remove', 'gtm-kit' ) }
							</button>
						</div>
					);
				} ) }
			</div>

			<button
				type="button"
				className={ `${ LINK } gtmkit-mt-3` }
				disabled={ disabled || atLimit }
				onClick={ addRow }
			>
				{ __( 'Add pattern +', 'gtm-kit' ) }
			</button>
			{ atLimit && (
				<span className="gtmkit-ml-3 gtmkit-text-xs gtmkit-text-text-muted">
					{ __( 'Maximum of 100 patterns reached.', 'gtm-kit' ) }
				</span>
			) }
		</div>
	);
};

/**
 * Checkbox group: the label and help on top, then the items as an inline grid
 * of checkboxes (used for the user-roles exclusion list).
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const ShellCheckboxGroup = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	const [ value, setValue ] = useSettingField( group, name );
	const items = resolveItems( field.itemsSource );
	const selected = Array.isArray( value ) ? value : [];

	const toggle = ( role, checked ) =>
		setValue(
			checked
				? [ ...selected, role ]
				: selected.filter( ( item ) => item !== role )
		);

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-4">
			<RowLabel
				label={ field.label }
				description={
					<span className="gtmkit-whitespace-nowrap">
						{ field.help }
					</span>
				}
			/>
			<div className="gtmkit-grid gtmkit-grid-cols-2 gtmkit-gap-x-6 gtmkit-gap-y-3">
				{ items.map( ( item ) => {
					const inputId = `gtmkit-role-${ item.role }`;
					return (
						<div
							key={ item.role }
							className="gtmkit-flex gtmkit-items-center gtmkit-gap-2"
						>
							<input
								id={ inputId }
								type="checkbox"
								checked={ selected.includes( item.role ) }
								disabled={ disabled }
								onChange={ ( e ) =>
									toggle( item.role, e.target.checked )
								}
							/>
							<label
								htmlFor={ inputId }
								className="gtmkit-cursor-pointer gtmkit-text-[13px] gtmkit-text-text-primary"
							>
								{ item.name }
							</label>
						</div>
					);
				} ) }
			</div>
		</div>
	);
};

const SHELL_COMPOSITE_CONTROLS = {
	'cmp-attributes': ShellCmpAttributes,
	'excluded-url-patterns': ShellExcludedUrlPatterns,
	'checkbox-group': ShellCheckboxGroup,
	'event-deferral': ShellEventDeferral,
};

/**
 * Resolve a composite (multi-row, self-laying-out) control by id.
 *
 * @param {string} control The control id.
 * @return {Function|undefined} The renderer.
 */
export const getShellCompositeControl = ( control ) =>
	SHELL_COMPOSITE_CONTROLS[ control ];

export default SHELL_COMPOSITE_CONTROLS;
