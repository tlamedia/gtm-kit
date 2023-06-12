<?php

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

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
		} elseif ( Options::init()->get( 'general', 'console_log' ) ) {
			add_action( 'wp_head', [ $page, 'container_disabled' ] );
		}

		add_filter( 'wp_resource_hints', [ $page, 'dns_prefetch' ], 10, 2 );
		add_filter( 'rocket_excluded_inline_js_content', [ $page, 'wp_rocket_exclude_javascript' ] );

		$noscript_implementation = Options::init()->get( 'general', 'noscript_implementation' );

		if ( $noscript_implementation == '0' && $container_active ) {
			add_action( 'wp_body_open', [ $page, 'get_body_script' ] );
		} elseif ( $noscript_implementation == '1' && $container_active ) {
			add_action( 'body_footer', [ $page, 'get_body_script' ] );
		}
	}

	/**
	 * The dataLayer initialization and settings
	 */
	public function get_header_datalayer(): void {
		?>
		<!-- GTM Kit -->
		<script <?php $this->get_attributes(); ?>>
			var <?php echo esc_js( $this->datalayer_name ); ?> = <?php echo esc_js( $this->datalayer_name ); ?> ||
			[];
			var gtmkit_settings = <?php echo json_encode( apply_filters( 'gtmkit_header_script_settings', [ 'datalayer_name' => $this->datalayer_name ] ), JSON_FORCE_OBJECT ); ?>;
		</script>
		<?php
	}

	/**
	 * Get attributes
	 */
	public function get_attributes(): void {
		$attributes = apply_filters( 'gtmkit_header_script_attributes', [ 'data-cfasync'    => 'false',
																		  'data-nowprocket' => ''
		] );

		foreach ( $attributes as $attribute => $value ) {
			echo ' ' . esc_attr( $attribute ) . '="' . esc_attr( $value ) . '"';
		}
	}

	/**
	 * The dataLayer content included before the GTM container script
	 */
public function get_datalayer_content(): void {
	?>
	<!-- GTM Kit -->
	<script <?php $this->get_attributes(); ?>>
		<?php

		if ( $this->options->get( 'general', 'gtm_id' ) ) {
			$datalayer_data = apply_filters( 'gtmkit_datalayer_content', [] );

			echo 'var dataLayer_content = ' . json_encode( $datalayer_data ) . ";\n";

			echo esc_attr( $this->datalayer_name ) . '.push( dataLayer_content );' . "\n";
		}

		echo "</script>\n";

		}

		/**
		 * The Google Tag Manager container script
		 */
		public function get_header_script(): void {

		$gtm_id = Options::init()->get( 'general', 'gtm_id' );

		if ( empty( $gtm_id ) ) {
			return;
		}

		$script_implementation = (int) Options::init()->get( 'general', 'script_implementation' );
		?>
		<!-- Google Tag Manager -->
		<script <?php $this->get_attributes(); ?>>
	<?php

	if ( $script_implementation > 0 ) {
		echo '
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

			requestIdleCallback(function () {';
		$this->get_gtm_script( $gtm_id );
		echo  '});';
	} else {
		$this->get_gtm_script( $gtm_id );
	}

	echo "</script>\n<!-- End Google Tag Manager -->\n";
}

	/**
	 * Get GTM script
	 */
	public function get_gtm_script( string $gtm_id ): void {
		$domain = ( Options::init()->get( 'general', 'sgtm_domain' ) ) ?: 'www.googletagmanager.com';
		$loader = ( Options::init()->get( 'general', 'sgtm_container_identifier' ) ) ?: 'gtm';

		echo "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
			new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
			j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
			'https://" . esc_attr( $domain ) . "/" . esc_attr( $loader ) . ".js?id='+i+dl;f.parentNode.insertBefore(j,f);
			})(window,document,'script','dataLayer','" . esc_attr( $gtm_id ) . "');";
	}

	/**
	 * The Google Tag Manager noscript
	 */
	public static function get_body_script(): void {
		$domain = ( Options::init()->get( 'general', 'sgtm_domain' ) ) ?: 'www.googletagmanager.com';
		$gtm_id = Options::init()->get( 'general', 'gtm_id' );

		echo '<noscript><iframe src="https://' . esc_attr( $domain ) . '/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
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

	/**
	 * Adds Google Tag Manager domain DNS Prefetch printed by wp_resource_hints
	 *
	 * @param array $hints URLs to print for resource hints.
	 * @param string $relation_type The relation type the URL are printed for.
	 *
	 * @return array URL to print
	 */
	function dns_prefetch( array $hints, string $relation_type ): array {

		$domain = ( Options::init()->get( 'general', 'sgtm_domain' ) ) ?: 'www.googletagmanager.com';

		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = '//' . $domain;
		}

		return $hints;
	}
}
