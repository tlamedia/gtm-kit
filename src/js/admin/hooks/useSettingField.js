/**
 * Custom hook for managing a single setting field
 *
 * This hook simplifies working with individual setting fields by providing
 * a tuple of [value, setValue] similar to useState, but backed by the
 * SettingsDataContext.
 *
 * @param {string} group - Settings group (general, integrations, premium)
 * @param {string} key   - Setting key within the group
 * @return {Array} Tuple of [value, setValue function]
 *
 * @example
 * // Before (with prop drilling):
 * const { useSettings, updateStateSettings } = useContext(SettingsDataContext);
 * <TextControl
 *   value={useSettings.general.gtm_id}
 *   onChange={(val) => updateStateSettings('general', 'gtm_id', val)}
 * />
 *
 * // After (with custom hook):
 * const [gtmId, setGtmId] = useSettingField('general', 'gtm_id');
 * <TextControl value={gtmId} onChange={setGtmId} />
 *
 * @since Phase 2 Enhancement (2026-01-27)
 */

import { useContext, useCallback } from '@wordpress/element';
import { SettingsDataContext } from '../context/SettingsDataContext';

export const useSettingField = ( group, key ) => {
	const { settings, updateStateSettings } = useContext( SettingsDataContext );

	// Get the current value from settings
	const value = settings?.[ group ]?.[ key ];

	// Create a stable setter function
	const setValue = useCallback(
		( newValue ) => {
			updateStateSettings( group, key, newValue );
		},
		[ group, key, updateStateSettings ]
	);

	return [ value, setValue ];
};

export default useSettingField;
