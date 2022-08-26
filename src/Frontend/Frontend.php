<?php

namespace TLA\GTM_Kit\Frontend;

use TLA\GTM_Kit\Options;

class Frontend {

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
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options        = $options;
		$this->datalayer_name = ( $this->options->get( 'general', 'datalayer_name' ) ) ?: 'dataLayer';
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {
		$page = new static( $options );

		$container_active = Options::init()->get( 'general', 'container_active' );

		add_action( 'wp_head', [ $page, 'get_header_datalayer' ], 1, 0 );
		add_action( 'wp_head', [ $page, 'get_datalayer_content' ] );
		if ( $container_active ) {
			add_action( 'wp_head', [ $page, 'get_header_script' ], 10, 0 );
		} else {
			add_action( 'wp_head', [ $page, 'container_disabled' ]);
		}

		add_filter( 'rocket_excluded_inline_js_content', [ $page, 'wp_rocket_exclude_javascript' ] );

		$noscript_implementation = Options::init()->get( 'general', 'noscript_implementation' );

		if ($noscript_implementation == '0' && $container_active) {
			add_action( 'wp_body_open', [ $page, 'get_body_script' ] );
		} elseif ($noscript_implementation == '1' && $container_active) {
			add_action( 'body_footer', [ $page, 'get_body_script' ] );
		}
	}

	/**
	 * The dataLayer initialization and settings
	 */
	public function get_header_datalayer(): void {

		$script_attributes = apply_filters('gtmkit_header_script_attributes', 'data-cfasync="false" data-nowprocket');

		$content = "<!-- GTM Kit -->\n";
		$content .= "<script $script_attributes>\n";
		$content .= 'var ' . esc_js( $this->datalayer_name ) . ' = ' . esc_js( $this->datalayer_name ) . ' || [];' . "\n";
		$content .= 'var gtmkit_settings = ' . json_encode( apply_filters( 'gtmkit_header_script_settings', [ 'datalayer_name' => $this->datalayer_name ] ), JSON_FORCE_OBJECT ) . "\n";
		$content .= "</script>\n";

		echo $content;
	}

	/**
	 * The dataLayer content included before the GTM container script
	 */
	public function get_datalayer_content(): void {

		$cript_attributes = apply_filters('gtmkit_datalayer_content_script_attributes', 'data-cfasync="false" data-nowprocket');

		$content = "<!-- GTM Kit -->\n";
		$content .= "<script $cript_attributes>\n";

		if ( $this->options->get('general', 'gtm_id') ) {
			$datalayer_data = apply_filters( 'gtmkit_datalayer_content', [] );

			$content .= 'var dataLayer_content = ' . json_encode( $datalayer_data ) . ";\n";

			$content .= $this->datalayer_name . '.push( dataLayer_content );'."\n";
		}

		$content .= "</script>\n";

		echo $content;
	}

	/**
	 * The Google Tag Manager container script
	 */
	public function get_header_script(): void {

		$gtm_id = Options::init()->get( 'general', 'gtm_id' );

		if (empty($gtm_id)) return;

		$domain = (Options::init()->get( 'general', 'sgtm_domain' )) ?: 'www.googletagmanager.com';
		$loader = (Options::init()->get( 'general', 'sgtm_container_identifier' )) ?: 'gtm';

		$gtm_script= "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
			new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
			j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
			'https://" . $domain . "/" . $loader . ".js?id='+i+dl;f.parentNode.insertBefore(j,f);
			})(window,document,'script','dataLayer','" . $gtm_id . "');"
		;

		$script_implementation = (int) Options::init()->get( 'general', 'script_implementation' );

		$delay = ($script_implementation == 2) ? 3 : 0;

		$delay_script = "
			window.requestIdleCallback =
			    window.requestIdleCallback ||
			    function (cb) {
			        var start = Date.now();
			        return setTimeout(function () {
			            cb({
			                didTimeout: false,
			                timeRemaining: function () {
			                    return Math.max(0, 50 - (Date.now() - start));
			                }
			            });
			        }, 1);
			    };

			requestIdleCallback(function () {setTimeout(function () {
			    $gtm_script
			}, $delay * 1000)});"
		;

		$script = "<!-- Google Tag Manager -->\n<script data-cfasync=\"false\" data-nowprocket>\n";

		if ($script_implementation > 0) {
			$script .= $delay_script;
		} else {
			$script .= $gtm_script;
		}

		$script .= "</script>\n<!-- End Google Tag Manager -->\n";

		echo $script;
	}

	/**
	 * The Google Tag Manager noscript
	 */
	public static function get_body_script(): void {
		$gtm_id = Options::init()->get( 'general', 'gtm_id' );

		$gtm_tag = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm_id . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';

		echo $gtm_tag;
	}

	/**
	 * Console warning
	 */
	public static function container_disabled(): void {
		echo '<script>console.warn("[GTM Kit] Google Tag Manager container is disabled.");</script>';
	}

	public function wp_rocket_exclude_javascript( $pattern ) {
		$pattern[] = 'dataLayer';
		$pattern[] = 'gtmkit';

		return $pattern;
	}

}
