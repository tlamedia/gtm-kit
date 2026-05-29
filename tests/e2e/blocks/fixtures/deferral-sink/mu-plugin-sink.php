<?php
/**
 * Plugin Name: GTM Kit E2E — Block Deferral Sink
 * Description: Registers a minimal consent-deferral sink against the 2.13.0 runtime seam so the block consent-deferred spec can prove block events buffer until consent is granted, then flush in order. Stands in for the Premium Event Deferral Queue, which does not ship in core.
 *
 * Mounted by wp-env's tests environment via the `mappings` block in
 * .wp-env.json. Production code is unaffected; only the e2e tests env
 * loads this file.
 *
 * Gated behind the `gtmkit_e2e_deferral` cookie so only the deferral spec
 * opts in; the purchase / mini-cart / collection specs see events fire
 * immediately.
 *
 * @package GTM Kit
 */

if ( empty( $_COOKIE['gtmkit_e2e_deferral'] ) ) {
	return;
}

// Expose the Consent Mode v2 default surface so window.gtmkit.consent
// exists and every category defaults to denied (opt-in), which puts the
// sink into the deferring state on initial page load.
add_filter( 'gtmkit_consent_default_settings_enabled', '__return_true' );
add_filter( 'wp_get_consent_type', static fn() => 'optin' );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! wp_script_is( 'gtmkit', 'enqueued' ) && ! wp_script_is( 'gtmkit', 'registered' ) ) {
			return;
		}

		$js = <<<'JS'
( function () {
	window.gtmkit = window.gtmkit || {};
	window.gtmkit.events = window.gtmkit.events || {};

	var buffer = [];

	function analyticsGranted() {
		var consent = window.gtmkit.consent;
		return !! ( consent && consent.state && consent.state.analytics_storage === 'granted' );
	}

	// Defer every event while analytics consent is not granted.
	window.gtmkit.events.shouldDefer = function () {
		return ! analyticsGranted();
	};

	function sink( event, name ) {
		buffer.push( [ event, ( typeof name === 'string' && name ) ? name : 'dataLayer' ] );
	}

	if ( typeof window.gtmkit.events.registerDeferralSink === 'function' ) {
		window.gtmkit.events.registerDeferralSink( sink );
	} else {
		window.gtmkit.events.deferralSink = sink;
	}

	function flush() {
		if ( ! analyticsGranted() ) {
			return;
		}
		var pending = buffer;
		buffer = [];
		pending.forEach( function ( entry ) {
			var event = entry[ 0 ];
			var name = entry[ 1 ];
			window[ name ] = window[ name ] || [];
			window[ name ].push( event );
		} );
	}

	window.addEventListener( 'gtmkit:consent:updated', flush );
}() );
JS;

		wp_add_inline_script( 'gtmkit', $js, 'after' );
	},
	20
);
