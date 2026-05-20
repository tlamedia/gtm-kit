		window.gtmkit_settings = {"datalayer_name":"dataLayer","console_log":""};
		window.gtmkit_data = {};
		window.dataLayer = window.dataLayer || [];
		window.gtmkit = window.gtmkit || {};
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
		