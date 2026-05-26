<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * Frontend
 */
final class Frontend {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Datalayer name.
	 *
	 * @var string
	 */
	protected string $datalayer_name;

	/**
	 * Consent signal source registry.
	 *
	 * @var ConsentSignalSourceRegistry
	 */
	protected ConsentSignalSourceRegistry $signal_source_registry;

	/**
	 * Event deferral gate.
	 *
	 * @var EventDeferralGate
	 */
	protected EventDeferralGate $event_deferral_gate;

	/**
	 * Constructor.
	 *
	 * The registry and deferral gate default to fresh instances when
	 * not supplied so existing call sites (`new Frontend( $options )`)
	 * keep working; the bootstrap in `inc/main.php` wires explicit
	 * services for the production request lifecycle.
	 *
	 * @param Options                          $options An instance of Options.
	 * @param ConsentSignalSourceRegistry|null $signal_source_registry Optional pre-built registry.
	 * @param EventDeferralGate|null           $event_deferral_gate    Optional pre-built deferral gate.
	 */
	public function __construct(
		Options $options,
		?ConsentSignalSourceRegistry $signal_source_registry = null,
		?EventDeferralGate $event_deferral_gate = null
	) {
		$this->options                = $options;
		$this->datalayer_name         = ( $this->options->get( 'general', 'datalayer_name' ) ) ? $this->options->get( 'general', 'datalayer_name' ) : 'dataLayer';
		$this->signal_source_registry = $signal_source_registry ?? new ConsentSignalSourceRegistry( $options );
		$this->event_deferral_gate    = $event_deferral_gate ?? new EventDeferralGate( $this->signal_source_registry );
	}

	/**
	 * Resolve the per-request output gate.
	 *
	 * Computes the URL-exclusion state and the resulting container-active
	 * value, applying the `gtmkit_container_active` filter exactly once so
	 * the same decision can gate both the core runtime here and the
	 * integration enqueues in `gtmkit_frontend_init()`.
	 *
	 * @param Options $options An instance of Options.
	 * @return array{url_excluded: bool, container_active: bool}
	 */
	public static function resolve_output_gate( Options $options ): array {
		$url_excluded     = UrlExclusion::is_excluded(
			UrlExclusion::current_request_path(),
			$options->get( 'general', 'excluded_url_patterns' )
		);
		$base_active      = ( $options->get( 'general', 'container_active' ) && ! $url_excluded );
		$container_active = (bool) apply_filters( 'gtmkit_container_active', $base_active );

		return [
			'url_excluded'     => $url_excluded,
			'container_active' => $container_active,
		];
	}

	/**
	 * Whether all GTM Kit frontend output is withheld for this request.
	 *
	 * True only when the URL matches an exclusion pattern and no
	 * `gtmkit_container_active` filter forced the container back on. When
	 * true, the core runtime and every integration enqueue must be skipped
	 * so dependent scripts never reference a runtime that was never loaded.
	 *
	 * @param array{url_excluded: bool, container_active: bool} $gate Resolved output gate.
	 * @return bool
	 */
	public static function is_output_suppressed( array $gate ): bool {
		return ( $gate['url_excluded'] && ! $gate['container_active'] );
	}

