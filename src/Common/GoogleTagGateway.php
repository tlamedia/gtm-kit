<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Options\Options;

/**
 * Resolves whether the Google tag gateway serves this request, and from where.
 *
 * The gateway serves the container from the site's own origin through a small
 * proxy file, so the request for the tag, and Google's second-stage script,
 * are same-origin. The measurement hits the tag later makes are not: they
 * still go to Google, because Google routes them through the site's domain
 * only for domains behind a CDN or load balancer it integrates with, on a
 * flag it sets in the config it serves. Nothing served from this proxy can
 * set that flag. Everything that needs to know whether the gateway is
 * serving asks this class, so the snippet, the resource hints, the admin
 * copy and the health checks cannot disagree about the answer.
 *
 * Three states matter and they are deliberately distinct:
 *
 * - available: nothing about the configuration rules the gateway out.
 * - enabled: the site owner has switched it on.
 * - active: it is switched on and currently safe to serve from.
 *
 * The gap between enabled and active is the whole point. A proxy that stops
 * answering must not become a silent tracking outage, so an enabled gateway
 * whose checks are failing falls back to the standard loader and says so.
 */
final class GoogleTagGateway {

	/**
	 * The path of the proxy file, relative to the plugin root.
	 *
	 * @var string
	 */
	public const PROXY_RELATIVE_PATH = 'gtg/measurement.php';

	/**
	 * An instance of Options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * The public URL of the proxy file.
	 *
	 * @return string
	 */
	public static function proxy_url(): string {
		return plugins_url( self::PROXY_RELATIVE_PATH, GTMKIT_FILE );
	}

	/**
	 * The filesystem path of the proxy file.
	 *
	 * @return string
	 */
	public static function proxy_file(): string {
		return GTMKIT_PATH . self::PROXY_RELATIVE_PATH;
	}

	/**
	 * Whether a custom server-side tagging domain rules the gateway out.
	 *
	 * A custom sGTM domain already serves the container from a domain the site
	 * owner controls. That is a different first-party mechanism with its own
	 * infrastructure, and pointing the loader at both at once would mean
	 * choosing one and silently ignoring the other. Rather than pick for the
	 * site owner, the gateway stands down and the admin says which one is
	 * serving the container.
	 *
	 * @return bool
	 */
	public function is_blocked_by_sgtm_domain(): bool {
		return (string) $this->options->get( 'general', 'sgtm_domain' ) !== '';
	}

	/**
	 * Whether the gateway can be offered on this site at all.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return ! $this->is_blocked_by_sgtm_domain();
	}

	/**
	 * Whether the site owner has switched the gateway on.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->options->get( 'general', 'google_tag_gateway' );
	}

	/**
	 * Whether this request's container should be served through the gateway.
	 *
	 * @return bool
	 */
	public function is_active(): bool {

		if ( ! $this->is_enabled() || ! $this->is_available() ) {
			return false;
		}

		return GoogleTagGatewayHealth::is_passing();
	}

	/**
	 * Whether the gateway is switched on but cannot currently be used.
	 *
	 * The state the fallback notice reports: the site owner asked for the
	 * gateway and is not getting it, which is the one combination they need
	 * to be told about.
	 *
	 * @return bool
	 */
	public function is_falling_back(): bool {
		return $this->is_enabled() && ! $this->is_active();
	}
}
