/*WordPress*/
import { __ } from '@wordpress/i18n';
import {
	useContext,
	useState,
	useEffect,
	useCallback,
	lazy,
	Suspense,
} from '@wordpress/element';
import { SlotFillProvider } from '@wordpress/components';
import { PluginArea } from '@wordpress/plugins';
import { Route, Routes, Navigate, useLocation } from 'react-router-dom';
import { LEGACY_ROUTES } from '../../registry/legacyRoutes';

/*Inbuilt Components*/
import Sidebar from './Sidebar';
import TopBar from './TopBar';
import SearchResults from './SearchResults';
import CapabilityPage from './CapabilityPage';
import DashboardPage from './DashboardPage';
import IntegrationsHub from './IntegrationsHub';
import IntegrationConfig from './IntegrationConfig';
import LicensePage from './LicensePage';
import SupportPage from './SupportPage';
import SectionErrorBoundary from '../../components/SectionErrorBoundary';
import { SkeletonPage } from '../../components/Skeleton';
import { ToastContainer } from '../../components/Toast';
import { SettingsDataContext } from '../../context/SettingsDataContext';
import { FilterProvider } from '../../context/FilterContext';
import { ToastContext } from '../../context/ToastContext';
import SettingsService from '../../services/SettingsService';

/*Reused non-field shell pages and flows*/
const GtmTemplates = lazy( () => import( '../pages/templates' ) );

/**
 * A capability route: renders the page entirely from the registry inside a
 * section error boundary.
 *
 * @param {Object} props              Component props.
 * @param {string} props.capabilityId Capability id.
 * @param {string} props.label        Boundary label.
 * @return {JSX.Element} The boundaried capability page.
 */
const Capability = ( { capabilityId, label } ) => (
	<SectionErrorBoundary sectionName={ label }>
		<CapabilityPage capabilityId={ capabilityId } />
	</SectionErrorBoundary>
);

/**
 * Redirect a legacy route to its new capability route, preserving the query
 * string so anchor deep links (`?focus=…`) survive.
 *
 * @param {Object} props    Component props.
 * @param {string} props.to The target route id.
 * @return {JSX.Element} A redirect.
 */
const LegacyRedirect = ( { to } ) => {
	const { search } = useLocation();
	return <Navigate replace to={ { pathname: `/${ to }`, search } } />;
};

/**
 * The registry-driven settings shell: a two-zone sidebar beside a routed main
 * column. Mounted in place of the legacy page router when the shell flag is on.
 *
 * @return {JSX.Element} The shell.
 */
const ShellApp = () => {
	const { useSettings } = useContext( SettingsDataContext );
	const { toasts } = useContext( ToastContext );
	const [ query, setQuery ] = useState( '' );
	const { pathname } = useLocation();

	const clearSearch = useCallback( () => setQuery( '' ), [] );

	// Clear the search when the route changes, so navigating shows the
	// destination page instead of stale results. A route change does not fire
	// when re-clicking the active item, so the sidebar also clears on click.
	useEffect( () => {
		setQuery( '' );
	}, [ pathname ] );

	const ready = Object.keys( useSettings ).length > 0;
	const searching = ready && query.trim() !== '';

	return (
		<SlotFillProvider>
			<FilterProvider>
				<div
					className="gtmkit-flex gtmkit-items-stretch gtmkit-min-h-screen gtmkit-bg-page"
					style={ {
						'--gtmkit-color-button':
							'var(--gtmkit-color-brand-primary)',
						'--wp-admin-theme-color':
							'var(--gtmkit-color-brand-primary)',
						'--wp-admin-theme-color-darker-10': '#1d4ed8',
						'--wp-admin-theme-color-darker-20':
							'var(--gtmkit-color-info)',
					} }
				>
					<Sidebar onNavigate={ clearSearch } />
					<div className="gtmkit-flex gtmkit-min-w-0 gtmkit-flex-1 gtmkit-flex-col">
						<TopBar query={ query } onSearch={ setQuery } />
						<main className="gtmkit-flex-1 gtmkit-px-8 gtmkit-py-8 gtmkit-text-base">
							<div className="gtmkit-mx-auto gtmkit-max-w-screen-lg min-[1440px]:gtmkit-max-w-[1440px]">
								{ searching && (
									<SearchResults
										query={ query }
										onNavigate={ () => setQuery( '' ) }
									/>
								) }
								{ ! searching && ! ready && (
									<SkeletonPage sections={ 2 } />
								) }
								{ ! searching && ready && (
									<Suspense
										fallback={
											<SkeletonPage sections={ 2 } />
										}
									>
										<Routes>
											<Route
												path="/dashboard"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'Dashboard',
															'gtm-kit'
														) }
													>
														<DashboardPage />
													</SectionErrorBoundary>
												}
											/>
											<Route
												path="/setup"
												element={
													<Capability
														capabilityId="setup"
														label={ __(
															'Setup',
															'gtm-kit'
														) }
													/>
												}
											/>
											<Route
												path="/gtm-templates"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'GTM Templates',
															'gtm-kit'
														) }
													>
														<GtmTemplates
															templateData={ SettingsService.getTemplates() }
														/>
													</SectionErrorBoundary>
												}
											/>
											<Route
												path="/events"
												element={
													<Capability
														capabilityId="events"
														label={ __(
															'Events & data layer',
															'gtm-kit'
														) }
													/>
												}
											/>
											<Route
												path="/commerce"
												element={
													<Capability
														capabilityId="commerce"
														label={ __(
															'Commerce',
															'gtm-kit'
														) }
													/>
												}
											/>
											<Route
												path="/consent"
												element={
													<Capability
														capabilityId="consent"
														label={ __(
															'Consent & privacy',
															'gtm-kit'
														) }
													/>
												}
											/>
											<Route
												path="/tools"
												element={
													<Capability
														capabilityId="tools"
														label={ __(
															'Tools',
															'gtm-kit'
														) }
													/>
												}
											/>
											<Route
												path="/integrations"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'Integrations',
															'gtm-kit'
														) }
													>
														<IntegrationsHub />
													</SectionErrorBoundary>
												}
											/>
											<Route
												path="/integrations/:slug"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'Integration',
															'gtm-kit'
														) }
													>
														<IntegrationConfig />
													</SectionErrorBoundary>
												}
											/>
											<Route
												path="/license"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'License',
															'gtm-kit'
														) }
													>
														<LicensePage />
													</SectionErrorBoundary>
												}
											/>
											<Route
												path="/support"
												element={
													<SectionErrorBoundary
														sectionName={ __(
															'Support',
															'gtm-kit'
														) }
													>
														<SupportPage />
													</SectionErrorBoundary>
												}
											/>
											{ Object.entries(
												LEGACY_ROUTES
											).map( ( [ from, to ] ) => (
												<Route
													key={ from }
													path={ `/${ from }` }
													element={
														<LegacyRedirect
															to={ to }
														/>
													}
												/>
											) ) }
											<Route
												path="*"
												element={
													<Navigate
														replace
														to="/dashboard"
													/>
												}
											/>
										</Routes>
									</Suspense>
								) }
							</div>
						</main>
					</div>
				</div>
			</FilterProvider>
			<PluginArea />
			<ToastContainer toasts={ toasts } />
		</SlotFillProvider>
	);
};

export default ShellApp;
