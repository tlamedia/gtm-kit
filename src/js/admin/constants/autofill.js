/**
 * Attributes that keep password managers and browser autofill out of a
 * settings input.
 *
 * Every settings field is a controlled input, so a value a password manager
 * fills in lands in the settings state exactly as if it had been typed, and
 * the next save stores it, however unrelated the setting being saved. Clearing
 * the field does not help while the manager keeps filling it back in.
 *
 * `autoComplete="off"` on its own is ignored by most password managers, so
 * each one's own opt-out is set alongside it. Spread onto every text, search,
 * number and password input. Checkboxes and radios are not autofill targets
 * and do not take it.
 *
 * @type {Readonly<Object<string, string>>}
 */
export const NO_AUTOFILL = Object.freeze( {
	autoComplete: 'off',
	'data-1p-ignore': 'true',
	'data-lpignore': 'true',
	'data-bwignore': 'true',
	'data-form-type': 'other',
} );
