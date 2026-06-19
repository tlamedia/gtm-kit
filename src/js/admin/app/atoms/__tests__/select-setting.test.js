/**
 * Covers the select control's display fallback: when a select-backed option has
 * never been saved, the declared `defaultValue` renders as selected instead of
 * a blank control, and a stored value still takes precedence. Exercised through
 * the registry control (`getControl('select')`) so the field -> atom forwarding
 * of `defaultValue` / `valueType` is covered too. The fallback is display only:
 * it never writes the default back to the store.
 */

/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';

import { SettingsDataContext } from '../../../context/SettingsDataContext';
import { getControl } from '../../../registry/controls';

const Select = getControl( 'select' );

const STRING_FIELD = {
	key: 'general.consent_gating_mode',
	control: 'select',
	label: 'Gating mode',
	valueType: 'string',
	defaultValue: 'always_load',
	options: [
		{ label: 'Always load (default)', value: 'always_load' },
		{ label: 'Weak block', value: 'weak_block' },
		{ label: 'Strong block', value: 'strong_block' },
	],
};

const INTEGER_FIELD = {
	key: 'integrations.cf7_load_js',
	control: 'select',
	label: 'Load JavaScript',
	valueType: 'integer',
	defaultValue: 1,
	options: [
		{ label: 'Only where registered (recommended)', value: 1 },
		{ label: 'On all pages', value: 2 },
	],
};

function renderField( field, settings = {} ) {
	const updateStateSettings = jest.fn();
	render(
		<SettingsDataContext.Provider value={ { settings, updateStateSettings } }>
			<Select field={ field } disabled={ false } />
		</SettingsDataContext.Provider>
	);
	return { updateStateSettings };
}

describe( 'select control default-value display fallback', () => {
	it( 'selects the string default when the option was never saved', () => {
		renderField( STRING_FIELD, { general: {} } );

		expect( screen.getByRole( 'combobox' ) ).toHaveValue( 'always_load' );
		// SelectControl emits its legacy-size/margin deprecation notices on the
		// first render in this file; acknowledge them so jest-console passes.
		expect( console ).toHaveWarned();
	} );

	it( 'selects the integer default when the option was never saved', () => {
		renderField( INTEGER_FIELD, { integrations: {} } );

		expect( screen.getByRole( 'combobox' ) ).toHaveValue( '1' );
	} );

	it( 'lets a stored value take precedence over the default', () => {
		renderField( STRING_FIELD, {
			general: { consent_gating_mode: 'strong_block' },
		} );

		expect( screen.getByRole( 'combobox' ) ).toHaveValue( 'strong_block' );
	} );

	it( 'does not write the default back to the store on render', () => {
		const { updateStateSettings } = renderField( STRING_FIELD, {
			general: {},
		} );

		expect( updateStateSettings ).not.toHaveBeenCalled();
	} );

	it( 'writes the chosen value when the user changes the selection', () => {
		const { updateStateSettings } = renderField( STRING_FIELD, {
			general: {},
		} );

		fireEvent.change( screen.getByRole( 'combobox' ), {
			target: { value: 'weak_block' },
		} );

		expect( updateStateSettings ).toHaveBeenCalledWith(
			'general',
			'consent_gating_mode',
			'weak_block'
		);
	} );
} );
