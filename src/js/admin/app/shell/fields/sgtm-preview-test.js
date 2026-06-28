/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';

/*Hooks / registry*/
import FieldTags from './FieldTags';
import RowLabel from './RowLabel';
import UpsellSlot from './UpsellSlot';
import { BOX } from './controls';
import { useFeatureFlags } from '../../../hooks/useFeatureFlags';
import { isTierLocked } from '../../../registry/gating';
import SettingsService from '../../../services/SettingsService';
import {
	getWebhookPreviewStatus,
	setWebhookPreviewToken,
	clearWebhookPreviewToken,
	sendWebhookPreviewTest,
} from '../../../api/settings';

const PRIMARY_BTN =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90 disabled:gtmkit-opacity-50';

const LINK_BTN =
	'gtmkit-text-[13px] gtmkit-text-brand-primary hover:gtmkit-underline disabled:gtmkit-opacity-50';

/**
 * Per-user memory of the last event and payload source, so a debugging session
 * resumes where it left off. Falls back silently when storage is unavailable.
 */
const PREFS_KEY = 'gtmkit-sgtm-preview-prefs';

const readPrefs = () => {
	try {
		return JSON.parse( window.localStorage.getItem( PREFS_KEY ) ) || {};
	} catch ( e ) {
		return {};
	}
};

const writePrefs = ( prefs ) => {
	try {
		window.localStorage.setItem( PREFS_KEY, JSON.stringify( prefs ) );
	} catch ( e ) {
		// Storage unavailable (private mode / quota); preferences are optional.
	}
};

/**
 * Format a remaining-seconds value as a short "Xm Ys" string.
 *
 * @param {number} seconds Seconds remaining.
 * @return {string} Human-readable duration.
 */
const formatRemaining = ( seconds ) => {
	const total = Math.max( 0, Math.floor( seconds ) );
	const mins = Math.floor( total / 60 );
	const secs = total % 60;
	if ( mins > 0 ) {
		return sprintf(
			// translators: 1: minutes, 2: seconds.
			__( '%1$dm %2$ds', 'gtm-kit' ),
			mins,
			secs
		);
	}
	return sprintf(
		// translators: %d: seconds.
		__( '%ds', 'gtm-kit' ),
		secs
	);
};

const EMPTY_STATUS = {
	armed: false,
	masked_token: '',
	live_attach: false,
	expires_in: 0,
};

/**
 * sGTM Preview/Debug test send.
 *
 * Sends a server-side webhook to the configured server container with the
 * `X-Gtm-Server-Preview` header attached, so the event surfaces in the
 * container's Preview/Debug panel without placing a real order. Token state
 * lives in a short, self-expiring transient on the server; this control reads
 * and refreshes it through the Premium REST endpoints. An advanced disclosure
 * exposes the guarded, self-expiring live-traffic toggle.
 *
 * @param {Object}  props          Component props.
 * @param {Object}  props.field    Field definition.
 * @param {boolean} props.disabled Whether the control is disabled.
 * @return {JSX.Element} The control.
 */
