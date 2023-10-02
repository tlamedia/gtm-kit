<?php
/**
 * Contact Form 7
 *
 * @package GTM Kit
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
	 * Instance.
	 *
	 * @var ContactForm7 An instance of ContactForm7.
	 */
	protected static $instance = null;

	/**
	 * Get instance
	 */
	public static function instance(): ?ContactForm7 {
		if ( is_null( self::$instance ) ) {
			$options         = new Options();
			$rest_api_server = new RestAPIServer();
			$util            = new Util( $rest_api_server );
			self::$instance  = new self( $options, $util );
		}

		return self::$instance;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public static function register( Options $options, Util $util ): void {

		self::$instance = new self( $options, $util );

		add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts(): void {

		if ( $this->options->get( 'integrations', 'cf7_load_js' ) === 1 && ! wp_script_is( 'contact-form-7' ) ) {
			return;
		}

		wp_enqueue_script(
			'gtmkit-cf7',
			GTMKIT_URL . 'assets/integration/contact-form-7.js',
			[],
			$this->util->get_plugin_version(),
			true
		);
	}
}
