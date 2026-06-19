/*WordPress*/
import { __, sprintf } from '@wordpress/i18n';
import { useContext, useState } from '@wordpress/element';
import { Link } from 'react-router-dom';

/*Registry / context / hooks / components*/
import {
	getDashboardMetrics,
	getDashboardNotifications,
	getDashboardDismissed,
} from '../../registry/dashboardData';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { useNotification } from '../../hooks';
import SettingsService from '../../services/SettingsService';
import { safeHref } from '../../utils/safeUrl';
import { InfoCard, InfoRow, ExternalAction } from './InfoList';

const DOCS_URL = 'https://gtmkit.com/documentation/';

const BADGE =
	'gtmkit-inline-flex gtmkit-items-center gtmkit-rounded-sm gtmkit-px-2 gtmkit-py-0.5 gtmkit-text-[11px] gtmkit-font-medium gtmkit-whitespace-nowrap';
const BRAND_ACTION =
	'gtmkit-shrink-0 gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline';
const SMALL_BUTTON =
	'gtmkit-shrink-0 gtmkit-cursor-pointer gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-3 gtmkit-py-1.5 gtmkit-text-[12px] gtmkit-font-medium gtmkit-text-text-secondary hover:gtmkit-bg-[#f6f7f7]';

const METRIC_BADGE = {
	active: {
		label: __( 'Active', 'gtm-kit' ),
		className: 'gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium',
	},
	off: {
		label: __( 'Off', 'gtm-kit' ),
		className: 'gtmkit-bg-[#ececed] gtmkit-text-text-muted',
	},
	premium: {
		label: __( 'Premium', 'gtm-kit' ),
		className: 'gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium',
	},
};

const SEVERITY_DOT = {
	error: 'gtmkit-bg-[#d63638]',
	warning: 'gtmkit-bg-[#dba617]',
};

const isOn = ( value ) => value === true || value === 1 || value === '1';

/**
 * A headline metric card: a label with optional status badge, a value and a
 * one-line caption.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.metric Metric descriptor.
 * @return {JSX.Element} The card.
 */
const MetricCard = ( { metric } ) => {
	const badge = metric.badge ? METRIC_BADGE[ metric.badge ] : null;

	return (
		<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2 gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-[18px] gtmkit-py-4">
			<div className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-2">
				<span className="gtmkit-text-sm gtmkit-font-medium gtmkit-text-text-muted">
					{ metric.label }
				</span>
				{ badge && (
					<span className={ `${ BADGE } ${ badge.className }` }>
						{ badge.label }
					</span>
				) }
			</div>
			<span className="gtmkit-text-[18px] gtmkit-font-semibold gtmkit-text-text-primary">
				{ metric.value }
			</span>
			<span className="gtmkit-text-xs gtmkit-text-text-muted">
				{ metric.subtitle }
			</span>
		</div>
	);
};

/**
 * The far-right card: the "Help improve GTM Kit" anonymous-data opt-in, with a
 * status badge and a link to manage it on the Tools page.
 *
 * @param {Object}  props        Component props.
 * @param {boolean} props.active Whether anonymous data sharing is on.
 * @return {JSX.Element} The card.
 */
const TelemetryCard = ( { active } ) => (
	<div className="gtmkit-flex gtmkit-flex-col gtmkit-gap-2 gtmkit-rounded-md gtmkit-border gtmkit-border-border-default gtmkit-bg-white gtmkit-px-[18px] gtmkit-py-4">
		<div className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-2">
			<span className="gtmkit-text-sm gtmkit-font-medium gtmkit-text-text-muted">
				{ __( 'Help improve GTM Kit', 'gtm-kit' ) }
			</span>
			<span
				className={ `${ BADGE } ${
					active
						? 'gtmkit-bg-tier-premium-bg gtmkit-text-tier-premium'
						: 'gtmkit-bg-[#ececed] gtmkit-text-text-muted'
				}` }
			>
				{ active
					? __( 'Active', 'gtm-kit' )
					: __( 'Inactive', 'gtm-kit' ) }
			</span>
		</div>
		<span className="gtmkit-block gtmkit-text-xs gtmkit-text-text-muted">
			{ __( 'Share anonymous data to help improve GTM Kit.', 'gtm-kit' ) }
		</span>
		<Link to="/tools" className={ `gtmkit-mt-auto ${ BRAND_ACTION }` }>
			{ active
				? __( 'Manage', 'gtm-kit' )
				: __( 'Share anonymous data', 'gtm-kit' ) }{ ' ' }
			→
		</Link>
	</div>
);

