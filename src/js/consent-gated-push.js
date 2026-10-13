/**
 * Push an event whose contact details wait for advertising consent.
 *
 * The purchase event can carry the buyer's contact details for Enhanced
 * Conversions. They are sent only once the visitor has granted both
 * `ad_storage` and `ad_user_data`. The server cannot know that when it builds
 * the page, and deciding it there would freeze one visitor's answer into
 * cached HTML, so the page carries the details apart from the event and this
 * script decides in the browser.
 *
 * Consent is read from every place it can be set, because a consent platform
 * does not always use the data layer GTM Kit pushes to:
 *
 * - the Consent Mode commands in the data layer the event goes to, and in
 *   `dataLayer` when that is a different layer, which is where `gtag()`
 *   commands land when a consent platform defined `gtag` first;
 * - GTM Kit's consent bridge, `window.gtmkit.consentBridge`, which GTM Kit's
 *   consent bridge template reports Google's consent state into through the
 *   documented template APIs. It is the documented way consent set through a
 *   GTM template reaches the page: a value reported as a change is the
 *   visitor's answer, and one reported as initial is the default Google Tag
 *   Manager resolved for this visitor, region included. A grant reported as
 *   initial before any consent state exists cannot be told apart from a site
 *   with no consent platform at all, because the template reads "no state"
 *   as granted, so such a grant only shortens the wait when Google Tag
 *   Manager's own state confirms the site set consent for that category;
 *   otherwise it counts as no consent state, and at the end of the wait it
 *   counts as a grant only where the site treats unknown consent as allowed
 *   and no denying default that applies to every region opposes it;
 * - the consent state Google Tag Manager holds once it has loaded, the
 *   fallback for a container without the bridge template, where it is the
 *   only place consent set through a GTM template is visible. This is not a
 *   published interface, so it is read defensively and anything unexpected
 *   counts as no consent state;
 * - `window.gtmkit.consent`: answers announced with the
 *   `gtmkit:consent:updated` event, and a denial in its state. A grant there
 *   is not enough on its own, because the state starts from GTM Kit's
 *   defaults without their region.
 *
 * An answer beats a default, and a denial from any place beats a grant.
 *
 * The whole event waits, not only the details: tags read the contact details
 * when the event is processed, so details added afterwards would never reach
 * the conversion. The wait ends as soon as consent is known:
 *
 * - both categories granted: the event is pushed with the details;
 * - an update leaves either category denied: pushed without them;
 * - no answer within the wait, or the page is left or hidden: pushed without
 *   them when the page holds any consent state for these categories.
 *
 * Hidden counts as left because a mobile browser that switches apps often
 * hides the page without ever reporting that it was left, then stops its
 * timers and may discard it, and a held event would be lost with it.
 *
 * No consent state for these categories anywhere means the site does not use
 * Consent Mode for them, and consent is unknown rather than denied. Unknown
 * consent sends the details, unless the site asks for it to withhold them.
 * The full wait still applies first, so a consent platform that loads late
 * can still set its defaults.
 *
 * A default is only a starting point, so a denying default alone keeps the
 * event waiting for the visitor's answer. A default scoped to regions cannot
 * be read from a data layer, because the visitor's region is not known in
 * the page, so it counts as no answer unless Google Tag Manager has already
 * resolved it.
 *
 * Installed once as `window.gtmkit.events.pushWhenConsented`, which hands the
 * event to `window.gtmkit.events.push` so every other rule for pushing events,
 * such as a deferral queue, still applies.
 *
 * While an event waits, `window.gtmkit.events.consentHold` tells other scripts
 * so, for example one that watches for the purchase event to appear:
 *
 * - `pending`: true while the event is held, false once it has been handed on,
 *   however the wait ended;
 * - `until`: the latest time the event can be handed on, in milliseconds since
 *   the epoch, as `Date.now()` reports it.
 *
 * It is set only when an event actually waits. When consent is already known,
 * the event is pushed at once and `consentHold` is left undefined, so a reader
 * can tell "never held" from "held and released".
 */
