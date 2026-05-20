		window.gtmkit_settings = {"datalayer_name":"dataLayer","console_log":""};
		window.gtmkit_data = {};
		window.dataLayer = window.dataLayer || [];
		window.gtmkit = window.gtmkit || {};
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
		window.gtmkit.consent = {
			state: {
				'ad_personalization': 'denied',
				'ad_storage': 'denied',
				'ad_user_data': 'denied',
				'analytics_storage': 'denied',
				'personalization_storage': 'denied',
				'functionality_storage': 'denied',
				'security_storage': 'denied'
			},
			update: function (state) {
				if (state && typeof state === 'object') {
					for (var k in state) {
						if (Object.prototype.hasOwnProperty.call(state, k)) {
							window.gtmkit.consent.state[k] = state[k];
						}
					}
				}
				if (typeof gtag !== 'undefined') {
					gtag('consent', 'update', state);
				}
				window.dispatchEvent(new CustomEvent('gtmkit:consent:updated', { detail: state }));
			}
		};
				window.gtmkit.events = window.gtmkit.events || {};
		window.gtmkit.events.push = window.gtmkit.events.push || function (event, name) {
			var layerName = (typeof name === 'string' && name) ? name : 'dataLayer';
			window[layerName] = window[layerName] || [];
			var events = window.gtmkit.events;
			if (typeof events.shouldDefer === 'function') {
				var eventName = (event && typeof event === 'object' && typeof event.event === 'string') ? event.event : '';
				var consentState = (window.gtmkit.consent && window.gtmkit.consent.state) ? window.gtmkit.consent.state : undefined;
				if (events.shouldDefer(eventName, event, consentState)) {
					if (typeof events.deferralSink === 'function') {
						events.deferralSink(event, layerName);
						return window[layerName];
					}
				}
			}
			window[layerName].push(event);
			return window[layerName];
		};
		window.gtmkit.events.registerDeferralSink = window.gtmkit.events.registerDeferralSink || function (fn) {
			if (typeof fn === 'function') {
				window.gtmkit.events.deferralSink = fn;
			}
		};
		