/**
 * A "Needs attention" row: a severity dot, a title and description, the
 * notification's own action link, and a dismiss control.
 *
 * @param {Object}   props              Component props.
 * @param {Object}   props.notification Parsed notification row.
 * @param {Function} props.onDismiss    Called with the notification to dismiss it.
 * @return {JSX.Element} The row.
 */
const HealthRow = ( { notification, onDismiss } ) => (
	<div className="gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-4 gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-3.5">
		<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-items-center gtmkit-gap-3">
			<span
				className={ `gtmkit-h-2 gtmkit-w-2 gtmkit-shrink-0 gtmkit-rounded-full ${
					SEVERITY_DOT[ notification.severity ] ||
					SEVERITY_DOT.warning
				}` }
			/>
			<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-0.5">
				<span className="gtmkit-text-sm gtmkit-font-medium gtmkit-text-text-primary">
					{ notification.title }
				</span>
				<span className="gtmkit-text-xs gtmkit-text-text-muted">
					{ notification.description }
				</span>
			</div>
		</div>
		<div className="gtmkit-flex gtmkit-shrink-0 gtmkit-items-center gtmkit-gap-3.5">
			{ notification.action?.href && (
				<a href={ notification.action.href } className={ BRAND_ACTION }>
					{ notification.action.label } →
				</a>
			) }
			<button
				type="button"
				onClick={ () => onDismiss( notification ) }
				className={ SMALL_BUTTON }
			>
				{ __( 'Dismiss', 'gtm-kit' ) }
			</button>
		</div>
	</div>
);

/**
 * A dismissed-notification row: a muted dot, the title and a Restore control
 * that moves it back into the active list.
 *
 * @param {Object}   props              Component props.
 * @param {Object}   props.notification Parsed notification row.
 * @param {Function} props.onRestore    Called with the notification id to restore it.
 * @return {JSX.Element} The row.
 */
