/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

/*Internal*/
import {
	useReachabilityProbe,
	PROBE_STATE,
} from '../../hooks/useReachabilityProbe';
import SettingsService from '../../services/SettingsService';

const BTN_SECONDARY =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-3 gtmkit-py-1.5 gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-text-primary hover:gtmkit-bg-brand-surface-subtle';

const TONE = {
	[ PROBE_STATE.SLOW ]:
		'gtmkit-border-amber-300 gtmkit-bg-amber-50 gtmkit-text-amber-900',
	[ PROBE_STATE.UNREACHABLE ]:
		'gtmkit-border-red-300 gtmkit-bg-red-50 gtmkit-text-red-900',
	[ PROBE_STATE.ERROR ]:
		'gtmkit-border-red-300 gtmkit-bg-red-50 gtmkit-text-red-900',
};

/**
 * The plain-text block a user can paste into a support request. It holds
 * only what the probe saw and what the page already shows; never the nonce
 * or any cookie.
 *
 * @param {string} state   The probe state.
 * @param {Object} details The probe details.
 * @return {string} The block.
 */
export const buildSupportDetails = ( state, details ) =>
	[
		'GTM Kit settings API check',
		`State: ${ state }`,
		`HTTP status: ${ details.status ?? 'none' }`,
		`Error code: ${ details.code || 'none' }`,
		`Message: ${ details.message || 'none' }`,
		`Elapsed: ${ details.elapsedMs ?? 'unknown' } ms`,
		`REST root: ${ SettingsService.getRestRoot() }`,
		`Checked at: ${ details.checkedAt || new Date().toISOString() }`,
		`GTM Kit version: ${ SettingsService.getVersion() || 'unknown' }`,
		`User agent: ${ window.navigator.userAgent }`,
	].join( '\n' );

const bodyText = ( state, details ) => {
	if ( state === PROBE_STATE.SLOW ) {
		return __(
			"Your site has not answered yet. GTM Kit checks that your browser can reach your site's settings API. If this message stays, saving settings may not work.",
			'gtm-kit'
		);
	}

	if ( state === PROBE_STATE.UNREACHABLE ) {
		return __(
			"Your browser could not reach your site's settings API, so saving settings and activating your licence will not work from this browser. This is usually caused by a browser extension or ad blocker, a VPN, or a firewall or security service in front of your site. Try a private window with extensions turned off, or another network.",
			'gtm-kit'
		);
	}

	// WordPress answers with `rest_forbidden` when the request arrived but the
	// capability check failed: the user lacks the capability the route asks
	// for, or the request reached WordPress without the login cookie. A
	// firewall's 403 does not carry this code.
	if (
		details.code === 'rest_forbidden' &&
		( details.status === 401 || details.status === 403 )
	) {
		return __(
			'WordPress refused the settings API check for your user account, so changes made here will not be saved. Either your account does not have permission to change GTM Kit settings (on a multisite network, ask a network administrator), or the site did not recognise you as logged in, which can happen when a cache or proxy removes cookies from API requests.',
			'gtm-kit'
		);
	}

	return [
		sprintf(
			/* translators: %d: HTTP status code. */
			__(
				'Your site answered the settings API check with an error (HTTP %d), so saving settings may not work from this browser.',
				'gtm-kit'
			),
			details.status
		),
		__(
			'Security plugins and firewalls often answer blocked requests with HTTP 403.',
			'gtm-kit'
		),
	].join( ' ' );
};

/**
 * A notice above every settings page, shown only when this browser cannot
 * reach the site's settings API or the check is taking unusually long.
 *
 * @param {Object} props                Component props.
 * @param {Object} [props.probeOptions] Options passed to the probe (tests).
 * @return {JSX.Element|null} The banner, or nothing while all is well.
 */
const ReachabilityBanner = ( { probeOptions } ) => {
	const { state, details, retry } = useReachabilityProbe( probeOptions );
	const [ isCopied, setIsCopied ] = useState( false );

	useEffect( () => setIsCopied( false ), [ state ] );

	if ( ! TONE[ state ] ) {
		return null;
	}

	const final = state !== PROBE_STATE.SLOW;
	const supportDetails = final ? buildSupportDetails( state, details ) : '';

	const copy = async () => {
		try {
			await window.navigator.clipboard.writeText( supportDetails );
			setIsCopied( true );
		} catch {
			// The Clipboard API is missing on an admin served over plain
			// HTTP, so fall back to copying through a hidden textarea.
			const textarea = document.createElement( 'textarea' );
			textarea.value = supportDetails;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();
			setIsCopied( document.execCommand( 'copy' ) );
			textarea.remove();
		}
	};

	return (
		<div
			role={ final ? 'alert' : 'status' }
			data-probe-state={ state }
			className={ `gtmkit-mb-6 gtmkit-rounded-md gtmkit-border gtmkit-px-5 gtmkit-py-4 gtmkit-text-sm ${ TONE[ state ] }` }
		>
			<p className="gtmkit-m-0 gtmkit-leading-[1.5]">
				{ bodyText( state, details ) }
			</p>
			<div className="gtmkit-mt-3 gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-2">
				<button
					type="button"
					className={ BTN_SECONDARY }
					onClick={ retry }
				>
					{ __( 'Check again', 'gtm-kit' ) }
				</button>
				{ final && (
					<button
						type="button"
						className={ BTN_SECONDARY }
						onClick={ copy }
					>
						{ isCopied
							? __( 'Copied', 'gtm-kit' )
							: __( 'Copy details for support', 'gtm-kit' ) }
					</button>
				) }
			</div>
		</div>
	);
};

export default ReachabilityBanner;