const SgtmPreviewTest = ( { field, disabled } ) => {
	const { meetsRequiredTier } = useFeatureFlags();
	const lockedByTier = isTierLocked( field, meetsRequiredTier );

	const prefs = readPrefs();

	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( '' );
	const [ events, setEvents ] = useState( [] );
	const [ subscriptionsActive, setSubscriptionsActive ] = useState( false );
	const [ status, setStatus ] = useState( EMPTY_STATUS );

	const [ token, setToken ] = useState( '' );
	const [ event, setEvent ] = useState( prefs.event || 'purchase' );
	const [ source, setSource ] = useState( prefs.source || 'sample' );
	const [ orderId, setOrderId ] = useState( '' );
	const [ syntheticTxn, setSyntheticTxn ] = useState( true );

	const [ advancedOpen, setAdvancedOpen ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ showSnippet, setShowSnippet ] = useState( false );

	const wooActive = SettingsService.isPluginActive( 'woocommerce' );

	const applyStatus = useCallback( ( payload ) => {
		if ( payload?.status ) {
			setStatus( { ...EMPTY_STATUS, ...payload.status } );
		}
		if ( Array.isArray( payload?.events ) ) {
			setEvents( payload.events );
		}
		if ( typeof payload?.subscriptionsActive === 'boolean' ) {
			setSubscriptionsActive( payload.subscriptionsActive );
		}
	}, [] );

	useEffect( () => {
		let active = true;
		getWebhookPreviewStatus()
			.then( ( response ) => {
				if ( active && response?.success ) {
					applyStatus( response.data );
				}
			} )
			.catch( () => {
				if ( active ) {
					setLoadError(
						__(
							'Could not load the preview status. Reload the page and try again.',
							'gtm-kit'
						)
					);
				}
			} )
			.finally( () => {
				if ( active ) {
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [ applyStatus ] );

	// Persist the last event and source for the next visit.
	useEffect( () => {
		writePrefs( { event, source } );
	}, [ event, source ] );

	const visibleEvents = events.filter(
		( definition ) =>
			! definition.requiresSubscriptions || subscriptionsActive
	);

	const currentEvent = events.find( ( e ) => e.value === event );
	const canReplayOrder = Boolean( currentEvent?.order ) && wooActive;

	// An event that cannot be replayed from an order forces the sample source.
	const effectiveSource =
		source === 'order' && canReplayOrder ? 'order' : 'sample';

	const hasToken = status.armed || token.trim() !== '';

	const controlsDisabled = disabled || lockedByTier || busy;

	const send = async () => {
		setBusy( true );
		setResult( null );
		try {
			const response = await sendWebhookPreviewTest( {
				event,
				source: effectiveSource,
				order_id:
					effectiveSource === 'order'
						? parseInt( orderId, 10 ) || 0
						: 0,
				synthetic_transaction_id: syntheticTxn,
				token: token.trim(),
			} );
			const data = response?.data || {};
			applyStatus( data );
			setResult( {
				ok: Boolean( response?.success ),
				message:
					data.message ||
					( response?.success
						? __( 'Delivered.', 'gtm-kit' )
						: __( 'The test could not be sent.', 'gtm-kit' ) ),
				statusCode: data.status_code,
				responseBody: data.response_body,
			} );
			// A pasted token is now held server-side; clear the input.
			if ( token.trim() !== '' ) {
				setToken( '' );
			}
		} catch ( e ) {
			setResult( {
				ok: false,
				message:
					e?.message ||
					__(
						'The test could not be sent. Check your connection and try again.',
						'gtm-kit'
					),
			} );
		} finally {
			setBusy( false );
		}
	};

	const clearToken = async () => {
		setBusy( true );
		try {
			const response = await clearWebhookPreviewToken();
			if ( response?.success ) {
				applyStatus( response.data );
			}
			setToken( '' );
			setResult( null );
		} catch ( e ) {
			// Leave state as-is; the token simply was not cleared.
		} finally {
			setBusy( false );
		}
	};

	const toggleLiveAttach = async ( next ) => {
		setBusy( true );
		try {
			const response = await setWebhookPreviewToken( {
				token: token.trim(),
				live_attach: next,
			} );
			if ( response?.success ) {
				applyStatus( response.data );
				if ( token.trim() !== '' ) {
					setToken( '' );
				}
			} else {
				setResult( {
					ok: false,
					message:
						response?.data ||
						__(
							'Paste your server container Preview token first.',
							'gtm-kit'
						),
				} );
			}
		} catch ( e ) {
			setResult( {
				ok: false,
				message:
					e?.message ||
					__( 'Could not update the live toggle.', 'gtm-kit' ),
			} );
		} finally {
			setBusy( false );
		}
	};

	const filterSnippet = `add_filter(
    'gtmkit_webhook_request_args',
    function ( $args, $sgtm_domain, $context ) {
        $args['headers']['X-Gtm-Server-Preview'] = 'PASTE_YOUR_TOKEN_HERE';
        return $args;
    },
    10,
    3
);`;

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-5">
			{ /* Header */ }
			<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
				<div className="gtmkit-flex gtmkit-min-h-5 gtmkit-items-center gtmkit-gap-2">
					<span className="gtmkit-text-[13px] gtmkit-font-medium gtmkit-leading-5 gtmkit-text-text-primary">
						{ field.label }
					</span>
					<FieldTags field={ field } locked={ lockedByTier } />
				</div>
				{ field.description && (
					<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-text-secondary">
						{ field.description }
					</p>
				) }
				{ lockedByTier && <UpsellSlot tier={ field.tier } /> }
			</div>

			{ ! lockedByTier && (
				<>
					{ loading && (
						<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-text-secondary">
							{ __( 'Loading…', 'gtm-kit' ) }
						</p>
					) }

					{ ! loading && loadError && (
						<div className="gtmkit-rounded-md gtmkit-bg-brand-surface-subtle gtmkit-px-3.5 gtmkit-py-3">
							<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-text-color-warning">
								{ loadError }
							</p>
						</div>
					) }

					{ ! loading && ! loadError && (
						<>
							{ /* Token */ }
							<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
								<RowLabel
									label={ __(
										'Server container Preview token',
										'gtm-kit'
									) }
									description={ __(
										'Open your server container in Tag Manager, click Preview, and copy the token. It is per-session and expires.',
										'gtm-kit'
									) }
								/>
								{ status.armed && (
									<div className="gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-2">
										<code className="gtmkit-rounded gtmkit-bg-brand-surface-subtle gtmkit-px-2 gtmkit-py-1 gtmkit-text-xs">
											{ status.masked_token }
										</code>
										<span className="gtmkit-text-xs gtmkit-text-text-secondary">
											{ sprintf(
												// translators: %s: remaining time.
												__(
													'expires in %s',
													'gtm-kit'
												),
												formatRemaining(
													status.expires_in
												)
											) }
										</span>
										<button
											type="button"
											className={ LINK_BTN }
											disabled={ controlsDisabled }
											onClick={ clearToken }
										>
											{ __( 'Clear', 'gtm-kit' ) }
										</button>
									</div>
								) }
								<input
									type="password"
									className={ `${ BOX } gtmkit-w-full` }
									autoComplete="off"
									placeholder={
										status.armed
											? __(
													'Paste a fresh token to replace the stored one',
													'gtm-kit'
											  )
											: __(
													'Paste your Preview token',
													'gtm-kit'
											  )
									}
									value={ token }
									disabled={ controlsDisabled }
									onChange={ ( e ) =>
										setToken( e.target.value )
									}
								/>
							</div>

							{ /* Event type */ }
							<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-1.5">
								<RowLabel
									label={ __( 'Event to test', 'gtm-kit' ) }
								/>
								<select
									className={ `${ BOX } gtmkit-w-[260px]` }
									value={ event }
									disabled={ controlsDisabled }
									onChange={ ( e ) =>
										setEvent( e.target.value )
									}
								>
									{ visibleEvents.map( ( definition ) => (
										<option
											key={ definition.value }
											value={ definition.value }
										>
											{ definition.value }
										</option>
									) ) }
								</select>
							</div>

							{ /* Payload source */ }
							<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2.5">
								<RowLabel
									label={ __( 'Payload source', 'gtm-kit' ) }
								/>
								<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2">
									<label
										htmlFor="gtmkit-preview-source-sample"
										className="gtmkit-flex gtmkit-items-center gtmkit-gap-2 gtmkit-text-[13px] gtmkit-text-text-primary"
									>
										<input
											id="gtmkit-preview-source-sample"
											type="radio"
											name="gtmkit-preview-source"
											checked={
												effectiveSource === 'sample'
											}
											disabled={ controlsDisabled }
											onChange={ () =>
												setSource( 'sample' )
											}
										/>
										{ __(
											'Synthetic sample event',
											'gtm-kit'
										) }
									</label>
									<label
										htmlFor="gtmkit-preview-source-order"
										className={ `gtmkit-flex gtmkit-items-center gtmkit-gap-2 gtmkit-text-[13px] gtmkit-text-text-primary ${
											canReplayOrder
												? ''
												: 'gtmkit-opacity-50'
										}` }
									>
										<input
											id="gtmkit-preview-source-order"
											type="radio"
											name="gtmkit-preview-source"
											checked={
												effectiveSource === 'order'
											}
											disabled={
												controlsDisabled ||
												! canReplayOrder
											}
											onChange={ () =>
												setSource( 'order' )
											}
										/>
										{ __(
											'Replay a real order',
											'gtm-kit'
										) }
									</label>
									{ ! canReplayOrder && (
										<p className="gtmkit-m-0 gtmkit-pl-6 gtmkit-text-xs gtmkit-text-text-muted">
											{ wooActive
												? __(
														'This event type cannot be replayed from an order. Use the synthetic sample.',
														'gtm-kit'
												  )
												: __(
														'Order replay needs WooCommerce active.',
														'gtm-kit'
												  ) }
										</p>
									) }
								</div>

								{ effectiveSource === 'order' && (
									<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2 gtmkit-pl-6">
										<input
											type="number"
											className={ `${ BOX } gtmkit-w-[180px]` }
											placeholder={ __(
												'Order ID',
												'gtm-kit'
											) }
											value={ orderId }
											disabled={ controlsDisabled }
											onChange={ ( e ) =>
												setOrderId( e.target.value )
											}
										/>
										<label
											htmlFor="gtmkit-preview-synthetic-txn"
											className="gtmkit-flex gtmkit-items-center gtmkit-gap-2 gtmkit-text-[13px] gtmkit-text-text-primary"
										>
											<input
												id="gtmkit-preview-synthetic-txn"
												type="checkbox"
												checked={ syntheticTxn }
												disabled={ controlsDisabled }
												onChange={ ( e ) =>
													setSyntheticTxn(
														e.target.checked
													)
												}
											/>
											{ __(
												'Use a synthetic transaction ID (recommended)',
												'gtm-kit'
											) }
										</label>
									</div>
								) }
							</div>

							{ /* Downstream-tag warning */ }
							<div className="gtmkit-rounded-md gtmkit-bg-brand-surface-subtle gtmkit-px-3.5 gtmkit-py-3">
								<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-leading-[1.45] gtmkit-text-color-warning">
									{ __(
										'A valid token does more than show the event in Preview: the container processes it and fires your downstream tags. A test purchase can push a real (fake) conversion into GA4 and Google Ads unless those tags are gated to Preview/debug traffic.',
										'gtm-kit'
									) }
								</p>
							</div>

							{ /* Send */ }
							<div className="gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-3">
								<button
									type="button"
									className={ PRIMARY_BTN }
									disabled={ controlsDisabled || ! hasToken }
									onClick={ send }
								>
									{ busy
										? __( 'Sending…', 'gtm-kit' )
										: __(
												'Send test webhook to sGTM Preview',
												'gtm-kit'
										  ) }
								</button>
								{ ! hasToken && (
									<span className="gtmkit-text-xs gtmkit-text-text-muted">
										{ __(
											'Paste a token to enable.',
											'gtm-kit'
										) }
									</span>
								) }
							</div>

							{ /* Result */ }
							{ result && (
								<div
									className={ `gtmkit-rounded-md gtmkit-px-3.5 gtmkit-py-3 ${
										result.ok
											? 'gtmkit-bg-green-50'
											: 'gtmkit-bg-red-50'
									}` }
								>
									<p
										className={ `gtmkit-m-0 gtmkit-text-xs gtmkit-leading-[1.45] ${
											result.ok
												? 'gtmkit-text-green-700'
												: 'gtmkit-text-red-700'
										}` }
									>
										{ typeof result.statusCode === 'number'
											? sprintf(
													// translators: 1: HTTP status code, 2: message.
													__(
														'HTTP %1$d — %2$s',
														'gtm-kit'
													),
													result.statusCode,
													result.message
											  )
											: result.message }
									</p>
									{ result.responseBody && (
										<pre className="gtmkit-mt-2 gtmkit-max-h-40 gtmkit-overflow-auto gtmkit-whitespace-pre-wrap gtmkit-break-all gtmkit-rounded gtmkit-bg-white gtmkit-p-2 gtmkit-text-[11px] gtmkit-text-text-secondary">
											{ result.responseBody }
										</pre>
									) }
								</div>
							) }

							{ /* Advanced: live-traffic toggle + filter snippet */ }
							<div className="gtmkit-border-t gtmkit-border-border-default gtmkit-pt-4">
								<button
									type="button"
									className={ LINK_BTN }
									onClick={ () =>
										setAdvancedOpen( ! advancedOpen )
									}
									aria-expanded={ advancedOpen }
								>
									{ advancedOpen
										? __(
												'Hide developer options',
												'gtm-kit'
										  )
										: __( 'Developer options', 'gtm-kit' ) }
								</button>

								{ advancedOpen && (
									<div className="gtmkit-mt-4 gtmkit-flex gtmkit-flex-col gtmkit-gap-4">
										{ status.live_attach && (
											<div className="gtmkit-rounded-md gtmkit-bg-red-50 gtmkit-px-3.5 gtmkit-py-3">
												<p className="gtmkit-m-0 gtmkit-text-xs gtmkit-font-medium gtmkit-text-red-700">
													{ sprintf(
														// translators: %s: remaining time.
														__(
															'Live preview header is ON. It turns itself off in %s when the token expires.',
															'gtm-kit'
														),
														formatRemaining(
															status.expires_in
														)
													) }
												</p>
											</div>
										) }
										<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2">
											<label
												htmlFor="gtmkit-preview-live-attach"
												className="gtmkit-flex gtmkit-items-start gtmkit-gap-2 gtmkit-text-[13px] gtmkit-text-text-primary"
											>
												<input
													id="gtmkit-preview-live-attach"
													type="checkbox"
													className="gtmkit-mt-0.5"
													checked={
														status.live_attach
													}
													disabled={
														controlsDisabled ||
														( ! status.armed &&
															token.trim() ===
																'' )
													}
													onChange={ ( e ) =>
														toggleLiveAttach(
															e.target.checked
														)
													}
												/>
												<span>
													{ __(
														'Attach the preview header to live webhook traffic',
														'gtm-kit'
													) }
												</span>
											</label>
											<p className="gtmkit-m-0 gtmkit-pl-6 gtmkit-text-xs gtmkit-text-text-secondary">
												{ __(
													'Live orders will appear in Preview while this is on. It is tied to the token and disarms automatically when the token expires, so it can never send a stale token that would make live webhooks fail. Use it only for short, supervised debugging.',
													'gtm-kit'
												) }
											</p>
										</div>

										<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2">
											<button
												type="button"
												className={ LINK_BTN }
												onClick={ () =>
													setShowSnippet(
														! showSnippet
													)
												}
											>
												{ showSnippet
													? __(
															'Hide filter snippet',
															'gtm-kit'
													  )
													: __(
															'Copy as filter snippet',
															'gtm-kit'
													  ) }
											</button>
											{ showSnippet && (
												<pre className="gtmkit-max-h-60 gtmkit-overflow-auto gtmkit-whitespace-pre-wrap gtmkit-rounded gtmkit-bg-brand-surface-subtle gtmkit-p-3 gtmkit-text-[11px] gtmkit-text-text-secondary">
													{ filterSnippet }
												</pre>
											) }
										</div>
									</div>
								) }
							</div>
						</>
					) }
				</>
			) }
		</div>
	);
};

export default SgtmPreviewTest;
