const datalayer_name = window["gtmkit_settings"].datalayer_name;

document.addEventListener( 'wpcf7mailsent', function( event ) {
	window[datalayer_name].push({
		"event" : "gtmkit.CF7MailSent",
		"formId" : event.detail.contactFormId,
		"response" : event.detail.inputs
	})
});
