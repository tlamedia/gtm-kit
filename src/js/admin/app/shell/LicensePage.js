/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useContext, useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

/*Context / services / components*/
import { LicenseContext } from '../../context/LicenseContext';
import SettingsService from '../../services/SettingsService';
import { safeHref } from '../../utils/safeUrl';
import ContextPane from './ContextPane';

const CARD =
	'gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white';
const HEADER =
	'gtmkit-flex gtmkit-items-center gtmkit-gap-2.5 gtmkit-px-5 gtmkit-py-4';
const LABEL = 'gtmkit-text-sm gtmkit-text-text-primary';
const TITLE =
	'gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary';
const BADGE =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-px-2 gtmkit-py-0.5 gtmkit-rounded-sm gtmkit-text-[11px] gtmkit-font-medium gtmkit-whitespace-nowrap';
const BTN_PRIMARY =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90';
const BTN_OUTLINE =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-border gtmkit-border-brand-primary gtmkit-bg-white gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-bg-brand-surface-subtle';
const BOX =
	'gtmkit-h-[34px] gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-[10px] gtmkit-text-[13px] gtmkit-text-text-secondary gtmkit-flex gtmkit-items-center';

const ACCOUNT_URL = 'https://gtmkit.com/account/';
const PREMIUM_SUPPORT_URL = 'https://jump.gtmkit.com/link/4-E35E4';

const LICENSE_CONTEXT = {
	about: {
		title: __( 'Trouble activating?', 'gtm-kit' ),
		text: __(
			'Make sure the key is pasted in full (it starts with GTMK-) with no leading or trailing spaces, and that your server can reach the GTM Kit licensing server. If activation still fails, premium licence holders get priority email support and we can check it from our side.',
			'gtm-kit'
		),
		link: {
			label: __( 'Contact premium support', 'gtm-kit' ),
			href: PREMIUM_SUPPORT_URL,
		},
	},
};

const FEATURES = [
	__( 'Server-side webhooks & background queue', 'gtm-kit' ),
	__( 'WooCommerce Subscriptions tracking', 'gtm-kit' ),
	__( 'Event deferral until consent', 'gtm-kit' ),
	__( 'Order attribution & enhanced conversions', 'gtm-kit' ),
];

/**
 * The renewal line for an active license: the formatted renewal date, or an
 * empty string for a license that does not expire.
 *
 * @param {number|null} expiresAt Expiry as a Unix timestamp (seconds), or null.
 * @return {string} The renewal text.
 */
const renewalText = ( expiresAt ) => {
	if ( ! expiresAt ) {
		return '';
	}
	const date = new Date( expiresAt * 1000 ).toLocaleDateString( undefined, {
		day: 'numeric',
		month: 'short',
		year: 'numeric',
	} );
	/* translators: %s: license renewal date. */
	return sprintf( __( 'Renews %s', 'gtm-kit' ), date );
};

/**
 * Resolve the purchase/compare URL from the upsell opportunities, falling back
 * to the product site.
 *
 * @return {string} The URL.
 */
const purchaseUrl = () => {
	const upgrades = SettingsService.getOpportunities()?.upgrades || {};
	const first = Object.values( upgrades )[ 0 ];
	return safeHref( first?.url ) || 'https://gtmkit.com/';
};

/**
 * A green check glyph for the feature list.
 *
 * @return {JSX.Element} The icon.
 */
