/*WordPress*/
import { __ } from '@wordpress/i18n';
import { memo } from '@wordpress/element';
import { BaseControl, TextControl, ToggleControl } from '@wordpress/components';

/*Custom Hooks*/
import { useSettingField } from '../../hooks/useSettingField';

const CMP_ATTRIBUTES_DEFAULT = {
	cookiebot: false,
	iubenda: false,
	cookieyes: false,
	custom: { name: '', value: '' },
};

const CMP_TOGGLES = [
	{
		key: 'cookiebot',
		label: 'Cookiebot',
		// translators: %s is an HTML attribute string rendered in monospace.
		description: __( 'Adds %s to GTM Kit scripts.', 'gtm-kit' ),
		attribute: 'data-cookieconsent="ignore"',
	},
	{
		key: 'iubenda',
		label: 'Iubenda',
		// translators: %s is an HTML attribute string rendered in monospace.
		description: __( 'Adds %s to GTM Kit scripts.', 'gtm-kit' ),
		attribute: 'data-cmp-ab="1"',
	},
	{
		key: 'cookieyes',
		label: 'CookieYes',
		// translators: %s is an HTML attribute string rendered in monospace.
		description: __( 'Adds %s to GTM Kit scripts.', 'gtm-kit' ),
		attribute: 'data-cookie-consent="ignore"',
	},
];

/**
 * Format the rendered <script> tag's attribute set for the read-only preview.
 * Mirrors the PHP build in Frontend::set_inline_script_attributes so the admin
 * sees exactly what the frontend will emit.
 *
 * @param {Object} cmp Current cmp_script_attributes setting value.
 * @return {string} Space-separated attribute pairs.
 */
function buildAttributePreview( cmp ) {
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
		const customValue = cmp?.custom?.value ?? '';
		attrs.push( `${ customName }="${ customValue }"` );
	}
	return attrs.join( ' ' );
}

/**
 * Consent management platform script-attribute control. Manages the nested
 * `cmp_script_attributes` option: known-CMP toggles, a custom name/value pair,
 * and a read-only preview of the resulting script-tag attributes.
 *
 * @param {Object}  props             Component props.
 * @param {string}  props.optionGroup Storage group.
 * @param {string}  props.optionName  Storage key.
 * @param {boolean} props.disabled    Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const CmpAttributesSetting = memo(
	( {
		optionGroup = 'general',
		optionName = 'cmp_script_attributes',
		disabled = false,
	} ) => {
		const [ value, setValue ] = useSettingField( optionGroup, optionName );

		const cmpAttributes = {
			...CMP_ATTRIBUTES_DEFAULT,
			...( value || {} ),
			custom: {
				...CMP_ATTRIBUTES_DEFAULT.custom,
				...( value?.custom || {} ),
			},
		};

		const updateCmpAttribute = ( patch ) =>
			setValue( { ...cmpAttributes, ...patch } );

		const updateCmpCustom = ( patch ) =>
			setValue( {
				...cmpAttributes,
				custom: { ...cmpAttributes.custom, ...patch },
			} );

		return (
			<>
				{ CMP_TOGGLES.map( ( cmp ) => (
					<div
						key={ cmp.key }
						className="gtmkit-settings-field-wrap gtmkit-py-2"
					>
						<BaseControl
							label={ cmp.label }
							id={ `cmp-attr-${ cmp.key }` }
						>
							<ToggleControl
								label={
									<>
										{ cmp.description.split( '%s' )[ 0 ] }
										<code className="gtmkit-text-xs">
											{ cmp.attribute }
										</code>
										{ cmp.description.split( '%s' )[ 1 ] }
									</>
								}
								checked={ Boolean( cmpAttributes[ cmp.key ] ) }
								onChange={ ( next ) =>
									updateCmpAttribute( { [ cmp.key ]: next } )
								}
								disabled={ disabled }
							/>
						</BaseControl>
					</div>
				) ) }

				<h4 className="gtmkit-font-bold gtmkit-pt-6">
					{ __( 'Custom attribute', 'gtm-kit' ) }
				</h4>
				<p className="gtmkit-mb-2">
					{ __(
						'Use this to add an attribute that is not in the list above. Names accept letters, digits, underscore, and dash; everything else is stripped on save. Leave the name empty to omit the attribute.',
						'gtm-kit'
					) }
				</p>
				<div className="gtmkit-flex gtmkit-gap-4">
					<TextControl
						label={ __( 'Name', 'gtm-kit' ) }
						value={ cmpAttributes.custom.name }
						onChange={ ( next ) =>
							updateCmpCustom( { name: next } )
						}
						placeholder="data-my-cmp"
						disabled={ disabled }
					/>
					<TextControl
						label={ __( 'Value', 'gtm-kit' ) }
						value={ cmpAttributes.custom.value }
						onChange={ ( next ) =>
							updateCmpCustom( { value: next } )
						}
						placeholder="ignore"
						disabled={ disabled }
					/>
				</div>

				<h4 className="gtmkit-font-bold gtmkit-pt-6">
					{ __( 'Resulting script tag attributes', 'gtm-kit' ) }
				</h4>
				<pre className="gtmkit-bg-gray-100 gtmkit-p-3 gtmkit-rounded gtmkit-text-xs gtmkit-overflow-x-auto">
					<code>{ buildAttributePreview( cmpAttributes ) }</code>
				</pre>
			</>
		);
	}
);

export default CmpAttributesSetting;
