/**
 * Canonical dataLayer helpers for gtm-kit.
 *
 * Mirrors the idempotent initializer currently emitted inline by
 * Frontend::enqueue_settings_and_data_script() (src/Frontend/Frontend.php) —
 * `window[name] = window[name] || []` — extracted as an ES module so both
 * the PHP-rendered template and future JS-only code paths can share one
 * definition, and so the push semantics are testable in Vitest without a
 * PHP render step.
 *
 * The runtime parallel of `pushDataLayer` is the `window.gtmkit.events.push`
 * helper installed by the PHP inline block. Both consult the same optional
 * `window.gtmkit.events.shouldDefer` gate. Keep the two in sync when the
 * gating contract changes.
 *
 * Reference for the `tests/js/dataLayer.test.js` starter.
 *
 * @module src/js/dataLayer
 */

export function ensureDataLayer( name = 'dataLayer' ) {
	window[ name ] = window[ name ] || [];
	return window[ name ];
}

export function pushDataLayer( event, name = 'dataLayer' ) {
	const layer = ensureDataLayer( name );
	const events = window.gtmkit && window.gtmkit.events;

	if ( events && typeof events.shouldDefer === 'function' ) {
		const eventName =
			event && typeof event === 'object' && typeof event.event === 'string'
				? event.event
				: '';
		const consentState =
			window.gtmkit && window.gtmkit.consent && window.gtmkit.consent.state
				? window.gtmkit.consent.state
				: undefined;

		if ( events.shouldDefer( eventName, event, consentState ) ) {
			if ( typeof events.deferralSink === 'function' ) {
				events.deferralSink( event, name );
				return layer;
			}
			// No sink registered: fall through and push, so the event is
			// never lost when the gate fires without a queue listening.
		}
	}

	layer.push( event );
	return layer;
}