const Check = () => (
	<svg
		width="14"
		height="14"
		viewBox="0 0 20 20"
		fill="none"
		aria-hidden="true"
		className="gtmkit-shrink-0 gtmkit-text-tier-premium"
	>
		<path
			d="M5 10.5l3 3 7-7"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

/**
 * The "Your license" card shown to a licensed customer: a success
 * confirmation panel with the masked key and renewal date, and the
 * deactivate control below it.
 *
 * @param {Object}   props              Component props.
 * @param {string}   props.maskedKey    The masked license key.
 * @param {string}   props.renews       The renewal line, or an empty string.
 * @param {Function} props.onDeactivate Deactivate handler.
 * @param {boolean}  props.deactivating Whether deactivation is in progress.
 * @return {JSX.Element} The card.
 */
const LicensedCard = ( { maskedKey, renews, onDeactivate, deactivating } ) => (
	<div className={ CARD }>
		<div className="gtmkit-m-5 gtmkit-rounded-md gtmkit-border gtmkit-border-tier-premium gtmkit-p-5">
			<div className="gtmkit-mb-2 gtmkit-flex gtmkit-items-center gtmkit-gap-2">
				<svg
					width="18"
					height="18"
					viewBox="0 0 20 20"
					fill="currentColor"
					aria-hidden="true"
					className="gtmkit-shrink-0 gtmkit-text-tier-premium"
				>
					<path
						fillRule="evenodd"
						clipRule="evenodd"
						d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
					/>
				</svg>
				<span className="gtmkit-text-lg gtmkit-font-semibold gtmkit-text-text-primary">
					{ __( 'License active', 'gtm-kit' ) }
				</span>
			</div>
			<p className="gtmkit-m-0 gtmkit-mb-3 gtmkit-text-[13px] gtmkit-leading-5 gtmkit-text-text-primary">
				{ __(
					'Your GTM Kit Premium license is active and all premium features are enabled.',
					'gtm-kit'
				) }
			</p>
			<div className="gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-x-3 gtmkit-gap-y-1 gtmkit-text-[13px] gtmkit-text-text-primary">
				<span>
					<span className="gtmkit-font-medium">
						{ __( 'License key:', 'gtm-kit' ) }
					</span>{ ' ' }
					<code className="gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-1.5 gtmkit-py-0.5">
						{ maskedKey }
					</code>
				</span>
				{ renews && <span>{ renews }</span> }
			</div>
		</div>
		<div className="gtmkit-px-5 gtmkit-pb-5">
			<button
				type="button"
				className={ BTN_OUTLINE }
				onClick={ onDeactivate }
				disabled={ deactivating }
			>
				{ __( 'Deactivate license', 'gtm-kit' ) }
				{ deactivating && <Spinner /> }
			</button>
		</div>
	</div>
);

/**
 * The "Unlock GTM Kit Premium" card shown to an unlicensed prospect, with the
 * license-key activation row.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.licenseKey    The license-key input value.
 * @param {Function} props.onChange      Input change handler.
 * @param {Function} props.onActivate    Activation handler.
 * @param {boolean}  props.sending       Whether activation is in progress.
 * @param {string}   props.message       The activation error/result message.
 * @param {boolean}  props.success       Whether the last activation succeeded.
 * @param {boolean}  props.premiumActive Whether the Premium plugin is active.
 * @return {JSX.Element} The card.
 */
const UnlicensedCard = ( {
	licenseKey,
	onChange,
	onActivate,
	sending,
	message,
	success,
	premiumActive,
} ) => (
	<div className={ CARD }>
		<div className={ HEADER }>
			<span className={ TITLE }>
				{ __( 'Unlock GTM Kit Premium', 'gtm-kit' ) }
			</span>
			<span
				className={ `${ BADGE } gtmkit-bg-[#fcf3d6] gtmkit-text-[#8a6d00]` }
			>
				{ __( 'Upgrade', 'gtm-kit' ) }
			</span>
		</div>
		<div className="gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-pt-3.5 gtmkit-pb-2">
			<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-secondary">
				{ __(
					'Server-side tracking, the webhook queue, subscriptions and more.',
					'gtm-kit'
				) }
			</p>
			<ul className="gtmkit-m-0 gtmkit-mt-3 gtmkit-list-none gtmkit-space-y-2 gtmkit-p-0">
				{ FEATURES.map( ( feature ) => (
					<li
						key={ feature }
						className="gtmkit-flex gtmkit-items-center gtmkit-gap-2.5"
					>
						<Check />
						<span className={ LABEL }>{ feature }</span>
					</li>
				) ) }
			</ul>
		</div>
		<div className="gtmkit-flex gtmkit-flex-wrap gtmkit-items-center gtmkit-gap-2.5 gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-4">
			{ premiumActive && (
				<>
					<input
						type="text"
						value={ licenseKey }
						placeholder={ __( 'Enter license key', 'gtm-kit' ) }
						aria-label={ __( 'License key', 'gtm-kit' ) }
						className={ `${ BOX } gtmkit-w-[360px] gtmkit-text-text-primary focus:gtmkit-border-brand-primary focus:gtmkit-outline-none` }
						onChange={ ( e ) => onChange( e.target.value ) }
					/>
					<button
						type="button"
						className={ BTN_PRIMARY }
						onClick={ onActivate }
						disabled={ sending }
					>
						{ __( 'Activate license', 'gtm-kit' ) }
						{ sending && <Spinner /> }
					</button>
				</>
			) }
			{ ! premiumActive && (
				<a
					href={ purchaseUrl() }
					target="_blank"
					rel="noreferrer"
					className={ BTN_PRIMARY }
				>
					{ __( 'Buy a license', 'gtm-kit' ) } →
				</a>
			) }
			<a
				href={ ACCOUNT_URL }
				target="_blank"
				rel="noreferrer"
				className={ BTN_OUTLINE }
			>
				{ __( 'Go to my account', 'gtm-kit' ) } →
			</a>
			{ message && (
				<span
					className={ `gtmkit-w-full gtmkit-text-xs ${
						success
							? 'gtmkit-text-tier-premium'
							: 'gtmkit-text-[#b32d2e]'
					}` }
				>
					{ message }
				</span>
			) }
		</div>
	</div>
);

/**
 * The License page: a single card that adapts to license state. A licensed
 * customer sees their license and a deactivate action; a prospect sees the
 * Premium upsell with an activation row.
 *
 * @return {JSX.Element} The page.
 */
const LicensePage = () => {
	const {
		hasValidLicense,
		licenseKey,
		updateLicenseKey,
		sendLicenseKey,
		deactivateLicense,
		isSendingLicenseKey,
		isLicenseKeySent,
		licenseKeyMessage,
	} = useContext( LicenseContext );

	const [ deactivating, setDeactivating ] = useState( false );

	const currentKey = SettingsService.getCurrentLicenseKey();
	const licensed = hasValidLicense && !! currentKey;
	const premiumActive = SettingsService.isPremiumPlugin();

	// Reflect a successful activation by reloading so the bridge reports the
	// new license state.
	useEffect( () => {
		if ( isLicenseKeySent && ! isSendingLicenseKey ) {
			window.location.reload();
		}
	}, [ isLicenseKeySent, isSendingLicenseKey ] );

	const onDeactivate = async () => {
		setDeactivating( true );
		await deactivateLicense();
		window.location.reload();
	};

	return (
		<>
			<div className="gtmkit-mb-8 gtmkit-flex gtmkit-items-center gtmkit-justify-between">
				<h2 className="gtmkit-m-0 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ __( 'License', 'gtm-kit' ) }
				</h2>
				{ ! licensed && premiumActive && (
					<a
						href={ purchaseUrl() }
						target="_blank"
						rel="noreferrer"
						className={ BTN_PRIMARY }
					>
						{ __( 'Buy a license', 'gtm-kit' ) } →
					</a>
				) }
			</div>

			<div className="min-[1440px]:gtmkit-flex min-[1440px]:gtmkit-items-start min-[1440px]:gtmkit-gap-6">
				<div className="min-[1440px]:gtmkit-min-w-0 min-[1440px]:gtmkit-flex-1">
					{ licensed ? (
						<LicensedCard
							maskedKey={ currentKey }
							renews={ renewalText(
								SettingsService.getLicenseExpiresAt()
							) }
							onDeactivate={ onDeactivate }
							deactivating={ deactivating }
						/>
					) : (
						<UnlicensedCard
							licenseKey={ licenseKey }
							onChange={ updateLicenseKey }
							onActivate={ sendLicenseKey }
							sending={ isSendingLicenseKey }
							message={ licenseKeyMessage }
							success={ isLicenseKeySent }
							premiumActive={ premiumActive }
						/>
					) }
				</div>
				<aside className="gtmkit-hidden min-[1440px]:gtmkit-block min-[1440px]:gtmkit-w-[400px] min-[1440px]:gtmkit-shrink-0 min-[1440px]:gtmkit-sticky min-[1440px]:gtmkit-top-[112px] min-[1440px]:gtmkit-self-start">
					<ContextPane context={ LICENSE_CONTEXT } />
				</aside>
			</div>
		</>
	);
};

export default LicensePage;
