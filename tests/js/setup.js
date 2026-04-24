/**
 * Vitest setup file.
 *
 * Runs once per test worker before any test file. Seeds the minimal
 * gtm-kit window globals that helpers under src/js/ can assume exist
 * in a real browser (where the PHP-emitted inline script registers
 * them). Individual tests remain responsible for populating concrete
 * values — this file only ensures the namespaces are defined.
 */

if ( typeof window !== 'undefined' ) {
	window.gtmkit_settings = window.gtmkit_settings || {};
	window.gtmkit_data = window.gtmkit_data || {};
}
