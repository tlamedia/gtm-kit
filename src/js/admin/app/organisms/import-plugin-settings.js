/**
 * Import settings from another Google Tag Manager plugin.
 *
 * Offers the same sources as the setup wizard, but at any time and over an
 * already-configured site, so the confirmation step spells out which settings
 * the import will replace before anything is written.
 */
import {
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { Button, Notice, RadioControl, Spinner } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import { isEqual } from 'lodash';

import { SettingsDataContext } from '../../context/SettingsDataContext';
import SettingsService from '../../services/SettingsService';
import { getAllFields } from '../../registry/assemble';

const IMPORTED_GROUPS = [ 'general', 'integrations' ];

/**
 * Human labels for settings keys, taken from the field registry so the
 * confirmation list always names fields the way the settings screens do.
 *
 * @return {Object} Map of `group.key` to label.
 */
const getFieldLabels = () => {
	const labels = {};
	getAllFields().forEach( ( field ) => {
		if ( field.key && field.label ) {
			// Field labels sit next to their control and several end in a
			// colon, which reads wrong in a list.
			labels[ field.key ] = field.label.replace( /\s*:\s*$/, '' );
		}
	} );
	return labels;
};

/**
 * The settings an import would actually change, as display labels.
 *
 * Values equal to the current setting are left out, so the list never promises
 * a change that will not happen.
 *
 * @param {Object} source   The selected source's import payload.
 * @param {Object} settings The current settings state.
 * @return {string[]} Labels of the settings that will be overwritten.
 */
const getOverwrittenLabels = ( source, settings ) => {
	const labels = getFieldLabels();
	const changed = [];

	IMPORTED_GROUPS.forEach( ( group ) => {
		Object.entries( source?.[ group ] || {} ).forEach(
			( [ key, value ] ) => {
				const current = settings?.[ group ]?.[ key ];

				if ( isEqual( current, value ) ) {
					return;
				}

				changed.push( labels[ `${ group }.${ key }` ] || key );
			}
		);
	} );

	return changed;
};

const ImportPluginSettings = () => {
	const { settings, importSettings, updateSettings, isPending, hasError } =
		useContext( SettingsDataContext );

	const sources = useMemo(
		() => SettingsService.getInstallData().import_data || {},
		[]
	);
	const sourceKeys = Object.keys( sources );

	const [ selected, setSelected ] = useState( sourceKeys[ 0 ] );
	const [ isConfirming, setIsConfirming ] = useState( false );
	const [ isImported, setIsImported ] = useState( false );

	// The import merges into settings state via a reducer, so the save has to
	// wait for the next render or it would send the pre-import settings.
	const saveAfterImport = useRef( false );

	useEffect( () => {
		if ( ! saveAfterImport.current ) {
			return;
		}
		saveAfterImport.current = false;
		updateSettings();
		setIsImported( true );
		// `updateSettings` is rebuilt on every render; keying on it would loop.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ settings ] );

	if ( ! sourceKeys.length ) {
		return (
			<p className="gtmkit-text-color-grey">
				{ __(
					'No settings from another Google Tag Manager plugin were found on this site.',
					'gtm-kit'
				) }
			</p>
		);
	}

	const source = sources[ selected ] || {};
	const sourceName = source.name || selected;
	const overwritten = getOverwrittenLabels( source, settings );
	const skippedContainers = Math.max(
		0,
		( source.container_count || 0 ) - 1
	);

	const options = sourceKeys.map( ( key ) => ( {
		value: key,
		label: sources[ key ].name || key,
	} ) );

	const startOver = () => {
		setIsConfirming( false );
		setIsImported( false );
	};

	if ( isImported ) {
		return (
			<Notice
				status={ hasError ? 'error' : 'success' }
				isDismissible={ false }
			>
				{ hasError
					? __(
							'The settings could not be saved. Please try again.',
							'gtm-kit'
					  )
					: sprintf(
							/* translators: %s: name of the plugin the settings were imported from. */
							__( 'Settings imported from %s.', 'gtm-kit' ),
							sourceName
					  ) }
				<Button
					variant="link"
					className="gtmkit-ml-4"
					onClick={ startOver }
				>
					{ __( 'Import from another plugin', 'gtm-kit' ) }
				</Button>
			</Notice>
		);
	}

	if ( isConfirming ) {
		return (
			<div className="gtmkit-settings-field-wrap">
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: name of the plugin the settings are imported from. */
						__(
							'Importing from %s replaces your current GTM Kit settings with the ones below. This cannot be undone.',
							'gtm-kit'
						),
						sourceName
					) }
				</Notice>

				{ overwritten.length ? (
					<>
						<p className="gtmkit-mt-4 gtmkit-mb-2 gtmkit-font-bold gtmkit-text-color-heading">
							{ __(
								'These settings will be replaced:',
								'gtm-kit'
							) }
						</p>
						<ul className="gtmkit-list-disc gtmkit-ml-6 gtmkit-mb-4 gtmkit-text-color-grey">
							{ overwritten.map( ( label ) => (
								<li key={ label }>{ label }</li>
							) ) }
						</ul>
					</>
				) : (
					<p className="gtmkit-my-4 gtmkit-text-color-grey">
						{ __(
							'Your current settings already match this plugin. Nothing will change.',
							'gtm-kit'
						) }
					</p>
				) }

				{ skippedContainers > 0 && (
					<Notice status="info" isDismissible={ false }>
						{ sprintf(
							/* translators: %d: number of additional containers found. */
							_n(
								'%d additional container was found and will not be imported. GTM Kit uses one container.',
								'%d additional containers were found and will not be imported. GTM Kit uses one container.',
								skippedContainers,
								'gtm-kit'
							),
							skippedContainers
						) }
					</Notice>
				) }

				<div className="gtmkit-flex gtmkit-gap-4 gtmkit-mt-6">
					<Button
						variant="primary"
						disabled={ isPending || ! overwritten.length }
						onClick={ () => {
							saveAfterImport.current = true;
							importSettings( source );
						} }
					>
						{ __( 'Import and save', 'gtm-kit' ) }
						{ isPending ? <Spinner /> : '' }
					</Button>
					<Button variant="secondary" onClick={ startOver }>
						{ __( 'Cancel', 'gtm-kit' ) }
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="gtmkit-settings-field-wrap">
			<RadioControl
				label={ __( 'Import settings from', 'gtm-kit' ) }
				help={ __(
					'Select the plugin you want to import settings from.',
					'gtm-kit'
				) }
				selected={ selected }
				options={ options }
				onChange={ ( value ) => setSelected( value ) }
			/>
			<Button
				variant="primary"
				className="gtmkit-mt-4"
				onClick={ () => setIsConfirming( true ) }
			>
				{ __( 'Continue', 'gtm-kit' ) }
			</Button>
		</div>
	);
};

export default ImportPluginSettings;
