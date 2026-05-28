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
	 * "Load JavaScript" setting value for the "on all pages" mode.
	 * The recommended mode is `1` and falls through any non-`2`
	 * value, so only the all-pages path needs a named constant.
	 *
	 * @var int
	 */
	private const LOAD_JS_ALL_PAGES = 2;

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
	 * The recommended "only on pages where CF7 is registered" mode
	 * hooks into the `wpcf7_enqueue_scripts` action that Contact Form
	 * 7 fires from inside its own enqueue path. That action is
	 * invoked from both the standard `wp_enqueue_scripts` flow and
	 * the late shortcode-time enqueue used by performance plugins
	 * (e.g. WP Rocket sets `wpcf7_load_js` to false so CF7 only loads
	 * during shortcode render). Using the action means our integration
	 * script lands on every page where CF7's own script lands, with
	 * no false negatives from `wp_script_is()` running before CF7 has
	 * decided to enqueue. The "on all pages" mode keeps the direct
	 * `wp_enqueue_scripts` hook so the integration loads unconditionally.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public static function register( Options $options, Util $util ): void {

		self::$instance = new self( $options, $util );

		$mode = (int) $options->get( 'integrations', 'cf7_load_js' );

		if ( self::LOAD_JS_ALL_PAGES === $mode ) {
			add_action( 'wp_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
			return;
		}

		add_action( 'wpcf7_enqueue_scripts', [ self::$instance, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * Runs from one of two hooks depending on the "Load JavaScript"
	 * setting; both reach the same handle + localized config. The
	 * hook-level gating in {@see self::register()} replaces the old
	 * inline `wp_script_is()` check, which produced false negatives on
	 * pages where CF7 enqueues later than `wp_enqueue_scripts` priority 10.
	 */
	public function enqueue_scripts(): void {

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
