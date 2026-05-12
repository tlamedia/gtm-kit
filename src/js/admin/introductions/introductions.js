/**
 * Entry point for the introductions modal. Mounts the React modal on
 * the `gtmkit-introductions-root` div the integration prints in the
 * admin footer, after registering the bundled components and exposing
 * the public window API for sibling plugins.
 */

import { createRoot } from '@wordpress/element';
import { doAction } from '@wordpress/hooks';

import IntroductionsModal from './IntroductionsModal.jsx';
import { registerComponent } from './componentRegistry';

import Welcome from './components/Welcome.jsx';

const MOUNT_ID  = 'gtmkit-introductions-root';
const READY_TAG = 'gtmkit.introductions.ready';

/**
 * Build and install the public window API.
 *
 * Sibling plugins call `window.gtmkit.introductions.registerComponent`
 * to register their bundled intros. Bundles loaded before this entry
 * push pending registrations to `_pendingRegistrations`; we drain that
 * queue before mounting so no registration is lost to a load-order
 * race.
 */
function installPublicApi() {
	window.gtmkit               = window.gtmkit || {};
	window.gtmkit.introductions = window.gtmkit.introductions || {};

	const pending = Array.isArray( window.gtmkit.introductions._pendingRegistrations )
		? window.gtmkit.introductions._pendingRegistrations
		: [];

	window.gtmkit.introductions.registerComponent = registerComponent;
	window.gtmkit.introductions._pendingRegistrations = pending;

	for ( const entry of pending ) {
		if ( entry && typeof entry.id === 'string' && entry.Component ) {
			registerComponent( entry.id, entry.Component );
		}
	}
	window.gtmkit.introductions._pendingRegistrations = [];
}

function boot() {
	installPublicApi();

	registerComponent( 'welcome', Welcome );

	doAction( READY_TAG );

	const mount = document.getElementById( MOUNT_ID );
	if ( ! mount ) {
		return;
	}

	const payload = window.gtmkitIntroductions || {};
	const intros  = Array.isArray( payload.introductions ) ? payload.introductions : [];
	if ( intros.length === 0 ) {
		return;
	}

	const root = createRoot( mount );
	root.render(
		<IntroductionsModal
			intros={ intros }
			restRoot={ payload.restRoot || '' }
			nonce={ payload.nonce || '' }
		/>
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
