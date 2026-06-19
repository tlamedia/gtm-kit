/**
 * Control-type registry.
 *
 * Maps a field's `control` id to a renderer. Each renderer receives the field
 * schema plus a resolved `disabled` flag and delegates to the existing setting
 * atoms, so behaviour matches the bespoke pages exactly. Add-ons can extend
 * this map at runtime; this is the field-level escape hatch.
 */
import { __ } from '@wordpress/i18n';

import TextSetting from '../../app/atoms/text-setting';
import ToggleSetting from '../../app/atoms/toggle-setting';
import RadioSetting from '../../app/atoms/radio-setting';
import CheckboxSetting from '../../app/atoms/checkbox-setting';
import SelectSetting from '../../app/atoms/select-setting';
import PageSelectSetting from '../../app/atoms/page-select-setting';
import RegionCodesSetting from '../../app/atoms/region-codes-setting';
import ExcludedUrlPatternsSetting from '../../app/atoms/excluded-url-patterns-setting';
import CmpAttributesSetting from '../../app/atoms/cmp-attributes-setting';
import GtmIdHelpPopup from '../../app/atoms/gtm-id-help-popup';
import SettingsService from '../../services/SettingsService';
import { getGoogleBusinessVerticals } from '../../app/utils/get-gbv';
import { getTransform } from '../transforms';

/**
 * Split a `group.key` storage identity into its parts.
 *
 * @param {string} key Dotted storage identity.
 * @return {{group: string, name: string}} The option group and key name.
 */
export const parseKey = ( key ) => {
	const dot = key.indexOf( '.' );
	return {
		group: key.slice( 0, dot ),
		name: key.slice( dot + 1 ),
	};
};

/**
 * Resolve a field's `help` into something an atom can render. A known id maps
 * to bespoke help UI; anything else passes through verbatim.
 *
 * @param {Object} field The field schema.
 * @return {*} Help content.
 */
const resolveHelp = ( field ) => {
	if ( field.help === 'gtm-id-help' ) {
		return (
			<div className="gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1">
				<span>
					{ __(
						'Find your Container ID in Google Tag Manager',
						'gtm-kit'
					) }
				</span>
				<GtmIdHelpPopup />
			</div>
		);
	}
	return field.help;
};

/**
 * Resolve list items for choice controls from a named source.
 *
 * @param {string} [source] The field's `itemsSource`.
 * @return {Array} Items, or an empty array.
 */
export const resolveItems = ( source ) => {
	switch ( source ) {
		case 'userRoles':
			return SettingsService.getUserRoles();
		case 'pageOptions':
			return SettingsService.getPageOptions();
		default:
			return [];
	}
};

/**
 * Resolve options for select controls: an inline list, or a named dynamic
 * source (taxonomies, Google Business verticals).
 *
 * @param {Object} field The field schema.
 * @return {Array} Option objects.
 */
export const resolveOptions = ( field ) => {
	switch ( field.optionsSource ) {
		case 'taxonomyOptions':
			return SettingsService.getTaxonomyOptions();
		case 'googleBusinessVerticals':
			return getGoogleBusinessVerticals;
		case 'pageOptions':
			return SettingsService.getPageOptions();
		default:
			return field.options || [];
	}
};

const TextControl = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<TextSetting
			title={ field.label }
			placeholder={ field.placeholder }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			notificationId={ field.notificationId }
			transform={ getTransform( field.transform ) }
			isDisabled={ disabled }
		/>
	);
};

const NumberControl = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<TextSetting
			title={ field.label }
			placeholder={ field.placeholder }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			type="number"
			min={ field.min }
			max={ field.max }
			step={ field.step }
			isDisabled={ disabled }
		/>
	);
};

const Toggle = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<ToggleSetting
			title={ field.label }
			label={ field.description }
			optionGroup={ group }
			optionName={ name }
			notificationId={ field.notificationId }
			disabled={ disabled }
		/>
	);
};

const Radio = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<RadioSetting
			title={ field.label }
			options={ field.options }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			valueType={ field.valueType }
			defaultValue={ field.defaultValue }
			disabled={ disabled }
		/>
	);
};

const CheckboxGroup = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<CheckboxSetting
			title={ field.label }
			help={ resolveHelp( field ) }
			items={ resolveItems( field.itemsSource ) }
			optionGroup={ group }
			optionName={ name }
			disabled={ disabled }
		/>
	);
};

const Select = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<SelectSetting
			title={ field.label }
			options={ resolveOptions( field ) }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			notSet={ field.notSet }
			disabled={ disabled }
		/>
	);
};

const PageSelect = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<PageSelectSetting
			title={ field.label }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			notSet={ field.notSet }
			disabled={ disabled }
		/>
	);
};

const RegionCodes = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<RegionCodesSetting
			title={ field.label }
			placeholder={ field.placeholder }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
			isDisabled={ disabled }
		/>
	);
};

const ExcludedUrlPatterns = ( { field } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<ExcludedUrlPatternsSetting
			title={ field.label }
			help={ resolveHelp( field ) }
			optionGroup={ group }
			optionName={ name }
		/>
	);
};

const CmpAttributes = ( { field, disabled } ) => {
	const { group, name } = parseKey( field.key );
	return (
		<CmpAttributesSetting
			optionGroup={ group }
			optionName={ name }
			disabled={ disabled }
		/>
	);
};

const CONTROLS = {
	text: TextControl,
	number: NumberControl,
	toggle: Toggle,
	radio: Radio,
	'checkbox-group': CheckboxGroup,
	select: Select,
	'page-select': PageSelect,
	'region-codes': RegionCodes,
	'excluded-url-patterns': ExcludedUrlPatterns,
	'cmp-attributes': CmpAttributes,
};

/**
 * Resolve a control renderer by id.
 *
 * @param {string} control The field's `control` id.
 * @return {Function|undefined} The renderer component.
 */
export const getControl = ( control ) => CONTROLS[ control ];

export default CONTROLS;
