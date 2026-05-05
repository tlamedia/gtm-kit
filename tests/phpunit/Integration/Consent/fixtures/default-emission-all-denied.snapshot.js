		window.gtmkit_settings = {"datalayer_name":"dataLayer","console_log":""};
		window.gtmkit_data = {};
		window.dataLayer = window.dataLayer || [];
				if (typeof gtag === "undefined") {
			function gtag(){dataLayer.push(arguments);}
			gtag('consent', 'default', {
				'ad_personalization': 'denied',
				'ad_storage': 'denied',
				'ad_user_data': 'denied',
				'analytics_storage': 'denied',
				'personalization_storage': 'denied',
				'functionality_storage': 'denied',
				'security_storage': 'denied'
			});
								} else if ( window.gtmkit_settings.console_log === 'on' ) {
			console.warn('GTM Kit: gtag is already defined')
		}
		window.gtmkit = window.gtmkit || {};
		window.gtmkit.consent = {
			update: function (state) {
				if (typeof gtag !== 'undefined') {
					gtag('consent', 'update', state);
				}
				window.dispatchEvent(new CustomEvent('gtmkit:consent:updated', { detail: state }));
			}
		};
				