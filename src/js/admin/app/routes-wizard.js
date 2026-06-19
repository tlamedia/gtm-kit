/**
 * SCSS
 */
import './style-wizard.scss';

/*WordPress*/
import { __ } from '@wordpress/i18n';
import { createRoot, useContext, lazy, Suspense } from '@wordpress/element';

/*Inbuilt Context Provider*/
import {
	SettingsDataProvider,
	SettingsDataContext,
} from '../context/SettingsDataContext';
import { NotificationProvider } from '../context/NotificationContext';
import { LicenseProvider } from '../context/LicenseContext';
import { SupportProvider } from '../context/SupportContext';
import { SiteDataProvider } from '../context/SiteDataContext';
import { ToastProvider, ToastContext } from '../context/ToastContext';
import SettingsService from '../services/SettingsService';

/*Router*/
import { HashRouter, Route, Routes, Navigate } from 'react-router-dom';

/*Inbuilt Components - Loaded eagerly*/
import Header from './organisms/header-onbarding';
import Footer from './organisms/footer-onboarding';
import SectionErrorBoundary from '../components/SectionErrorBoundary';
import { SkeletonPage } from '../components/Skeleton';
import { ToastContainer } from '../components/Toast';

/*Page Components - Lazy loaded*/
const Welcome = lazy( () => import( './pages-wizard/welcome' ) );
const EssentialSettings = lazy( () =>
	import( './pages-wizard/essential-settings' )
);
const ShareAnonymousData = lazy( () =>
	import( './pages-wizard/share-anonymous-data' )
);
const GettingStarted = lazy( () => import( './pages-wizard/getting-started' ) );
const AutomaticUpdates = lazy( () =>
	import( './pages-wizard/automatic-updates' )
);

const SettingRouters = () => {
	const { useSettings } = useContext( SettingsDataContext );
	const { toasts } = useContext( ToastContext );

	if ( ! Object.keys( useSettings ).length ) {
		return (
			<>
				<Header />
				<main className="gtmkit-max-w-3xl gtmkit-bg-white gtmkit-border-1 gtmkit-border-color-border gtmkit-rounded-md gtmkit-mx-auto gtmkit-py-12 gtmkit-px-16 gtmkit-text-base">
					<SkeletonPage sections={ 1 } showTitle={ false } />
				</main>
				<Footer />
			</>
		);
	}
	return (
		<>
			<Header />
			<main className="gtmkit-max-w-3xl gtmkit-bg-white gtmkit-border-1 gtmkit-border-color-border gtmkit-rounded-md gtmkit-mx-auto gtmkit-py-12 gtmkit-px-16 gtmkit-text-base">
				<Suspense
					fallback={
						<SkeletonPage sections={ 1 } showTitle={ false } />
					}
				>
					<Routes>
						<Route
							exact
							path="/welcome"
							element={
								<SectionErrorBoundary
									sectionName={ __( 'Welcome', 'gtm-kit' ) }
								>
									<Welcome />
								</SectionErrorBoundary>
							}
						/>
						<Route
							exact
							path="/essential-settings"
							element={
								<SectionErrorBoundary
									sectionName={ __(
										'Essential Settings',
										'gtm-kit'
									) }
								>
									<EssentialSettings />
								</SectionErrorBoundary>
							}
						/>
						<Route
							exact
							path={ '/share-anonymous-data' }
							element={
								<SectionErrorBoundary
									sectionName={ __(
										'Share Anonymous Data',
										'gtm-kit'
									) }
								>
									<ShareAnonymousData />
								</SectionErrorBoundary>
							}
						/>
						<Route
							exact
							path={ '/automatic-updates' }
							element={
								<SectionErrorBoundary
									sectionName={ __(
										'Automatic Updates',
										'gtm-kit'
									) }
								>
									<AutomaticUpdates />
								</SectionErrorBoundary>
							}
						/>
						<Route
							exact
							path={ '/getting-started' }
							element={
								<SectionErrorBoundary
									sectionName={ __(
										'Getting Started',
										'gtm-kit'
									) }
								>
									<GettingStarted />
								</SectionErrorBoundary>
							}
						/>

						<Route
							path="/"
							element={ <Navigate replace to={ '/welcome' } /> }
						/>
					</Routes>
				</Suspense>
			</main>
			<Footer />
			<ToastContainer toasts={ toasts } />
		</>
	);
};

const InitSettings = () => {
	return (
		<HashRouter basename="/">
			<ToastProvider>
				<SettingsDataProvider>
					<NotificationProvider>
						<LicenseProvider>
							<SupportProvider>
								<SiteDataProvider>
									<SettingRouters />
								</SiteDataProvider>
							</SupportProvider>
						</LicenseProvider>
					</NotificationProvider>
				</SettingsDataProvider>
			</ToastProvider>
		</HashRouter>
	);
};

document.addEventListener( 'DOMContentLoaded', () => {
	const rootElement = document.getElementById( SettingsService.getRootId() );
	if ( rootElement !== null && rootElement !== undefined ) {
		const root = createRoot( rootElement );
		root.render( <InitSettings /> );
	}
} );