	/**
	 * Register frontend
	 *
	 * @param Options                                                $options An instance of Options.
	 * @param array{url_excluded: bool, container_active: bool}|null $gate    Pre-resolved output gate; resolved here when null.
	 */
	public static function register( Options $options, ?array $gate = null ): void {
		$page                    = new Frontend( $options );
		$gate                    = $gate ?? self::resolve_output_gate( $options );
		$container_active        = $gate['container_active'];
		$noscript_implementation = $options->get( 'general', 'noscript_implementation' );

		// On an excluded URL with no filter override, withhold every enqueue
		// path together: head loader, noscript iframe, the dependent
		// settings/data + dataLayer scripts, the delay-js push, the
		// resource-hint DNS prefetch, and the cache-plugin attribute
		// filters. Integration enqueues are withheld in tandem from
		// gtmkit_frontend_init() using the same gate. The decision is
		// per-URL and so safe to bake into the cached response.
		if ( self::is_output_suppressed( $gate ) ) {
			return;
		}

		if ( empty( $options->get( 'general', 'just_the_container' ) ) ) {
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_settings_and_data_script' ], 5, 0 );
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_datalayer_content' ] );
		}

		if ( $container_active && $page->is_user_allowed() ) {
			// Priority 6 so 'gtmkit-container' is registered before any dependent script (WP 6.9.1 validates deps at enqueue time).
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_header_script' ], 6 );
		} elseif ( $options->get( 'general', 'console_log' ) ) {
			add_action( 'wp_head', [ $page, 'container_disabled' ] );
		}

		if ( $options->get( 'general', 'load_js_event' ) ) {
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_delay_js_script' ] );
		}

		if ( $noscript_implementation === '0' && $container_active ) {
			add_action( 'wp_body_open', [ $page, 'get_body_script' ] );
		} elseif ( $noscript_implementation === '1' && $container_active ) {
			add_action( 'body_footer', [ $page, 'get_body_script' ] );
		}

		add_filter( 'wp_resource_hints', [ $page, 'dns_prefetch' ], 10, 2 );
		add_filter( 'rocket_excluded_inline_js_content', [ $page, 'wp_rocket_exclude_javascript' ] );
		add_filter( 'wp_inline_script_attributes', [ $page, 'set_inline_script_attributes' ], 10, 2 );
	}

