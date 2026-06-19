/*WordPress*/
import { __ } from '@wordpress/i18n';
import { useContext } from '@wordpress/element';
import { Spinner } from '@wordpress/components';

/*Context / services / components*/
import { SupportContext } from '../../context/SupportContext';
import SettingsService from '../../services/SettingsService';
import { safeHref } from '../../utils/safeUrl';
import {
	InfoCard,
	InfoRow,
	InfoNote,
	ExternalAction,
	PremiumBadge,
	PageHeader,
} from './InfoList';

const DOCS_URL = 'https://gtmkit.com/documentation/';
const FORUM_URL = 'https://wordpress.org/support/plugin/gtm-kit/';
const PREMIUM_SUPPORT_URL = 'https://jump.gtmkit.com/link/4-E35E4';
const GITHUB_ISSUES_URL = 'https://github.com/tlamedia/gtm-kit/issues';

/**
 * The purchase/upgrade URL from the upsell opportunities, falling back to the
 * product site.
 *
 * @return {string} The URL.
 */
const purchaseUrl = () => {
	const upgrades = SettingsService.getOpportunities()?.upgrades || {};
	return (
		safeHref( Object.values( upgrades )[ 0 ]?.url ) || 'https://gtmkit.com/'
	);
};

const INPUT =
	'gtmkit-h-[34px] gtmkit-w-[260px] gtmkit-rounded-sm gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-[10px] gtmkit-text-[13px] gtmkit-text-text-primary focus:gtmkit-border-brand-primary focus:gtmkit-outline-none';
const BTN_PRIMARY =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-gap-1 gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90 disabled:gtmkit-opacity-50';
const CHANNEL_CARD =
	'gtmkit-flex gtmkit-flex-col gtmkit-gap-3.5 gtmkit-rounded-xl gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-6 gtmkit-pb-[22px] gtmkit-pt-6';

/**
 * A support-channel card: a title, a paragraph and a bottom-aligned action link
 * with an optional Premium badge.
 *
 * @param {Object}  props             Component props.
 * @param {string}  props.title       Channel title.
 * @param {string}  props.description Channel description.
 * @param {Object}  props.action      The action link ({ label, href }).
 * @param {boolean} [props.premium]   Whether to show the Premium badge.
 * @return {JSX.Element} The card.
 */
const ChannelCard = ( { title, description, action, premium } ) => (
	<div className={ CHANNEL_CARD }>
		<h3 className="gtmkit-m-0 gtmkit-text-[17px] gtmkit-font-semibold gtmkit-text-text-primary">
			{ title }
		</h3>
		<p className="gtmkit-m-0 gtmkit-flex-1 gtmkit-text-sm gtmkit-leading-normal gtmkit-text-text-secondary">
			{ description }
		</p>
		<div className="gtmkit-flex gtmkit-w-full gtmkit-items-center gtmkit-justify-between gtmkit-gap-2 gtmkit-pt-1">
			<a
				href={ action.href }
				target="_blank"
				rel="noreferrer"
				className="gtmkit-text-sm gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
			>
				{ action.label }
			</a>
			{ premium && <PremiumBadge /> }
		</div>
	</div>
);

/**
 * Support page: support channels, the system-data sharing flow and the
 * documentation library.
 *
 * @return {JSX.Element} The page.
 */
