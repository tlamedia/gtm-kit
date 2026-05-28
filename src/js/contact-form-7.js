document.addEventListener( 'wpcf7mailsent', function ( event ) {
	const datalayerName = window.gtmkit_settings.datalayer_name;
	const formMeta = {
		formId: event.detail.contactFormId,
		response: event.detail.inputs,
	};

	window.gtmkit.events.push(
		Object.assign( { event: 'gtmkit.CF7MailSent' }, formMeta ),
		datalayerName
	);

	// Pair the existing CF7 event with the GA4 standard generate_lead
	// event when the engagement-event toggle is on. Keeping both pushes
	// in the same listener preserves the single-source-of-truth contract
	// for CF7 form events and lets GTM trigger authors rely on the order
	// (CF7MailSent first, then generate_lead, same tick).
	const engagementConfig = window.gtmkitCf7Engagement || {};
	if ( ! engagementConfig.generateLeadEnabled ) {
		return;
	}

	const leadPayload = Object.assign( { event: 'generate_lead' }, formMeta );
	if (
		engagementConfig.payload &&
		typeof engagementConfig.payload === 'object'
	) {
		Object.keys( engagementConfig.payload ).forEach( function ( key ) {
			leadPayload[ key ] = engagementConfig.payload[ key ];
		} );
	}
	window.gtmkit.events.push( leadPayload, datalayerName );
} );