	/**
	 * The inline script for settings and data use by other GTM Kit scripts.
	 */
	public function enqueue_settings_and_data_script(): void {
		$settings = wp_cache_get( 'gtmkit_script_settings', 'gtmkit' );
		if ( ! $settings ) {

			$settings = apply_filters(
				'gtmkit_header_script_settings',
				[
					'datalayer_name' => $this->datalayer_name,
					'console_log'    => $this->options->get( 'general', 'console_log' ),
				]
			);

			wp_cache_set( 'gtmkit_script_settings', $settings, 'gtmkit' );
		}

		/**
		 * Region codes the consent defaults apply to.
		 *
		 * @param array<int, string> $consent_region Zero or more ISO region codes (e.g. `DK`, `DE-BY`, `US-CA`).
		 */
		$consent_region = apply_filters(
			'gtmkit_consent_region',
			$this->options->get( 'general', 'gcm_region' )
		);
		if ( ! is_array( $consent_region ) ) {
			$consent_region = [];
		}

		/**
		 * Whether GTM Kit should emit the consent default block at all.
		 *
		 * When false, GTM Kit stays silent so a CMP or GTM-based consent
		 * implementation can own the flow without double-firing.
		 *
		 * @param bool $consent_enabled Master toggle state after filter.
		 */
		$consent_enabled = (bool) apply_filters(
			'gtmkit_consent_default_settings_enabled',
			(bool) $this->options->get( 'general', 'gcm_default_settings' )
		);

		/**
		 * Per-category Consent Mode v2 default state.
		 *
		 * Resolved through the signal source registry so Premium add-ons
		 * (WP Consent API integration, named CMP integrations) can plug
		 * in alternative state providers via the
		 * {@see 'gtmkit_consent_signal_sources'} filter. The default
		 * `gtmkit_default` source delegates to the legacy
		 * {@see 'gtmkit_consent_default_state'} filter, so existing
		 * integrations keep working unchanged.
		 *
		 * The registry is only consulted when the master toggle is on.
		 * When off, the consent block is suppressed entirely so a CMP
		 * or GTM-based consent solution can own the flow without
		 * double-firing.
		 *
		 * @var array<string, string> $consent_defaults
		 */
		$consent_defaults = [
			'ad_personalization'      => 'denied',
			'ad_storage'              => 'denied',
			'ad_user_data'            => 'denied',
			'analytics_storage'       => 'denied',
			'personalization_storage' => 'denied',
			'functionality_storage'   => 'denied',
			'security_storage'        => 'denied',
		];
		if ( $consent_enabled ) {
			$resolved_state = $this->signal_source_registry->read_state();
			if ( null !== $resolved_state ) {
				$consent_defaults = $resolved_state;
			}
		}

		$wait_for_update    = (int) $this->options->get( 'general', 'gcm_wait_for_update' );
		$ads_data_redaction = (bool) $this->options->get( 'general', 'gcm_ads_data_redaction' );
		$url_passthrough    = (bool) $this->options->get( 'general', 'gcm_url_passthrough' );

		// Build the inner body of gtag('consent', 'default', { ... }) so the
		// conditional wait_for_update and region fields don't leak template
		// whitespace into the rendered script. Also build a categories-only
		// body for the window.gtmkit.consent.state surface, which holds the
		// seven Consent Mode v2 categories and nothing else.
		$category_lines = [];
		foreach (
			[
				'ad_personalization',
				'ad_storage',
				'ad_user_data',
				'analytics_storage',
				'personalization_storage',
				'functionality_storage',
				'security_storage',
			] as $category
		) {
			$category_lines[] = sprintf(
				"'%s': '%s'",
				$category,
				esc_js( (string) ( $consent_defaults[ $category ] ?? 'denied' ) )
			);
		}
		$consent_state_body = implode( ",\n\t\t\t\t", $category_lines );

		$consent_lines = $category_lines;
		if ( $wait_for_update > 0 ) {
			$consent_lines[] = "'wait_for_update': " . $wait_for_update;
		}
		if ( ! empty( $consent_region ) ) {
			$consent_lines[] = "'region': " . (string) wp_json_encode( array_values( $consent_region ) );
		}
		$consent_body = implode( ",\n\t\t\t\t", $consent_lines );

		ob_start();
		?>
		window.gtmkit_settings = <?php echo wp_json_encode( $settings, JSON_FORCE_OBJECT ); ?>;
		window.gtmkit_data = <?php echo wp_json_encode( apply_filters( 'gtmkit_header_script_data', [] ), JSON_FORCE_OBJECT ); ?>;
		window.<?php echo esc_js( $this->datalayer_name ); ?> = window.<?php echo esc_js( $this->datalayer_name ); ?> || [];
		window.gtmkit = window.gtmkit || {};
		<?php if ( $consent_enabled ) : ?>
		if (typeof gtag === "undefined") {
			function gtag(){<?php echo esc_attr( $this->datalayer_name ); ?>.push(arguments);}
			gtag('consent', 'default', {
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $consent_body is composed entirely of hardcoded category keys, esc_js()-escaped 'granted'/'denied' literals, an integer cast, and JSON of ISO region codes already sanitized by OptionSchema::sanitize_region_codes(). Re-escaping would corrupt the JSON and the double quotes it contains.
				echo $consent_body;
				?>

			});
			<?php echo $ads_data_redaction ? 'gtag("set", "ads_data_redaction", true);' : ''; ?>
			<?php echo $url_passthrough ? 'gtag("set", "url_passthrough", true);' : ''; ?>
		} else if ( window.gtmkit_settings.console_log === 'on' ) {
			console.warn('GTM Kit: gtag is already defined')
		}
		window.gtmkit.consent = {
			state: {
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $consent_state_body is built above from hardcoded category keys and esc_js()-escaped 'granted'/'denied' literals only (no region/wait_for_update). Re-escaping would corrupt the embedded quotes.
				echo $consent_state_body;
				?>

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
		<?php endif; ?>
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
		<?php
		$script = ob_get_clean();

		wp_register_script( 'gtmkit', '', [], GTMKIT_VERSION, [ 'in_footer' => false ] );
		wp_enqueue_script( 'gtmkit' );
		wp_add_inline_script( 'gtmkit', $script, 'before' );
	}

	/**
	 * The dataLayer content included before the GTM container script
	 */
	public function enqueue_datalayer_content(): void {

		$datalayer_data = apply_filters( 'gtmkit_datalayer_content', [] );
		if ( ! is_array( $datalayer_data ) ) {
			$datalayer_data = [];
		}

		// Route through the client push helper so deferral happens in the
		// browser. Server-side suppression would freeze the decision into
		// the cached HTML and drop the event for every visitor.
		$script  = 'const gtmkit_dataLayer_content = ' . wp_json_encode( $datalayer_data ) . ";\n";
		$script .= 'window.gtmkit.events.push( gtmkit_dataLayer_content, ' . wp_json_encode( $this->datalayer_name ) . ' );' . "\n";

		// Ask the script registry whether `gtmkit-container` was actually registered earlier in this request rather than re-evaluating the gate predicate, which can disagree with the earlier evaluation if a `gtmkit_container_active` filter callback was added between `register()` and `wp_enqueue_scripts`.
		$dependency = wp_script_is( 'gtmkit-container', 'registered' ) ? [ 'gtmkit-container' ] : [ 'gtmkit' ];

		wp_register_script( 'gtmkit-datalayer', '', $dependency, GTMKIT_VERSION, [ 'in_footer' => false ] );
		wp_enqueue_script( 'gtmkit-datalayer' );
		wp_add_inline_script( 'gtmkit-datalayer', apply_filters( 'gtmkit_datalayer_script', $script ), 'before' );
	}

		/**
		 * The Google Tag Manager container script
		 */
	public function enqueue_header_script(): void {

		$gtm_id = $this->options->get( 'general', 'gtm_id' );

		if ( empty( $gtm_id ) ) {
			return;
		}

		$script_implementation = (int) $this->options->get( 'general', 'script_implementation' );

		ob_start();

		if ( $script_implementation > 0 ) {
			echo '
			window.requestIdleCallback =
			    window.requestIdleCallback ||
			    function (cb) {
			        const start = Date.now();
			        return setTimeout(function () {
			            cb({
			                didTimeout: false,
			                timeRemaining: function () {
			                    return Math.max(0, 50 - (Date.now() - start));
			                }
			            });
			        }, 1);
			    };

			requestIdleCallback(function () {';
			$this->get_gtm_script( $gtm_id );
			echo '});';
		} else {
			$this->get_gtm_script( $gtm_id );
		}

		$script = ob_get_clean();

		wp_register_script( 'gtmkit-container', '', [ 'gtmkit' ], GTMKIT_VERSION, [ 'in_footer' => false ] );
		wp_enqueue_script( 'gtmkit-container' );
		wp_add_inline_script( 'gtmkit-container', $script );

		$this->maybe_enqueue_consent_gating_shim();
	}

	/**
	 * Enqueue the consent-gating shim when the gating mode is strong_block.
	 *
	 * The shim is a small vanilla-JS file that listens for
	 * `gtmkit:consent:updated` and re-injects the masked GTM container as
	 * text/javascript once the required consent categories are granted.
	 * Loaded in <head> after gtmkit-container so the masked <script> element
	 * is in the DOM by the time the shim runs its initial check.
	 */
	private function maybe_enqueue_consent_gating_shim(): void {
		if ( $this->options->get( 'general', 'consent_gating_mode' ) !== OptionSchema::GATING_MODE_STRONG_BLOCK ) {
			return;
		}

		/**
		 * Consent categories that must be `granted` before the strong-block
		 * shim will unmask the GTM container. Default: analytics_storage and
		 * ad_storage, the two categories most GTM containers depend on.
		 *
		 * @param array<int, string> $required_categories Consent Mode v2 category names.
		 */
		$required_categories = apply_filters(
			'gtmkit_strong_block_required_categories',
			[ 'analytics_storage', 'ad_storage' ]
		);
		if ( ! is_array( $required_categories ) ) {
			$required_categories = [ 'analytics_storage', 'ad_storage' ];
		}
		$required_categories = array_values( array_filter( $required_categories, 'is_string' ) );

		wp_register_script(
			'gtmkit-consent-gating',
			GTMKIT_URL . 'assets/frontend/consent-gating.js',
			[ 'gtmkit-container' ],
			GTMKIT_VERSION,
			[ 'in_footer' => false ]
		);
		wp_localize_script(
			'gtmkit-consent-gating',
			'gtmkitConsentGating',
			[
				'requiredCategories' => $required_categories,
				// Used by the shim to scope its "GTM already booted" check
				// to our specific container id, so unrelated globals
				// (gtag.js for an Ads pixel, debug inspectors) cannot
				// short-circuit the unmask path.
				'containerId'        => (string) $this->options->get( 'general', 'gtm_id' ),
			]
		);
		wp_enqueue_script( 'gtmkit-consent-gating' );
	}

	/**
	 * This script fires the 'delay_js' event in Google Tag Manager
	 */
	public function enqueue_delay_js_script(): void {

		$payload = [ 'event' => 'load_delayed_js' ];
		if ( $this->event_deferral_gate->should_defer( 'load_delayed_js', $payload ) ) {
			return;
		}

		$script = esc_attr( $this->datalayer_name ) . '.push({"event" : "load_delayed_js"});' . "\n";

		$dependency = wp_script_is( 'gtmkit-container', 'registered' ) ? [ 'gtmkit-container' ] : [ 'gtmkit' ];

		wp_register_script( 'gtmkit-delay', '', $dependency, GTMKIT_VERSION, [ 'in_footer' => true ] );
		wp_enqueue_script( 'gtmkit-delay' );
		wp_add_inline_script( 'gtmkit-delay', $script, 'before' );
	}

	/**
	 * Get GTM script
	 *
	 * @param string $gtm_id The GTM container ID.
	 */
	public function get_gtm_script( string $gtm_id ): void {
		$domain = $this->options->get( 'general', 'sgtm_domain' ) ? $this->options->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';
		$loader = ! empty( $this->options->get( 'general', 'sgtm_container_identifier' ) ) ? $this->options->get( 'general', 'sgtm_container_identifier' ) : 'gtm';

		if ( $domain !== 'www.googletagmanager.com' && $loader !== 'gtm' && $this->options->get( 'general', 'sgtm_cookie_keeper' ) ) {
			$gtm_id = preg_replace( '/^GTM\-/i', '', $gtm_id );
			echo "/* Google Tag Manager with Stape.io Cookie Keeper */\n";
			echo '!function(){"use strict";function l(e){for(var t=e,r=0,n=document.cookie.split(";");r<n.length;r++){var o=n[r].split("=");if(o[0].trim()===t)return o[1]}}function s(e){return localStorage.getItem(e)}function u(e){return window[e]}function d(e,t){e=document.querySelector(e);return t?null==e?void 0:e.getAttribute(t):null==e?void 0:e.textContent}var e=window,t=document,r="script",n="' . esc_js( $this->datalayer_name ) . '",o="' . esc_js( $gtm_id ) . '",a="https://' . esc_attr( $domain ) . '",i="",c="' . esc_attr( $loader ) . '",E="cookie",I="_sbp",v="",g=!1;try{var g=!!E&&(m=navigator.userAgent,!!(m=new RegExp("Version/([0-9._]+)(.*Mobile)?.*Safari.*").exec(m)))&&16.4<=parseFloat(m[1]),A="stapeUserId"===E,f=g&&!A?function(e,t,r){void 0===t&&(t="");var n={cookie:l,localStorage:s,jsVariable:u,cssSelector:d},t=Array.isArray(t)?t:[t];if(e&&n[e])for(var o=n[e],a=0,i=t;a<i.length;a++){var c=i[a],c=r?o(c,r):o(c);if(c)return c}else console.warn("invalid uid source",e)}(E,I,v):void 0;g=g&&(!!f||A)}catch(e){console.error(e)}var m=e,E=(m[n]=m[n]||[],m[n].push({"gtm.start":(new Date).getTime(),event:"gtm.js"}),t.getElementsByTagName(r)[0]),I="dataLayer"===n?"":"&l="+n,v=f?"&bi="+encodeURIComponent(f):"",A=t.createElement(r),e=g?"kp"+c:c,n=!g&&i?i:a;A.async=!0,A.src=n+"/"+e+".js?st="+o+I+v' . ( ( ! empty( $this->options->get( 'general', 'gtm_auth' ) ) && ! empty( $this->options->get( 'general', 'gtm_preview' ) ) ) ? "+'&gtm_auth=" . esc_attr( $this->options->get( 'general', 'gtm_auth' ) ) . '&gtm_preview=' . esc_attr( $this->options->get( 'general', 'gtm_preview' ) ) . "&gtm_cookies_win=x'" : '' ) . ',null!=(f=E.parentNode)&&f.insertBefore(A,E)}();';
			echo "\n/* End Google Tag Manager */\n";
		} else {
			$argument = ( $loader === 'gtm' ) ? 'id' : 'st';
			echo "/* Google Tag Manager */\n";
			echo "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
			echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
			echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
			echo "'https://" . esc_attr( $domain ) . '/' . esc_attr( $loader ) . '.js?' . esc_attr( $argument ) . "='+i+dl";
			echo ( ! empty( $this->options->get( 'general', 'gtm_auth' ) ) && ! empty( $this->options->get( 'general', 'gtm_preview' ) ) ) ? "+'&gtm_auth=" . esc_attr( $this->options->get( 'general', 'gtm_auth' ) ) . '&gtm_preview=' . esc_attr( $this->options->get( 'general', 'gtm_preview' ) ) . "&gtm_cookies_win=x'" : '';
			echo ";f.parentNode.insertBefore(j,f);\n";
			echo "})(window,document,'script','" . esc_js( $this->datalayer_name ) . "','" . esc_attr( $gtm_id ) . "');\n";
			echo "/* End Google Tag Manager */\n";
		}
	}

	/**
	 * Set inline script attributes
	 *
	 * @param array<string, mixed> $attributes The script attributes.
	 * @param string               $script The script.
	 *
	 * @return array<string, mixed> The script attributes.
	 */
	public function set_inline_script_attributes( array $attributes, string $script ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( isset( $attributes['id'] ) && strpos( $attributes['id'], 'gtmkit-' ) === 0 ) {

			if ( strpos( $attributes['id'], 'gtmkit-delay' ) === 0 ) {
				return $attributes;
			}

			// Always-on cache-plugin compatibility attributes. These are
			// not consent-related and stay on regardless of CMP setup.
			$built = [
				'data-cfasync'    => 'false',
				'data-nowprocket' => '',
			];

			// CMP attributes from the cmp_script_attributes setting. The
			// setting is the source of truth for the named CMPs and the
			// custom slot; the gtmkit_header_script_attributes filter
			// below stays a third-party extension point and runs after
			// this build, so user-land filters can still override or add
			// attributes.
			$cmp = $this->options->get( 'general', 'cmp_script_attributes' );
			if ( ! is_array( $cmp ) ) {
				$cmp = [];
			}
			if ( ! empty( $cmp['cookiebot'] ) ) {
				$built['data-cookieconsent'] = 'ignore';
			}
			if ( ! empty( $cmp['iubenda'] ) ) {
				$built['data-cmp-ab'] = '1';
			}
			if ( ! empty( $cmp['cookieyes'] ) ) {
				$built['data-cookie-consent'] = 'ignore';
			}
			if ( ! empty( $cmp['custom']['name'] ) ) {
				// The sanitiser already strips disallowed characters at
				// save time. Re-strip here as a defence-in-depth guard
				// for legacy or filter-injected values that bypassed it.
				$custom_name = (string) preg_replace(
					OptionSchema::CMP_CUSTOM_NAME_PATTERN,
					'',
					(string) $cmp['custom']['name']
				);
				if ( '' !== $custom_name ) {
					$built[ $custom_name ] = isset( $cmp['custom']['value'] ) ? (string) $cmp['custom']['value'] : '';
				}
			}

			$script_attributes = apply_filters( 'gtmkit_header_script_attributes', $built );

			foreach ( $script_attributes as $attribute_name => $value ) {
				$attributes[ $attribute_name ] = $value;
			}

			// Strong-block: mask the GTM container script so the browser
			// will not execute it until the consent-gating shim re-injects
			// it as text/javascript. CMP attributes from above stay on
			// the masked script (they are inert while type=text/plain)
			// so a CMP that recognises them can also unblock.
			if (
				strpos( $attributes['id'], 'gtmkit-container' ) === 0
				&& $this->options->get( 'general', 'consent_gating_mode' ) === OptionSchema::GATING_MODE_STRONG_BLOCK
			) {
				$attributes['type']              = 'text/plain';
				$attributes['data-gtmkit-gated'] = '1';
			}
		}

		return $attributes;
	}

	/**
	 * The Google Tag Manager noscript
	 */
	public function get_body_script(): void {
		$domain = $this->options->get( 'general', 'sgtm_domain' ) ? $this->options->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';
		$gtm_id = $this->options->get( 'general', 'gtm_id' );
		if ( empty( $gtm_id ) ) {
			return;
		}

		echo '<noscript><iframe src="https://' . esc_attr( $domain ) . '/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
	}

	/**
	 * Console warning
	 */
	public function container_disabled(): void {
		echo '<script>console.warn("[GTM Kit] Google Tag Manager container is disabled.");</script>';

		if ( ! $this->is_user_allowed() ) {
			echo '<script>console.warn("[GTM Kit] The current user role is excluded from tracking.");</script>';
		}
	}

	/**
	 * Exclude GTM Kit in WP Rocket
	 *
	 * @param array<int, string> $pattern The exclude list.
	 *
	 * @return array<int, string>
	 */
	public function wp_rocket_exclude_javascript( array $pattern ): array {
		$pattern[] = 'dataLayer';
		$pattern[] = 'gtmkit';

		return $pattern;
	}

	/**
	 * Adds Google Tag Manager domain DNS Prefetch printed by wp_resource_hints
	 *
	 * @param array<int, string> $hints URLs to print for resource hints.
	 * @param string             $relation_type The relation type the URL are printed for.
	 *
	 * @return array<int, string> URL to print
	 */
	public function dns_prefetch( array $hints, string $relation_type ): array {

		$domain = $this->options->get( 'general', 'sgtm_domain' ) ? $this->options->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';

		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = '//' . $domain;
		}

		return $hints;
	}

	/**
	 * Is user allowed
	 *
	 * @return bool
	 */
	public function is_user_allowed(): bool {
		return self::is_user_allowed_for( $this->options );
	}

	/**
	 * Whether the current user's role is allowed to receive GTM Kit scripts.
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function is_user_allowed_for( Options $options ): bool {
		$excluded_user_roles = $options->get( 'general', 'exclude_user_roles' );

		if ( empty( $excluded_user_roles ) ) {
			return true;
		}

		foreach ( wp_get_current_user()->roles as $role ) {
			if ( in_array( $role, $excluded_user_roles, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the gtmkit-container script will be registered during this request.
	 *
	 * Mirrors the gates inside {@see Frontend::register()} and
	 * {@see Frontend::enqueue_header_script()} so dependent scripts only list
	 * 'gtmkit-container' when it will actually be registered.
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function will_register_container( Options $options ): bool {
		$url_excluded = UrlExclusion::is_excluded(
			UrlExclusion::current_request_path(),
			$options->get( 'general', 'excluded_url_patterns' )
		);

		$base_active      = $options->get( 'general', 'container_active' ) && ! $url_excluded;
		$container_active = (bool) apply_filters( 'gtmkit_container_active', $base_active );

		if ( ! $container_active ) {
			return false;
		}

		if ( empty( $options->get( 'general', 'gtm_id' ) ) ) {
			return false;
		}

		return self::is_user_allowed_for( $options );
	}
}
