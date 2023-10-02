document.addEventListener('wpcf7mailsent', function (event) {
	window[window.gtmkit_settings.datalayer_name].push({
		event: 'gtmkit.CF7MailSent',
		formId: event.detail.contactFormId,
		response: event.detail.inputs,
	});
});
