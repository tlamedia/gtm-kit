/**
 * Escape-hatch component registry for the `component` block type.
 *
 * Genuinely non-form UI that cannot be expressed as fields or simple content
 * blocks is rendered here by id. This is the block-level escape hatch, the
 * counterpart to control-type and SlotFill escape hatches.
 */
import { memo, useContext, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, TextareaControl } from '@wordpress/components';

import ShareAnonymousData from '../../app/organisms/share-anonymous-data';
import ImportPluginSettings from '../../app/organisms/import-plugin-settings';
import PluginInactive from '../../app/molecules/plugin-inactive';
import SettingsService from '../../services/SettingsService';
import { useSettingField } from '../../hooks';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { pasteSgtmLoader, refreshSgtmLoader } from '../../api/settings';
import { NO_AUTOFILL } from '../../constants/autofill';
import {
	describeSgtmLoaderFailure,
	describeSgtmLoaderFallback,
	getSgtmLoaderErrorReason,
} from './sgtmLoaderMessages';

/**
 * Server-provided consent admin badges, rendered as dismissable-free notices
 * above the consent sections.
 *
 * @return {JSX.Element|null} The badges, or null when none.
 */
const ConsentBadges = memo( () => {
	const badges = SettingsService.getConsentAdminBadges();
	if ( ! badges.length ) {
		return null;
	}
	return (
		<div className="gtmkit-mb-6 gtmkit-space-y-2">
			{ badges.map( ( badge ) => (
				<Notice
					key={ badge.id }
					status={ badge.severity || 'info' }
					isDismissible={ false }
				>
					{ badge.message }
				</Notice>
			) ) }
		</div>
	);
} );

/**
 * Plugin-inactive notice. Reads its plugin name and slug from block props and
 * renders only when the plugin is inactive.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.pluginName Display name.
 * @param {Object} props.pluginSlug Plugin slug to check.
 * @return {JSX.Element|null} The notice, or null when the plugin is active.
 */
const PluginInactiveNotice = ( { pluginName, pluginSlug } ) => {
	if ( SettingsService.isPluginActive( pluginSlug ) ) {
		return null;
	}
	return <PluginInactive pluginName={ pluginName } />;
};

/**
 * State what kind of site WordPress reports this install to be, and what
 * GTM Kit therefore does with the container here.
 *
 * The reported kind comes from the server, resolved by the same code the
 * frontend output gate calls. The consequence is recomposed from the live
 * value of the override so the sentence keeps up with the toggle before the
 * page is saved.
 *
 * @return {JSX.Element} The readout.
 */
const SiteKindStatus = memo( () => {
	const { type, isProduction } = SettingsService.getSiteEnvironment();
	const [ loadAnyway ] = useSettingField(
		'general',
		'load_on_non_production'
	);

	const reported = sprintf(
		// translators: %s is the kind of site WordPress reports, for example "staging", "development" or "local".
		__( 'WordPress reports this site as %s.', 'gtm-kit' ),
		type
	);

	if ( isProduction ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ reported }{ ' ' }
				{ __(
					'GTM Kit loads your Google Tag Manager container here.',
					'gtm-kit'
				) }
			</Notice>
		);
	}

	if ( loadAnyway ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ reported }{ ' ' }
				{ __(
					'GTM Kit loads your Google Tag Manager container here anyway, because the setting below is switched on, so traffic from this site is measured alongside your real traffic.',
					'gtm-kit'
				) }
			</Notice>
		);
	}

	return (
		<Notice status="info" isDismissible={ false }>
			{ reported }{ ' ' }
			{ __(
				'GTM Kit does not load your Google Tag Manager container here, so traffic from this site stays out of your analytics and advertising audiences. The data layer is still built, so you can see what would be sent from a live site.',
				'gtm-kit'
			) }
		</Notice>
	);
} );

/**
 * Show which loader the pages use while "Get the loader from Stape" is on,
 * with a way to refresh it or to paste the code Stape shows instead.
 *
 * Reads the saved toggle rather than the live one, because the loader is
 * fetched only when the settings are saved.
 *
 * @return {JSX.Element|null} The readout, or null when the toggle is off.
 */
