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
	layer.push( event );
	return layer;
}
