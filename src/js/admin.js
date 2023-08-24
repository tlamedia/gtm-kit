// eslint-disable-next-line no-undef
jQuery( function ( $ ) {
	/**
	 * Set the initial active tab in the settings pages.
	 *
	 * @return {void}
	 */
	function setInitialActiveTab() {
		let activeTabId = window.location.hash.replace( '#top#', '' );
		/* In some cases, the second # gets replace by %23, which makes the tab
		 * switching not work unless we do this. */
		if ( activeTabId.search( '#top' ) !== -1 ) {
			activeTabId = window.location.hash.replace( '#top%23', '' );
		}
		/*
		 * WordPress uses fragment identifiers for its own in-page links, e.g.
		 * `#wpbody-content` and other plugins may do that as well. Also, facebook
		 * adds a `#_=_` see PR 506. In these cases and when it's empty, default
		 * to the first tab.
		 */
		if ( '' === activeTabId || '#' === activeTabId.charAt( 0 ) ) {
			/*
			 * Reminder: jQuery attr() gets the attribute value for only the first
			 * element in the matched set so this will always be the first tab id.
			 */
			// eslint-disable-next-line no-undef
			activeTabId = jQuery( '.gtmkit-tab' ).attr( 'id' );
		}

		// eslint-disable-next-line no-undef
		jQuery( '#' + activeTabId ).addClass( 'active' );
		// eslint-disable-next-line no-undef
		jQuery( '#' + activeTabId + '-tab' )
			.addClass( 'active' )
			.trigger( 'click' );
	}

	$( document ).ready( function () {
		// Handle the settings pages tabs.
		$( '#gtmkit-tabs' )
			.find( 'a' )
			.on( 'click', function () {
				$( '#gtmkit-tabs' ).find( 'a' ).removeClass( 'active' );
				$( '.gtmkit-tab' ).removeClass( 'active' );

				const id = $( this ).attr( 'id' ).replace( '-tab', '' );
				const activeTab = $( '#' + id );
				activeTab.addClass( 'active' );
				$( this ).addClass( 'active' );
				if ( activeTab.hasClass( 'nosave' ) ) {
					$( '#gtmkit-submit-container' ).hide();
				} else {
					$( '#gtmkit-submit-container' ).show();
				}
			} );

		$( '.gtmkit-items-list' )
			.find( 'a.gtmkit-open-tab' )
			.on( 'click', function () {
				$( '#gtmkit-tabs' ).find( 'a' ).removeClass( 'active' );
				$( '.gtmkit-tab' ).removeClass( 'active' );

				const id = $( this )
					.attr( 'id' )
					.replace( 'gtmkit-open-tab-', '' );
				const activeTab = $( '#' + id );
				const activeNavTab = $( '#' + id + '-tab' );
				activeTab.addClass( 'active' );
				activeNavTab.addClass( 'active' );
				if ( activeTab.hasClass( 'nosave' ) ) {
					$( '#gtmkit-submit-container' ).hide();
				} else {
					$( '#gtmkit-submit-container' ).show();
				}
			} );
		setInitialActiveTab();
	} );
} );
