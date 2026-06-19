import SettingsService from '../../services/SettingsService';

export const getAdminLink = ( adminPage = 'general', destination = '' ) => {
	const adminPageUrl = SettingsService.getAdminPageUrl();
	return destination
		? `${ adminPageUrl }${ adminPage }#/${ destination }`
		: `${ adminPageUrl }${ adminPage }`;
};