const SupportPage = () => {
	const {
		isSendingSystemData,
		updateSupportTicket,
		useSupportTicket,
		sendSystemData,
		useIsSystemDataSent,
		useSystemDataMessage,
	} = useContext( SupportContext );

	const canSend = useSupportTicket.trim().toUpperCase().startsWith( 'FS' );
	const tutorials = SettingsService.getTutorials();
	const premiumActive = SettingsService.isPremiumPlugin();

	return (
		<>
			<PageHeader
				title={ __( 'Support', 'gtm-kit' ) }
				subtitle={ __( 'Get help and contact us', 'gtm-kit' ) }
			/>

			<div className="gtmkit-mb-6 gtmkit-grid gtmkit-grid-cols-1 gtmkit-gap-4 md:gtmkit-grid-cols-3">
				<ChannelCard
					title={ __( 'Premium support', 'gtm-kit' ) }
					description={ __(
						'Premium subscribers get direct email support, usually a same-day reply on weekdays. Bugs, configuration questions, edge cases.',
						'gtm-kit'
					) }
					action={
						premiumActive
							? {
									label: __( 'Contact support', 'gtm-kit' ),
									href: PREMIUM_SUPPORT_URL,
							  }
							: {
									label: __(
										'Get GTM Kit Premium',
										'gtm-kit'
									),
									href: purchaseUrl(),
							  }
					}
					premium
				/>
				<ChannelCard
					title={ __( 'Community support', 'gtm-kit' ) }
					description={ __(
						'Free support in the WordPress.org support forum. Covers the plugin itself, not Google Tag Manager configuration.',
						'gtm-kit'
					) }
					action={ {
						label: __( 'WordPress support forum', 'gtm-kit' ),
						href: FORUM_URL,
					} }
				/>
				<ChannelCard
					title={ __( 'Bug reports', 'gtm-kit' ) }
					description={ __(
						'Found a reproducible bug? Open an issue on GitHub with steps to reproduce and the plugin version. Public, searchable, faster to triage.',
						'gtm-kit'
					) }
					action={ {
						label: __( 'Report on GitHub', 'gtm-kit' ),
						href: GITHUB_ISSUES_URL,
					} }
				/>
			</div>

			<InfoCard
				title={ __(
					'Share system data with the GTM Kit support team',
					'gtm-kit'
				) }
			>
				{ useIsSystemDataSent ? (
					<InfoNote>{ useSystemDataMessage }</InfoNote>
				) : (
					<>
						<InfoNote>
							{ __(
								'If you have contacted support you may have been asked to share your system data. Enter the support ticket you have been given below.',
								'gtm-kit'
							) }
						</InfoNote>
						<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-3.5 gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-3.5">
							<span className="gtmkit-text-sm gtmkit-text-text-primary">
								{ __( 'Support ticket', 'gtm-kit' ) }
							</span>
							<input
								type="text"
								value={ useSupportTicket }
								placeholder={ __( 'FS-12345', 'gtm-kit' ) }
								aria-label={ __( 'Support ticket', 'gtm-kit' ) }
								className={ INPUT }
								onChange={ ( e ) =>
									updateSupportTicket( e.target.value )
								}
							/>
						</div>
						<div className="gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-3.5">
							<button
								type="button"
								className={ BTN_PRIMARY }
								onClick={ sendSystemData }
								disabled={ ! canSend }
							>
								{ __( 'Send system data', 'gtm-kit' ) }
								{ isSendingSystemData && <Spinner /> }
							</button>
							{ useSystemDataMessage && (
								<p className="gtmkit-m-0 gtmkit-mt-2 gtmkit-text-xs gtmkit-text-[#b32d2e]">
									{ useSystemDataMessage }
								</p>
							) }
						</div>
					</>
				) }
			</InfoCard>

			<InfoCard
				title={ __( 'Documentation', 'gtm-kit' ) }
				headerAction={
					<ExternalAction
						href={ DOCS_URL }
						label={ __( 'View all documentation', 'gtm-kit' ) }
					/>
				}
			>
				{ tutorials.map( ( tutorial, index ) => (
					<InfoRow
						key={ tutorial.title || index }
						title={ tutorial.title }
						subtitle={
							Array.isArray( tutorial.text )
								? tutorial.text[ 0 ]
								: tutorial.text
						}
						right={
							<ExternalAction
								href={ safeHref( tutorial.link?.url ) }
								label={ __( 'Read', 'gtm-kit' ) }
							/>
						}
					/>
				) ) }
			</InfoCard>
		</>
	);
};

export default SupportPage;
