<?php
/**
 * Contact Form 7
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Integration;

use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Contact Form 7 integration
 */
final class ContactForm7 extends AbstractIntegration {

	/**
	 * Instance.
	 *
	 * @var null|ContactForm7 An instance of ContactForm7.
	 */
	protected static ?ContactForm7 $instance = null;

	/**
	 * Get instance
	 */
	public static function instance(): ContactForm7 {
		if ( is_null( self::$instance ) ) {
			$options         = new Options();
			$rest_api_server = new RestAPIServer();
			$util            = new Util( $options, $rest_api_server );
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

		if ( (int) $this->options->get( 'integrations', 'cf7_load_js' ) === 1 && ! wp_script_is( 'contact-form-7' ) ) {
			return;
		}
		$this->util->enqueue_script( 'gtmkit-cf7', 'integration/contact-form-7.js' );

		// Hand the CF7 module the generate_lead toggle + an optional
		// payload override so the second push fires alongside the
		// existing `gtmkit.CF7MailSent` event without requiring a
		// second `wpcf7mailsent` listener.
		$generate_lead_enabled = (bool) $this->options->get( 'general', 'engagement_event_generate_lead_enabled' );

		/**
		 * Extra fields merged into the `generate_lead` payload. Sites
		 * that want to assign a lead value (e.g. `value` + `currency`)
		 * return them here. Empty by default; the event is otherwise
		 * carried with the same form metadata the CF7 integration
		 * already exposes (`formId`, `response`).
		 *
		 * @param array<string, mixed> $payload       Extra fields merged into the push.
		 * @param array<string, mixed> $form_metadata Metadata available at registration time. Empty here because per-form values become available only at submission time inside the JS listener.
		 */
		$payload = apply_filters( 'gtmkit_engagement_event_generate_lead_payload', [], [] );
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		wp_localize_script(
			'gtmkit-cf7',
			'gtmkitCf7Engagement',
			[
				'generateLeadEnabled' => $generate_lead_enabled,
				'payload'             => (object) $payload,
			]
		);
	}
}
