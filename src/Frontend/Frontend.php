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
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_settings_and_data_script' ], 1, 0 );
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_datalayer_content' ] );
		}

		if ( $container_active ) {
			add_action( 'wp_enqueue_scripts', [ $page, 'enqueue_header_script' ] );
		} elseif ( $options->get( 'general', 'console_log' ) ) {
			add_action( 'wp_head', [ $page, 'container_disabled' ] );
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
		$settings = [
			'datalayer_name' => $this->datalayer_name,
			'console_log'    => $this->options->get( 'general', 'console_log' ),
		];

		ob_start();
		?>
		window.gtmkit_settings = <?php echo wp_json_encode( apply_filters( 'gtmkit_header_script_settings', $settings ), JSON_FORCE_OBJECT ); ?>;
		window.gtmkit_data = <?php echo wp_json_encode( apply_filters( 'gtmkit_header_script_data', [] ), JSON_FORCE_OBJECT ); ?>;
		<?php if ( $this->options->get( 'general', 'gcm_default_settings' ) ) : ?>
		if (typeof gtag === "undefined") {
			function gtag(){<?php echo esc_attr( $this->datalayer_name ); ?>.push(arguments);}
			gtag('consent', 'default', {
				'ad_storage': '<?php echo ( $this->options->get( 'general', 'gcm_ad_storage' ) ) ? 'granted' : 'denied'; ?>',
				'analytics_storage': '<?php echo ( $this->options->get( 'general', 'gcm_analytics_storage' ) ) ? 'granted' : 'denied'; ?>',
				'personalization_storage': '<?php echo ( $this->options->get( 'general', 'gcm_personalization_storage' ) ) ? 'granted' : 'denied'; ?>',
				'functionality_storage': '<?php echo ( $this->options->get( 'general', 'gcm_functionality_storage' ) ) ? 'granted' : 'denied'; ?>',
				'security_storage': '<?php echo ( $this->options->get( 'general', 'gcm_security_storage' ) ) ? 'granted' : 'denied'; ?>',
			});
		} else if ( window.gtmkit_settings.console_log === 'on' ) {
			console.warn('GTM Kit: gtag is already defined')
		}<?php endif; ?>
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

		$script  = 'window.' . esc_js( $this->datalayer_name ) . ' = window.' . esc_js( $this->datalayer_name ) . ' || [];' . "\n";
		$script .= 'const gtmkit_dataLayer_content = ' . wp_json_encode( $datalayer_data ) . ";\n";
		$script .= esc_attr( $this->datalayer_name ) . '.push( gtmkit_dataLayer_content );' . "\n";

		wp_register_script( 'gtmkit-datalayer', '', [], GTMKIT_VERSION, [ 'in_footer' => false ] );
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

		wp_register_script( 'gtmkit-container', '', [ 'gtmkit-datalayer' ], GTMKIT_VERSION, [ 'in_footer' => false ] );
		wp_enqueue_script( 'gtmkit-container' );
		wp_add_inline_script( 'gtmkit-container', $script );
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
			echo "/* Google Tag Manager with Stape.io Cookie Keeper */\n";
			echo '!function(){"use strict";function e(e,t,o){return void 0===t&&(t=""),"cookie"===e?function(e){for(var t=0,o=document.cookie.split(";");t<o.length;t++){var r=o[t].split("=");if(r[0].trim()===e)return r[1]}}(t):"localStorage"===e?(r=t,localStorage.getItem(r)):"jsVariable"===e?window[t]:"cssSelector"===e?(n=t,i=o,a=document.querySelector(n),i?null==a?void 0:a.getAttribute(i):null==a?void 0:a.textContent):void console.warn("invalid uid source",e);var r,n,i,a}!function(t,o,r,n,i,a,c,l,s,u){var d,v,E,I;try{v=l&&(E=navigator.userAgent,(I=/Version\/([0-9\._]+)(.*Mobile)?.*Safari.*/.exec(E))&&parseFloat(I[1])>=16.4)?e(l,"_sbp",""):void 0}catch(e){console.error(e)}var g=t;g[n]=g[n]||[];g[n].push({"gtm.start":(new Date).getTime(),event:"gtm.js"});var m=o.getElementsByTagName(r)[0],T=v?"&bi="+encodeURIComponent(v):"",_=o.createElement(r),f=v?"kp"+c:c,dl=n!="dataLayer"?"&l="+n:"";_.async=!0,_.src="https://' . esc_attr( $domain ) . '/"+f+".js?id=' . esc_attr( $gtm_id ) . '"+dl+T' . ( ( ! empty( Options::init()->get( 'general', 'gtm_auth' ) ) && ! empty( Options::init()->get( 'general', 'gtm_preview' ) ) ) ? "+'&gtm_auth=" . esc_attr( Options::init()->get( 'general', 'gtm_auth' ) ) . '&gtm_preview=' . esc_attr( Options::init()->get( 'general', 'gtm_preview' ) ) . "&gtm_cookies_win=x'" : '' ) . ',null===(d=m.parentNode)||void 0===d||d.insertBefore(_,m)}(window,document,"script","' . esc_js( $this->datalayer_name ) . '",0,0,"' . esc_attr( $loader ) . '","cookie")}();';
			echo "\n/* End Google Tag Manager */\n";
		} else {
			echo "/* Google Tag Manager */\n";
			echo "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
			echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
			echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
			echo "'https://" . esc_attr( $domain ) . '/' . esc_attr( $loader ) . ".js?id='+i+dl";
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

		echo '<noscript><iframe src="https://' . esc_attr( $domain ) . '/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
	}

	/**
	 * Console warning
	 */
	public function container_disabled(): void {
		echo '<script>console.warn("[GTM Kit] Google Tag Manager container is disabled.");</script>';
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
}
