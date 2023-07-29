<?php
/**
 * Contact Form 7
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * Contact Form 7 integration
 */
final class ContactForm7 extends AbstractIntegration {

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 * @param Util $util
	 */
	public function __construct( Options $options, Util $util) {
		// Call parent constructor.
		parent::__construct( $options, $util );
	}

	/**
	 * Get instance
	 */
	public static function instance(): ?ContactForm7 {
		if ( is_null( self::$instance ) ) {
			$options        = new Options();
			$rest_API_server = new RestAPIServer();
			$util        = new Util( $rest_API_server );
			self::$instance = new self( $options, $util );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 * @param Util $util
	 */
	public static function register( Options $options, Util $util): void {

		self::$instance = new self( $options, $util );

		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'cf7_load_js' ) == 1 && ! wp_script_is( 'contact-form-7' ) ) {
			return;
		}

		wp_enqueue_script(
			'gtmkit-cf7',
			GTMKIT_URL . 'assets/js/contact-form-7.js',
			[],
			$this->util->get_plugin_version(),
			true
		);

	}
}