const DismissedRow = ( { notification, onRestore } ) => (
	<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-3 gtmkit-border-t gtmkit-border-border-default gtmkit-bg-[#fafafa] gtmkit-px-5 gtmkit-py-3">
		<span className="gtmkit-h-2 gtmkit-w-2 gtmkit-shrink-0 gtmkit-rounded-full gtmkit-bg-[#c3c4c7]" />
		<span className="gtmkit-min-w-0 gtmkit-flex-1 gtmkit-text-[13px] gtmkit-text-text-muted">
			{ notification.title }
		</span>
		<button
			type="button"
			onClick={ () => onRestore( notification.id ) }
			className={ SMALL_BUTTON }
		>
			{ __( 'Restore', 'gtm-kit' ) }
		</button>
	</div>
);

/**
 * The "Needs attention" card. It always renders: a header with a count badge,
 * then either the active notifications or an "all caught up" empty state, and a
 * collapsible list of dismissed notifications, each restorable.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.active    Active notification rows.
 * @param {Array}    props.dismissed Dismissed notification rows.
 * @param {Function} props.onDismiss Dismiss handler.
 * @param {Function} props.onRestore Restore handler.
 * @return {JSX.Element} The card.
 */
const NeedsAttention = ( { active, dismissed, onDismiss, onRestore } ) => {
	const [ showDismissed, setShowDismissed ] = useState( false );
	// Track what was dismissed this session so it stays restorable even when the
	// server drops it from its own dismissed list after a dismiss.
	const [ sessionDismissed, setSessionDismissed ] = useState( [] );

	const handleDismiss = ( notification ) => {
		setSessionDismissed( ( prev ) =>
			prev.some( ( item ) => item.id === notification.id )
				? prev
				: [ ...prev, notification ]
		);
		onDismiss( notification.id );
	};

	const handleRestore = ( id ) => {
		setSessionDismissed( ( prev ) =>
			prev.filter( ( item ) => item.id !== id )
		);
		onRestore( id );
	};

	// The dismissed list the server reports plus this session's dismissals,
	// de-duplicated by id and never showing anything that is currently active.
	const activeIds = new Set( active.map( ( item ) => item.id ) );
	const seen = new Set();
	const dismissedList = [ ...dismissed, ...sessionDismissed ].filter(
		( item ) => {
			if ( seen.has( item.id ) || activeIds.has( item.id ) ) {
				return false;
			}
			seen.add( item.id );
			return true;
		}
	);

	return (
		<div className="gtmkit-mb-6 gtmkit-overflow-hidden gtmkit-rounded-[10px] gtmkit-border gtmkit-border-border-default gtmkit-bg-white">
			<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2 gtmkit-px-5 gtmkit-py-4">
				<h3 className="gtmkit-m-0 gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary">
					{ __( 'Needs attention', 'gtm-kit' ) }
				</h3>
				<span
					className={ `gtmkit-inline-flex gtmkit-items-center gtmkit-rounded-[9px] gtmkit-px-[7px] gtmkit-py-0.5 gtmkit-text-[11px] gtmkit-font-medium ${
						active.length > 0
							? 'gtmkit-bg-[#fcf3d6] gtmkit-text-[#8a6d00]'
							: 'gtmkit-bg-[#f0f0f1] gtmkit-text-text-muted'
					}` }
				>
					{ active.length }
				</span>
			</div>

			{ active.length > 0 ? (
				active.map( ( notification, index ) => (
					<HealthRow
						key={ notification.id || index }
						notification={ notification }
						onDismiss={ handleDismiss }
					/>
				) )
			) : (
				<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2 gtmkit-border-t gtmkit-border-border-default gtmkit-px-5 gtmkit-py-4 gtmkit-text-[13px]">
					<span className="gtmkit-font-medium gtmkit-text-[#1a7f37]">
						✓
					</span>
					<span className="gtmkit-text-text-secondary">
						{ __(
							"You're all caught up. Nothing needs attention right now.",
							'gtm-kit'
						) }
					</span>
				</div>
			) }

			{ dismissedList.length > 0 && (
				<>
					<div className="gtmkit-border-t gtmkit-border-border-default gtmkit-bg-[#fafafa] gtmkit-px-5 gtmkit-py-3">
						<button
							type="button"
							onClick={ () =>
								setShowDismissed( ( shown ) => ! shown )
							}
							className="gtmkit-cursor-pointer gtmkit-border-0 gtmkit-bg-transparent gtmkit-p-0 gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-brand-primary hover:gtmkit-underline"
						>
							{ showDismissed
								? sprintf(
										/* translators: %d: number of dismissed notifications. */
										__(
											'Hide dismissed notifications (%d)',
											'gtm-kit'
										),
										dismissedList.length
								  )
								: sprintf(
										/* translators: %d: number of dismissed notifications. */
										__(
											'Show dismissed notifications (%d)',
											'gtm-kit'
										),
										dismissedList.length
								  ) }
						</button>
					</div>
					{ showDismissed &&
						dismissedList.map( ( notification, index ) => (
							<DismissedRow
								key={ notification.id || index }
								notification={ notification }
								onRestore={ handleRestore }
							/>
						) ) }
				</>
			) }
		</div>
	);
};

/**
 * Dashboard (home): an at-a-glance overview of the GTM Kit setup. Status
 * metrics, a primary next-step, the derived "Needs attention" list and learning
 * resources, all driven from the live settings.
 *
 * @return {JSX.Element} The dashboard.
 */
const DashboardPage = () => {
	const { settings } = useContext( SettingsDataContext );
	const { notifications, dismissNotification, restoreNotification } =
		useNotification();
	const metrics = getDashboardMetrics( settings );
	const checks = getDashboardNotifications( notifications );
	const dismissed = getDashboardDismissed( notifications );
	const tutorials = SettingsService.getTutorials().slice( 0, 2 );

	return (
		<>
			<div className="gtmkit-mb-6">
				<h2 className="gtmkit-m-0 gtmkit-mb-1 gtmkit-text-2xl gtmkit-font-bold gtmkit-text-text-primary">
					{ __( 'Dashboard', 'gtm-kit' ) }
				</h2>
				<p className="gtmkit-m-0 gtmkit-text-sm gtmkit-text-text-muted">
					{ __( 'Overview of your GTM Kit setup', 'gtm-kit' ) }
				</p>
			</div>

			<div className="gtmkit-mb-6 gtmkit-grid gtmkit-grid-cols-1 gtmkit-gap-4 sm:gtmkit-grid-cols-2 lg:gtmkit-grid-cols-4">
				{ metrics.map( ( metric ) => (
					<MetricCard key={ metric.label } metric={ metric } />
				) ) }
				<TelemetryCard
					active={ isOn( settings?.general?.analytics_active ) }
				/>
			</div>

			<div className="gtmkit-mb-6 gtmkit-flex gtmkit-items-center gtmkit-justify-between gtmkit-gap-4 gtmkit-rounded-md gtmkit-border gtmkit-border-[#cfe0fb] gtmkit-bg-[#eff6ff] gtmkit-px-5 gtmkit-py-[18px]">
				<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-col gtmkit-gap-1">
					<div className="gtmkit-flex gtmkit-items-center gtmkit-gap-2.5">
						<span className="gtmkit-text-[15px] gtmkit-font-semibold gtmkit-text-text-primary">
							{ __( 'Set up your GTM container', 'gtm-kit' ) }
						</span>
						<span
							className={ `${ BADGE } gtmkit-bg-[#fcf3d6] gtmkit-text-[#8a6d00]` }
						>
							{ __( 'Recommended next step', 'gtm-kit' ) }
						</span>
					</div>
					<p className="gtmkit-m-0 gtmkit-text-[13px] gtmkit-leading-normal gtmkit-text-text-secondary">
						{ __(
							'GTM Kit is sending data to your container. Generate the Tags, Triggers and Variables you still need in Google Tag Manager.',
							'gtm-kit'
						) }
					</p>
				</div>
				<Link
					to="/gtm-templates"
					className="gtmkit-inline-flex gtmkit-shrink-0 gtmkit-items-center gtmkit-rounded-sm gtmkit-bg-brand-primary gtmkit-px-4 gtmkit-py-[9px] gtmkit-text-[13px] gtmkit-font-medium gtmkit-text-white hover:gtmkit-opacity-90"
				>
					{ __( 'Open Template Assistant', 'gtm-kit' ) }
				</Link>
			</div>

			<NeedsAttention
				active={ checks }
				dismissed={ dismissed }
				onDismiss={ dismissNotification }
				onRestore={ restoreNotification }
			/>

			<InfoCard title={ __( 'Resources', 'gtm-kit' ) }>
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
				<InfoRow
					title={ __( 'All tutorials', 'gtm-kit' ) }
					subtitle={ __(
						'Browse the full guide library',
						'gtm-kit'
					) }
					right={
						<ExternalAction
							href={ DOCS_URL }
							label={ __( 'Read', 'gtm-kit' ) }
						/>
					}
				/>
			</InfoCard>
		</>
	);
};

export default DashboardPage;
