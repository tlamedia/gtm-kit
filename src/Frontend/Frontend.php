<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

/**
 * Frontend
 */
final class Frontend {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Datalayer name.
	 *
	 * @var string
	 */
	protected $datalayer_name;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options        = $options;
		$this->datalayer_name = ( $this->options->get( 'general', 'datalayer_name' ) ) ? $this->options->get( 'general', 'datalayer_name' ) : 'dataLayer';
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function register( Options $options ): void {
		$page                    = new Frontend( $options );
		$container_active        = ( $options->get( 'general', 'container_active' ) && apply_filters( 'gtmkit_container_active', true ) );
		$noscript_implementation = $options->get( 'general', 'noscript_implementation' );

		if ( empty( $options->get( 'general', 'just_the_container' ) ) ) {
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_settings_and_data_script' ], 5, 0 );
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_datalayer_content' ] );
		}

		if ( $container_active && $page->is_user_allowed() ) {
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_header_script' ] );
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

		if ( $options->get( 'general', 'event_inspector' ) ) {
			add_action( 'wp_footer', [ $page, 'get_event_inspector' ] );
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

		ob_start();
		?>
		window.gtmkit_settings = <?php echo wp_json_encode( $settings, JSON_FORCE_OBJECT ); ?>;
		window.gtmkit_data = <?php echo wp_json_encode( apply_filters( 'gtmkit_header_script_data', [] ), JSON_FORCE_OBJECT ); ?>;
		window.<?php echo esc_js( $this->datalayer_name ); ?> = window.<?php echo esc_js( $this->datalayer_name ); ?> || [];
		<?php if ( $this->options->get( 'general', 'gcm_default_settings' ) ) : ?>
		if (typeof gtag === "undefined") {
			function gtag(){<?php echo esc_attr( $this->datalayer_name ); ?>.push(arguments);}
			gtag('consent', 'default', {
				'ad_personalization': '<?php echo ( $this->options->get( 'general', 'gcm_ad_personalization' ) ) ? 'granted' : 'denied'; ?>',
				'ad_storage': '<?php echo ( $this->options->get( 'general', 'gcm_ad_storage' ) ) ? 'granted' : 'denied'; ?>',
				'ad_user_data': '<?php echo ( $this->options->get( 'general', 'gcm_ad_user_data' ) ) ? 'granted' : 'denied'; ?>',
				'analytics_storage': '<?php echo ( $this->options->get( 'general', 'gcm_analytics_storage' ) ) ? 'granted' : 'denied'; ?>',
				'personalization_storage': '<?php echo ( $this->options->get( 'general', 'gcm_personalization_storage' ) ) ? 'granted' : 'denied'; ?>',
				'functionality_storage': '<?php echo ( $this->options->get( 'general', 'gcm_functionality_storage' ) ) ? 'granted' : 'denied'; ?>',
				'security_storage': '<?php echo ( $this->options->get( 'general', 'gcm_security_storage' ) ) ? 'granted' : 'denied'; ?>',
				<?php if ( $this->options->get( 'general', 'gcm_wait_for_update' ) ) : ?>
				'wait_for_update':  <?php echo esc_html( (string) ( (int) $this->options->get( 'general', 'gcm_wait_for_update' ) ) ); ?>
				<?php endif; ?>
			});
			<?php echo ( $this->options->get( 'general', 'gcm_ads_data_redaction' ) ) ? 'gtag("set", "ads_data_redaction", true);' : ''; ?>
			<?php echo ( $this->options->get( 'general', 'gcm_url_passthrough' ) ) ? 'gtag("set", "url_passthrough", true);' : ''; ?>
		} else if ( window.gtmkit_settings.console_log === 'on' ) {
			console.warn('GTM Kit: gtag is already defined')
		}<?php endif; ?>
		<?php
		$script = ob_get_clean();

		wp_register_script( 'gtmkit', '', [], GTMKIT_VERSION, [ 'in_footer' => false ] );
		wp_enqueue_script( 'gtmkit' );
		wp_add_inline_script( 'gtmkit', $script, 'before' );

		if ( $this->options->get( 'general', 'event_inspector' ) ) {

			$deps_file  = GTMKIT_PATH . 'assets/frontend/event-inspector.asset.php';
			$dependency = [];
			$version    = false;

			if ( \file_exists( $deps_file ) ) {
				$deps_file  = require $deps_file;
				$dependency = $deps_file['dependencies'];
				$version    = $deps_file['version'];
			}
			$dependency[] = 'gtmkit';

			\wp_enqueue_style( 'gtmkit-event-inspector-style', GTMKIT_URL . 'assets/frontend/event-inspector.css', [], $version );
			\wp_enqueue_script( 'gtmkit-event-inspector-script', GTMKIT_URL . 'assets/frontend/event-inspector.js', $dependency, $version, [ 'strategy' => 'defer' ] );
		}
	}

	/**
	 * The dataLayer content included before the GTM container script
	 */
	public function enqueue_datalayer_content(): void {

		$datalayer_data = apply_filters( 'gtmkit_datalayer_content', [] );

		$script  = 'const gtmkit_dataLayer_content = ' . wp_json_encode( $datalayer_data ) . ";\n";
		$script .= esc_attr( $this->datalayer_name ) . '.push( gtmkit_dataLayer_content );' . "\n";

		$dependency = ( $this->options->get( 'general', 'container_active' ) && apply_filters( 'gtmkit_container_active', true ) )
			? [ 'gtmkit-container' ]
			: [ 'gtmkit' ];

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
	}

	/**
	 * This script fires the 'delay_js' event in Google Tag Manager
	 */
	public function enqueue_delay_js_script(): void {

		$script = esc_attr( $this->datalayer_name ) . '.push({"event" : "load_delayed_js"});' . "\n";

		wp_register_script( 'gtmkit-delay', '', [ 'gtmkit-container' ], GTMKIT_VERSION, [ 'in_footer' => true ] );
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
		$loader = $this->options->get( 'general', 'sgtm_container_identifier' ) ? $this->options->get( 'general', 'sgtm_container_identifier' ) : 'gtm';

		if ( $this->options->get( 'general', 'sgtm_cookie_keeper' ) ) {
			$gtm_id = preg_replace( '/^GTM\-/i', '', $gtm_id );
			echo "/* Google Tag Manager with Stape.io Cookie Keeper */\n";
			echo '!function(){"use strict";function l(e){for(var t=e,r=0,n=document.cookie.split(";");r<n.length;r++){var o=n[r].split("=");if(o[0].trim()===t)return o[1]}}function s(e){return localStorage.getItem(e)}function u(e){return window[e]}function d(e,t){e=document.querySelector(e);return t?null==e?void 0:e.getAttribute(t):null==e?void 0:e.textContent}var e=window,t=document,r="script",n="' . esc_js( $this->datalayer_name ) . '",o="' . esc_js( $gtm_id ) . '",a="https://' . esc_attr( $domain ) . '",i="",c="' . esc_attr( $loader ) . '",E="cookie",I="_sbp",v="",g=!1;try{var g=!!E&&(m=navigator.userAgent,!!(m=new RegExp("Version/([0-9._]+)(.*Mobile)?.*Safari.*").exec(m)))&&16.4<=parseFloat(m[1]),A="stapeUserId"===E,f=g&&!A?function(e,t,r){void 0===t&&(t="");var n={cookie:l,localStorage:s,jsVariable:u,cssSelector:d},t=Array.isArray(t)?t:[t];if(e&&n[e])for(var o=n[e],a=0,i=t;a<i.length;a++){var c=i[a],c=r?o(c,r):o(c);if(c)return c}else console.warn("invalid uid source",e)}(E,I,v):void 0;g=g&&(!!f||A)}catch(e){console.error(e)}var m=e,E=(m[n]=m[n]||[],m[n].push({"gtm.start":(new Date).getTime(),event:"gtm.js"}),t.getElementsByTagName(r)[0]),I="dataLayer"===n?"":"&l="+n,v=f?"&bi="+encodeURIComponent(f):"",A=t.createElement(r),e=g?"kp"+c:c,n=!g&&i?i:a;A.async=!0,A.src=n+"/"+e+".js?st="+o+I+v' . ( ( ! empty( Options::init()->get( 'general', 'gtm_auth' ) ) && ! empty( Options::init()->get( 'general', 'gtm_preview' ) ) ) ? "+'&gtm_auth=" . esc_attr( Options::init()->get( 'general', 'gtm_auth' ) ) . '&gtm_preview=' . esc_attr( Options::init()->get( 'general', 'gtm_preview' ) ) . "&gtm_cookies_win=x'" : '' ) . ',null!=(f=E.parentNode)&&f.insertBefore(A,E)}();';
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
	 * @param array  $attributes The script attributes.
	 * @param string $script The script.
	 *
	 * @return array The script attributes.
	 */
	public function set_inline_script_attributes( array $attributes, string $script ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( isset( $attributes['id'] ) && strpos( $attributes['id'], 'gtmkit-' ) === 0 ) {

			if ( strpos( $attributes['id'], 'gtmkit-delay' ) === 0 ) {
				return $attributes;
			}

			$script_attributes = apply_filters(
				'gtmkit_header_script_attributes',
				[
					'data-cfasync'       => 'false',
					'data-nowprocket'    => '',
					'data-cookieconsent' => 'ignore',
				]
			);

			foreach ( $script_attributes as $attribute_name => $value ) {
				$attributes[ $attribute_name ] = $value;
			}
		}

		return $attributes;
	}

	/**
	 * The Google Tag Manager noscript
	 */
	public static function get_body_script(): void {
		$domain = Options::init()->get( 'general', 'sgtm_domain' ) ? Options::init()->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';
		$gtm_id = Options::init()->get( 'general', 'gtm_id' );

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
	 * @param array $pattern The exclude list.
	 *
	 * @return array
	 */
	public function wp_rocket_exclude_javascript( array $pattern ): array {
		$pattern[] = 'dataLayer';
		$pattern[] = 'gtmkit';

		return $pattern;
	}

	/**
	 * Adds Google Tag Manager domain DNS Prefetch printed by wp_resource_hints
	 *
	 * @param array  $hints URLs to print for resource hints.
	 * @param string $relation_type The relation type the URL are printed for.
	 *
	 * @return array URL to print
	 */
	public function dns_prefetch( array $hints, string $relation_type ): array {

		$domain = $this->options->get( 'general', 'sgtm_domain' ) ? $this->options->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';

		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = '//' . $domain;
		}

		return $hints;
	}

	/**
	 * Get the Event Inspector HTML
	 */
	public function get_event_inspector(): void {
		?>
		<div id="gtmkit-event-inspector">
			<div id="gtmkit-event-inspector-wrapper">
				<div id="gtmkit-event-inspector-title">GTM Kit Event Inspector:</div>
				<ul id="gtmkit-event-inspector-list"></ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Is user allowed
	 *
	 * @return bool
	 */
	public function is_user_allowed(): bool {

		$is_user_allowed     = true;
		$excluded_user_roles = $this->options->get( 'general', 'exclude_user_roles' );

		if ( ! empty( $excluded_user_roles ) ) {
			foreach ( wp_get_current_user()->roles as $role ) {
				if ( in_array( $role, $excluded_user_roles, true ) ) {
					$is_user_allowed = false;
					break;
				}
			}
		}

		return $is_user_allowed;
	}
}
