
document.addEventListener( 'wpcf7mailsent', function( event ) {
	window.dataLayer.push({
		"event" : "gtmkit.CF7MailSent",
		"formId" : event.detail.contactFormId,
		"response" : event.detail.inputs
	})
});
