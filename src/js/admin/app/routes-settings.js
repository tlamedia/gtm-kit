/**
 * SCSS
 */
import './style-settings.scss';

/*WordPress*/
import { createRoot } from '@wordpress/element';

/*Inbuilt Context Provider*/
import { SettingsDataProvider } from '../context/SettingsDataContext';
import { NotificationProvider } from '../context/NotificationContext';
import { LicenseProvider } from '../context/LicenseContext';
import { SupportProvider } from '../context/SupportContext';
import { SiteDataProvider } from '../context/SiteDataContext';
import { ToastProvider } from '../context/ToastContext';
import SettingsService from '../services/SettingsService';

/*Router*/
import { HashRouter } from 'react-router-dom';

/*Settings shell*/
import ShellApp from './shell/ShellApp';

const InitSettings = () => {
	return (
		<HashRouter basename="/">
			<ToastProvider>
				<SettingsDataProvider>
					<NotificationProvider>
						<LicenseProvider>
							<SupportProvider>
								<SiteDataProvider>
									<ShellApp />
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