const SgtmLoaderStatus = () => {
	const { fetchedSettings, sgtmLoader, setSgtmLoader } =
		useContext( SettingsDataContext );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ showPaste, setShowPaste ] = useState( false );
	const [ code, setCode ] = useState( '' );

	if ( ! fetchedSettings?.general?.sgtm_stape_issued_loader ) {
		return null;
	}

	// Until a save, refresh or paste reports back, the page-load state applies.
	const state = sgtmLoader || SettingsService.getSgtmLoader();
	const date = state.fetchedAt
		? new Date( state.fetchedAt * 1000 ).toLocaleString()
		: '';

	const run = async ( request ) => {
		setIsBusy( true );

		try {
			const response = await request();

			setSgtmLoader( response.data );

			if ( [ 'fetched', 'stored' ].includes( response.data?.status ) ) {
				setShowPaste( false );
				setCode( '' );
			}
		} catch ( error ) {
			setSgtmLoader( {
				...state,
				status: 'failed',
				reason: getSgtmLoaderErrorReason( error ),
			} );
		} finally {
			setIsBusy( false );
		}
	};

	let current = __(
		'Your pages use the standard loader. Refresh the loader to get the one Stape issues.',
		'gtm-kit'
	);

	if ( state.source === 'api' ) {
		current = sprintf(
			// translators: %s is the date and time the loader was fetched.
			__(
				'Your pages use the loader Stape issued, fetched %s.',
				'gtm-kit'
			),
			date
		);
	} else if ( state.source === 'pasted' ) {
		current = sprintf(
			// translators: %s is the date and time the pasted loader was stored.
			__( 'Your pages use the loader you pasted on %s.', 'gtm-kit' ),
			date
		);
	}

	const failed = state.status === 'failed';

	return (
		<div className="gtmkit-mb-6 gtmkit-space-y-3">
			{ failed && (
				<Notice status="warning" isDismissible={ false }>
					{ describeSgtmLoaderFailure( state.reason ) }{ ' ' }
					{ describeSgtmLoaderFallback( state.source ) }
				</Notice>
			) }
			{ state.status === 'throttled' && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'The loader was refreshed less than a minute ago. Try again shortly.',
						'gtm-kit'
					) }
				</Notice>
			) }
			{ state.status === 'unavailable' && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Fill in and save the container ID, the sGTM container domain and the container identifier first.',
						'gtm-kit'
					) }
				</Notice>
			) }
			<Notice
				status={
					[ 'fetched', 'stored' ].includes( state.status )
						? 'success'
						: 'info'
				}
				isDismissible={ false }
			>
				{ current }
			</Notice>
			<div className="gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-3">
				<Button
					variant="secondary"
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ () => run( refreshSgtmLoader ) }
				>
					{ __( 'Refresh loader', 'gtm-kit' ) }
				</Button>
				<Button
					variant="link"
					onClick={ () => setShowPaste( ! showPaste ) }
				>
					{ __( 'Paste the code instead', 'gtm-kit' ) }
				</Button>
			</div>
			{ ( showPaste || failed ) && (
				<div className="gtmkit-space-y-2">
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Code from Stape', 'gtm-kit' ) }
						help={ __(
							'Paste the Google Tag Manager code Stape shows for your container. GTM Kit reads the loader address from it and never adds the code itself to your pages.',
							'gtm-kit'
						) }
						value={ code }
						onChange={ setCode }
						rows={ 6 }
						{ ...NO_AUTOFILL }
					/>
					<Button
						variant="secondary"
						isBusy={ isBusy }
						disabled={ isBusy || code.trim() === '' }
						onClick={ () => run( () => pasteSgtmLoader( code ) ) }
					>
						{ __( 'Use this code', 'gtm-kit' ) }
					</Button>
				</div>
			) }
		</div>
	);
};

const COMPONENTS = {
	'share-anonymous-data': ShareAnonymousData,
	'import-plugin-settings': ImportPluginSettings,
	'consent-badges': ConsentBadges,
	'plugin-inactive': PluginInactiveNotice,
	'site-kind-status': SiteKindStatus,
	'sgtm-loader-status': SgtmLoaderStatus,
};

/**
 * Resolve an escape-hatch component by id.
 *
 * @param {string} id The block's `component` id.
 * @return {Function|undefined} The component.
 */
export const getComponent = ( id ) => COMPONENTS[ id ];

export default COMPONENTS;