( function ( window ) {
	var WAIT_MS = 3000;
	var POLL_MS = 100;
	var REQUIRED = [ 'ad_storage', 'ad_user_data' ];

	window.gtmkit = window.gtmkit || {};
	window.gtmkit.events = window.gtmkit.events || {};

	if ( typeof window.gtmkit.events.pushWhenConsented === 'function' ) {
		return;
	}

	function isConsentCommand( entry ) {
		return (
			!! entry &&
			typeof entry === 'object' &&
			entry[ 0 ] === 'consent' &&
			( entry[ 1 ] === 'default' || entry[ 1 ] === 'update' ) &&
			!! entry[ 2 ] &&
			typeof entry[ 2 ] === 'object'
		);
	}

	/**
	 * Record one value for a category, keeping only `granted` and `denied`.
	 *
	 * @param {Object} bucket   Values per category, each a list.
	 * @param {string} category The category.
	 * @param {*}      value    The value found.
	 */
	function note( bucket, category, value ) {
		if ( value !== 'granted' && value !== 'denied' ) {
			return;
		}
		bucket[ category ] = bucket[ category ] || [];
		bucket[ category ].push( value );
	}

	/**
	 * Collect the Consent Mode commands in a data layer.
	 *
	 * Within one layer a later command replaces an earlier one, as it does for
	 * Google Tag Manager.
	 *
	 * @param {Array}  layer The data layer.
	 * @param {Object} found Defaults, regional flags and updates found so far.
	 */
	function readLayer( layer, found ) {
		var defaults = {};
		var updates = {};
		var i;
		var j;

		if ( ! layer || typeof layer.length !== 'number' ) {
			return;
		}

		for ( i = 0; i < layer.length; i++ ) {
			if ( ! isConsentCommand( layer[ i ] ) ) {
				continue;
			}

			var isUpdate = layer[ i ][ 1 ] === 'update';
			var values = layer[ i ][ 2 ];

			for ( j = 0; j < REQUIRED.length; j++ ) {
				var category = REQUIRED[ j ];

				if ( values[ category ] === undefined ) {
					continue;
				}

				found.signal = true;

				if ( isUpdate ) {
					updates[ category ] = values[ category ];
				} else if ( values.region !== undefined ) {
					found.regional[ category ] = true;
				} else {
					defaults[ category ] = values[ category ];
				}
			}
		}

		for ( j = 0; j < REQUIRED.length; j++ ) {
			note( found.defaults, REQUIRED[ j ], defaults[ REQUIRED[ j ] ] );
			note(
				found.layerDefaults,
				REQUIRED[ j ],
				defaults[ REQUIRED[ j ] ]
			);
			note( found.updates, REQUIRED[ j ], updates[ REQUIRED[ j ] ] );
		}
	}

	/**
	 * Collect the consent state Google Tag Manager holds.
	 *
	 * GTM keeps it per category with `default` and `update` as booleans, true
	 * for granted. Its default already accounts for the visitor's region, and
	 * it is what GTM's own tags go by. A default GTM marks `implicit` was not
	 * set by the site, so it does not count as consent state.
	 *
	 * @param {Object} found Defaults, regional flags and updates found so far.
	 */
	function readTagManager( found ) {
		var entries;
		var j;

		try {
			entries =
				window.google_tag_data &&
				window.google_tag_data.ics &&
				window.google_tag_data.ics.entries;
		} catch ( e ) {
			return;
		}

		if ( ! entries || typeof entries !== 'object' ) {
			return;
		}

		for ( j = 0; j < REQUIRED.length; j++ ) {
			var category = REQUIRED[ j ];
			var entry = entries[ category ];

			if ( ! entry || typeof entry !== 'object' ) {
				continue;
			}
			if ( typeof entry.update === 'boolean' ) {
				found.signal = true;
				note( found.updates, category, entry.update ? 'granted' : 'denied' );
			}
			if ( typeof entry.default === 'boolean' && entry.implicit !== true ) {
				found.signal = true;
				note(
					found.resolved,
					category,
					entry.default ? 'granted' : 'denied'
				);
			}
		}
	}

	/**
	 * Whether Google Tag Manager holds a consent state the site set for a
	 * category: an answer, or a default it does not mark as its own.
	 *
	 * Read as defensively as `readTagManager()`: this is not a published
	 * interface, and anything unexpected counts as no state.
	 *
	 * @param {string} category The category.
	 * @return {boolean} Whether a consent state the site set exists.
	 */
	function tagManagerHasState( category ) {
		var entry;

		try {
			entry =
				window.google_tag_data &&
				window.google_tag_data.ics &&
				window.google_tag_data.ics.entries &&
				window.google_tag_data.ics.entries[ category ];
		} catch ( e ) {
			return false;
		}

		if ( ! entry || typeof entry !== 'object' ) {
			return false;
		}

		return (
			typeof entry.update === 'boolean' ||
			( typeof entry.default === 'boolean' && entry.implicit !== true )
		);
	}

	/**
	 * Collect the consent state reported through GTM Kit's consent bridge.
	 *
	 * The bridge holds, per category, `granted` or `denied` and whether it was
	 * reported as the visitor's answer (`change`) or as the default Google Tag
	 * Manager resolved for this visitor (`initial`), which already accounts
	 * for the region. Anything not shaped like that counts as no consent
	 * state.
	 *
	 * A grant reported as initial is a resolved default only when Google Tag
	 * Manager holds a consent state the site set for that category. The
	 * template reads "no state" as granted, so without that confirmation the
	 * grant may be nothing more than a consent platform that has not set its
	 * default yet, or a site with no consent state at all. It then goes to the
	 * pending grants, which are not a consent state of their own and count
	 * only once the wait is over.
	 *
	 * @param {Object} found Defaults, regional flags and updates found so far.
	 */
	function readBridge( found ) {
		var bridge;
		var j;

		try {
			bridge = window.gtmkit && window.gtmkit.consentBridge;
		} catch ( e ) {
			return;
		}

		if (
			! bridge ||
			typeof bridge !== 'object' ||
			! bridge.state ||
			typeof bridge.state !== 'object' ||
			! bridge.basis ||
			typeof bridge.basis !== 'object'
		) {
			return;
		}

		for ( j = 0; j < REQUIRED.length; j++ ) {
			var category = REQUIRED[ j ];
			var value = bridge.state[ category ];
			var basis = bridge.basis[ category ];

			if ( value !== 'granted' && value !== 'denied' ) {
				continue;
			}
			if ( basis === 'change' ) {
				found.signal = true;
				note( found.updates, category, value );
			} else if ( basis === 'initial' ) {
				if ( value === 'granted' && ! tagManagerHasState( category ) ) {
					note( found.pendingGrants, category, value );
				} else {
					found.signal = true;
					note( found.resolved, category, value );
				}
			}
		}
	}

	/**
	 * Collect a denial in GTM Kit's own consent state.
	 *
	 * Only a denial counts: the state starts from GTM Kit's defaults without
	 * their region, so a grant there may not apply to this visitor.
	 *
	 * @param {Object} found Defaults, regional flags and updates found so far.
	 */
	function readGtmKitState( found ) {
		var consent = window.gtmkit && window.gtmkit.consent;
		var state = consent && consent.state;
		var j;

		if ( ! state || typeof state !== 'object' ) {
			return;
		}

		for ( j = 0; j < REQUIRED.length; j++ ) {
			if ( state[ REQUIRED[ j ] ] === 'denied' ) {
				found.signal = true;
				note( found.defaults, REQUIRED[ j ], 'denied' );
			}
		}
	}

	/**
	 * The consent the page holds for the required categories.
	 *
	 * For each category an answer decides; without one, the default Google Tag
	 * Manager resolved for the visitor, as the bridge reports it or GTM holds
	 * it; without that, a default in a data layer, unless one is scoped to
	 * regions, which cannot be read here because the visitor's region is not
	 * known in the page. A denial in any place beats a grant.
	 *
	 * A grant the bridge reported as initial while Google Tag Manager held no
	 * consent state is left out, unless `includePending` asks for it, which
	 * only the decision at the end of the wait does. Such a grant is never a
	 * consent state on its own, and a denying default not scoped to regions
	 * beats it.
	 *
	 * @param {string}  layerName      The data layer the event goes to.
	 * @param {Object}  announced      Answers announced with the GTM Kit consent event.
	 * @param {boolean} includePending Whether pending bridge grants fill in categories nothing resolved.
	 * @return {{granted: boolean, denied: boolean, signal: boolean}} Whether
	 *     both are granted, whether the visitor has answered with at least one
	 *     denied, and whether any consent state exists for either category.
	 */
	function readConsent( layerName, announced, includePending ) {
		var found = {
			defaults: {},
			layerDefaults: {},
			resolved: {},
			regional: {},
			updates: {},
			pendingGrants: {},
			signal: false,
		};
		var j;

		readLayer( window[ layerName ], found );
		if ( layerName !== 'dataLayer' ) {
			readLayer( window.dataLayer, found );
		}
		readBridge( found );
		readTagManager( found );
		readGtmKitState( found );

		for ( j = 0; j < REQUIRED.length; j++ ) {
			var answer = announced[ REQUIRED[ j ] ];
			if ( answer === 'granted' || answer === 'denied' ) {
				found.signal = true;
				note( found.updates, REQUIRED[ j ], answer );
			}
		}

		var granted = true;
		var denied = false;

		for ( j = 0; j < REQUIRED.length; j++ ) {
			var name = REQUIRED[ j ];
			var values = found.updates[ name ];
			var answered = !! values;

			if ( ! answered ) {
				values = found.resolved[ name ];
			}
			if ( ! answered && ! values && ! found.regional[ name ] ) {
				values = found.defaults[ name ];
			}
			// A pending grant fills in only where nothing resolved the
			// category, and a denying default not scoped to regions still
			// beats it. Where a default scoped to regions keeps the defaults
			// from deciding on their own, only a data layer default counts
			// against it: GTM Kit's own state carries its default without the
			// region, so for a visitor outside that region it is no default.
			if (
				! answered &&
				includePending &&
				! found.resolved[ name ] &&
				found.pendingGrants[ name ]
			) {
				values = (
					( found.regional[ name ]
						? found.layerDefaults[ name ]
						: found.defaults[ name ] ) || []
				).concat( found.pendingGrants[ name ] );
			}

			// Assigned on every pass: `var` is not reset per iteration, so a
			// category without a value would otherwise inherit the previous one.
			var value =
				values &&
				( values.indexOf( 'denied' ) === -1 ? 'granted' : 'denied' );
			if ( value !== 'granted' ) {
				granted = false;
			}
			if ( answered && value === 'denied' ) {
				denied = true;
			}
		}

		return { granted: granted, denied: denied, signal: found.signal };
	}

	/**
	 * Copy `source` into `target`, merging nested objects.
	 *
	 * @param {Object} target The object to write into.
	 * @param {Object} source The object to copy from.
	 * @return {Object} The target.
	 */
	function merge( target, source ) {
		for ( var key in source ) {
			if ( ! Object.prototype.hasOwnProperty.call( source, key ) ) {
				continue;
			}
			var value = source[ key ];
			if (
				value &&
				typeof value === 'object' &&
				! Array.isArray( value ) &&
				target[ key ] &&
				typeof target[ key ] === 'object'
			) {
				merge( target[ key ], value );
			} else {
				target[ key ] = value;
			}
		}
		return target;
	}

	/**
	 * Push the event now if consent allows it, or once the answer arrives.
	 *
	 * @param {Object} event   The event, without the held details.
	 * @param {string} name    The data layer name.
	 * @param {Object} held    The details that need consent, shaped like the event.
	 * @param {Object} options Site-wide settings. `unknownConsent` is `deny`
	 *                         when unknown consent withholds the details.
	 */
	window.gtmkit.events.pushWhenConsented = function (
		event,
		name,
		held,
		options
	) {
		var layerName = typeof name === 'string' && name ? name : 'dataLayer';
		var unknownAllows = ! (
			options &&
			typeof options === 'object' &&
			options.unknownConsent === 'deny'
		);
		var done = false;
		var deadline = new Date().getTime() + WAIT_MS;
		var hold = null;
		var announced = {};
		var timer;

		window[ layerName ] = window[ layerName ] || [];

		function finish( withDetails ) {
			if ( done ) {
				return;
			}
			done = true;
			window.clearInterval( timer );
			window.removeEventListener( 'pagehide', giveUp );
			window.removeEventListener( 'gtmkit:consent:updated', onAnnounced );
			window.removeEventListener( 'gtmkit:consent:bridged', check );
			window.document.removeEventListener(
				'visibilitychange',
				onVisibilityChange
			);

			if ( hold ) {
				hold.pending = false;
			}

			if ( withDetails && held && typeof held === 'object' ) {
				merge( event, held );
			}

			if ( typeof window.gtmkit.events.push === 'function' ) {
				window.gtmkit.events.push( event, layerName );
			} else {
				window[ layerName ].push( event );
			}
		}

		function check() {
			var consent = readConsent( layerName, announced );

			if ( consent.granted ) {
				finish( true );
			} else if ( consent.denied ) {
				finish( false );
			}
		}

		// When the wait ends without an answer, the details go where no
		// consent state exists and the site treats unknown consent as
		// allowed, and there a grant the bridge reported before any consent
		// state existed also counts where it stands unopposed. Such a grant
		// cannot be told apart from no consent state, so a site that withholds
		// the details on unknown consent ignores it. An answer that arrived
		// since the last check still counts.
		function giveUp() {
			check();

			if ( done ) {
				return;
			}

			var consent = readConsent( layerName, announced, unknownAllows );

			finish( consent.granted || ( ! consent.signal && unknownAllows ) );
		}

		// The event can carry the answer itself, from a consent platform that
		// announces it without going through `window.gtmkit.consent.update()`.
		function onAnnounced( e ) {
			var detail = e && e.detail;
			var j;

			if ( detail && typeof detail === 'object' ) {
				for ( j = 0; j < REQUIRED.length; j++ ) {
					if ( detail[ REQUIRED[ j ] ] !== undefined ) {
						announced[ REQUIRED[ j ] ] = detail[ REQUIRED[ j ] ];
					}
				}
			}
			check();
		}

		function onVisibilityChange() {
			if ( window.document.visibilityState === 'hidden' ) {
				giveUp();
			}
		}

		check();

		if ( done ) {
			return;
		}

		hold = { pending: true, until: deadline };
		window.gtmkit.events.consentHold = hold;

		window.addEventListener( 'pagehide', giveUp );
		window.addEventListener( 'gtmkit:consent:updated', onAnnounced );
		window.addEventListener( 'gtmkit:consent:bridged', check );
		window.document.addEventListener(
			'visibilitychange',
			onVisibilityChange
		);

		// The wait is measured on the clock, not in timer ticks: browsers slow
		// timers down in background tabs, and a count of ticks would stretch
		// the wait with them.
		timer = window.setInterval( function () {
			check();
			if ( new Date().getTime() >= deadline ) {
				giveUp();
			}
		}, POLL_MS );
	};
} )( window );
