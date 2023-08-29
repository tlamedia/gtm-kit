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
		$page = new Frontend( $options );

		if ( empty( $options->get( 'general', 'just_the_container' ) ) ) {
			add_action( 'wp_head', [ $page, 'get_header_datalayer' ], 1, 0 );
			add_action( 'wp_head', [ $page, 'get_datalayer_content' ] );
		}

		$container_active = Options::init()->get( 'general', 'container_active' );

		if ( $container_active ) {
			add_action( 'wp_head', [ $page, 'get_header_script' ], 10, 0 );
		} elseif ( Options::init()->get( 'general', 'console_log' ) ) {
			add_action( 'wp_head', [ $page, 'container_disabled' ] );
		}

		add_filter( 'wp_resource_hints', [ $page, 'dns_prefetch' ], 10, 2 );
		add_filter( 'rocket_excluded_inline_js_content', [ $page, 'wp_rocket_exclude_javascript' ] );

		$noscript_implementation = Options::init()->get( 'general', 'noscript_implementation' );

		if ( $noscript_implementation === '0' && $container_active ) {
			add_action( 'wp_body_open', [ $page, 'get_body_script' ] );
		} elseif ( $noscript_implementation === '1' && $container_active ) {
			add_action( 'body_footer', [ $page, 'get_body_script' ] );
		}
	}

	/**
	 * The dataLayer initialization and settings
	 */
	public function get_header_datalayer(): void {
		$settings = [
			'datalayer_name' => $this->datalayer_name,
			'console_log'    => Options::init()->get( 'general', 'console_log' ),
		];
		?>

		<!-- GTM Kit -->
		<script <?php $this->get_attributes(); ?>>
			window.<?php echo esc_js( $this->datalayer_name ); ?> = window.<?php echo esc_js( $this->datalayer_name ); ?> || [];
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
			}
			<?php endif; ?>
		</script>
		<?php
	}

	/**
	 * Get attributes
	 */
	public function get_attributes(): void {
		$attributes = apply_filters(
			'gtmkit_header_script_attributes',
			[
				'data-cfasync'       => 'false',
				'data-nowprocket'    => '',
				'data-cookieconsent' => 'ignore',
			]
		);

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

			echo 'const dataLayer_content = ' . wp_json_encode( $datalayer_data ) . ";\n";

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

		echo "</script>\n<!-- End Google Tag Manager -->\n";
	}

	/**
	 * Get GTM script
	 *
	 * @param string $gtm_id The GTM container ID.
	 */
	public function get_gtm_script( string $gtm_id ): void {
		$domain = Options::init()->get( 'general', 'sgtm_domain' ) ? Options::init()->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';
		$loader = Options::init()->get( 'general', 'sgtm_container_identifier' ) ? Options::init()->get( 'general', 'sgtm_container_identifier' ) : 'gtm';

		echo "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
			new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
			j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
			'https://" . esc_attr( $domain ) . '/' . esc_attr( $loader ) . ".js?id='+i+dl;f.parentNode.insertBefore(j,f);
			})(window,document,'script','" . esc_js( $this->datalayer_name ) . "','" . esc_attr( $gtm_id ) . "');";
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
	public static function container_disabled(): void {
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

		$domain = Options::init()->get( 'general', 'sgtm_domain' ) ? Options::init()->get( 'general', 'sgtm_domain' ) : 'www.googletagmanager.com';

		if ( 'dns-prefetch' === $relation_type ) {
			$hints[] = '//' . $domain;
		}

		return $hints;
	}
}
