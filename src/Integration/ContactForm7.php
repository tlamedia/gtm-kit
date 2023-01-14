<?php
/**
 * Contact Form 7
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Options;

/**
 * Contact Form 7 integration
 */
class ContactForm7 extends AbstractIntegration {

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Get instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			$options        = new Options();
			self::$instance = new self( $options );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {

		self::$instance = new self( $options );

		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'cf7_load_js' ) == 1 && ! wp_script_is( 'contact-form-7' ) ) {
			return;
		}

		if ( wp_get_environment_type() == 'local' ) {
			$version = time();
		} else {
			$version = GTMKIT_VERSION;
		}

		wp_enqueue_script(
			'gtmkit-cf7',
			GTMKIT_URL . 'assets/js/contact-form-7.js',
			[],
			$version,
			true
		);

	}
}
