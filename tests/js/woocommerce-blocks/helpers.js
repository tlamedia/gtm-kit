/**
 * Shared test helpers for the WooCommerce block subscribers.
 *
 * Installs a fake `window.gtmkit.events.push` seam that records pushed
 * events onto the named dataLayer, mirroring the runtime seam the PHP
 * inline script installs in a real browser.
 */

/**
 * Seed the gtmkit window globals and a recording push seam.
 *
 * @param {Object} [options]               Overrides.
 * @param {Object} [options.settings]       Extra `window.gtmkit_settings`.
 * @param {Object} [options.data]           Extra `window.gtmkit_data`.
 * @return {{events: () => Array<Object>}} Accessor for the recorded events.
 */
export const installSeam = ( { settings = {}, data = {} } = {} ) => {
	window.gtmkit_settings = {
		datalayer_name: 'dataLayer',
		console_log: false,
		...settings,
	};
	window.gtmkit_data = {
		wc: { currency: 'USD' },
		...data,
	};
	window.dataLayer = [];
	window.gtmkit = {
		events: {
			push: ( event, name = 'dataLayer' ) => {
				window[ name ] = window[ name ] || [];
				window[ name ].push( event );
			},
		},
	};

	return {
		events: () =>
			window.dataLayer.filter(
				( entry ) =>
					entry && typeof entry === 'object' && 'event' in entry
			),
	};
};

/**
 * Build a fake wp.data accessor backed by a mutable store map.
 *
 * @param {Object<string, Object>} stores Map of store key to selector bundle.
 * @return {{select: Function, subscribe: Function, notify: Function, setStore: Function}}
 */
export const fakeData = ( stores = {} ) => {
	const listeners = new Set();
	const map = { ...stores };

	return {
		select: ( key ) => map[ key ],
		subscribe: ( listener ) => {
			listeners.add( listener );
			return () => listeners.delete( listener );
		},
		notify: () => listeners.forEach( ( l ) => l() ),
		setStore: ( key, value ) => {
			map[ key ] = value;
		},
	};
};

/**
 * Flush pending microtasks so a microtask-queued handler runs.
 *
 * @return {Promise<void>}
 */
export const flushMicrotasks = async () => {
	await Promise.resolve();
	await Promise.resolve();
};
