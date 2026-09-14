<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options\Processor;

use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Options\OptionsFactory;

/**
 * Google tag gateway processor.
 *
 * Guards the moment the gateway is switched on. Serving the container from
 * this site's own origin only works when the proxy is reachable and the
 * server can reach the gateway service, so switching it on without those
 * being true would replace working tracking with none.
 *
 * The check runs on the transition only. An already-enabled setting is left
 * alone, because a later failure is handled at serving time by falling back
 * to the standard loader and telling the site owner, which keeps tracking up.
 * Re-checking on every save would instead let an unrelated settings change
 * switch the gateway off during a passing network blip.
 *
 * The settings screen asks {@see self::refusal()} before it saves, and tells
 * the site owner why a switch-on was refused without writing anything. Here a
 * refusal coerces the value instead, so a save that reaches this point by
 * another route, such as an import, still lands its other settings.
 */
final class GoogleTagGatewayProcessor implements OptionsAwareProcessorInterface {

	/**
	 * Refused because a custom server-side tagging domain is set.
	 *
	 * @var string
	 */
	public const REFUSED_SGTM_DOMAIN = 'sgtm_domain';

	/**
	 * Refused because the server cannot reach the gateway service.
	 *
	 * @var string
	 */
	public const REFUSED_SERVICE = 'service';

	/**
	 * Refused because the proxy on this site does not serve the tag.
	 *
	 * @var string
	 */
	public const REFUSED_SCRIPT = 'script';

	/**
	 * Process the gateway setting.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 *
	 * @return bool Processed value.
	 */
	public function process( $value, $old_value ): bool {
		return $this->process_with_options( $value, $old_value, [] );
	}

	/**
	 * Process the gateway setting against the save it belongs to.
	 *
	 * @param mixed                $value New value.
	 * @param mixed                $old_value Previous value.
	 * @param array<string, mixed> $options The options being saved.
	 *
	 * @return bool Processed value.
	 */
	public function process_with_options( $value, $old_value, array $options ): bool {

		$value = (bool) $value;

		// Switching off, or staying as it was, needs no check.
		if ( ! $value || (bool) $old_value ) {
			return $value;
		}

		return null === self::refusal( $options );
	}

	/**
	 * Why switching the gateway on would be refused, if it would.
	 *
	 * The server-side tagging domain is read from the options being saved
	 * when they carry one, so clearing the domain and switching the gateway on
	 * in the same save is judged on the outcome of that save, not on the
	 * stored domain it replaces.
	 *
	 * @param array<string, mixed> $options The options being saved.
	 * @param bool                 $fresh Run the checks now rather than reuse a recent result.
	 *
	 * @return string|null One of the REFUSED_* reasons, or null when the gateway can be switched on.
	 */
	public static function refusal( array $options, bool $fresh = false ): ?string {

		$general = ( isset( $options['general'] ) && is_array( $options['general'] ) ) ? $options['general'] : [];
		$domain  = array_key_exists( 'sgtm_domain', $general )
			? $general['sgtm_domain']
			: OptionsFactory::get_instance()->get( 'general', 'sgtm_domain' );

		// A custom server-side tagging domain is a different first-party
		// mechanism and already serves the container. The two do not compose.
		if ( '' !== ( is_scalar( $domain ) ? (string) $domain : '' ) ) {
			return self::REFUSED_SGTM_DOMAIN;
		}

		// The container checked is the one this save sets, for the same reason
		// as the domain: on a fresh setup the ID and the switch arrive together,
		// and the stored ID is still empty.
		$gtm_id = self::container_id( $options );
		$result = $fresh ? GoogleTagGatewayHealth::run_checks( $gtm_id ) : GoogleTagGatewayHealth::ensure_fresh( $gtm_id );

		if ( empty( $result['service'] ) ) {
			return self::REFUSED_SERVICE;
		}

		if ( empty( $result['script'] ) ) {
			return self::REFUSED_SCRIPT;
		}

		return null;
	}

	/**
	 * The container ID a save will leave in place.
	 *
	 * Normalised the way the save normalises it, so the check requests the
	 * container the page will request. Falls back to the stored ID when the
	 * save does not carry one.
	 *
	 * @param array<string, mixed> $options The options being saved.
	 *
	 * @return string
	 */
	public static function container_id( array $options ): string {

		$general = ( isset( $options['general'] ) && is_array( $options['general'] ) ) ? $options['general'] : [];

		if ( array_key_exists( 'gtm_id', $general ) ) {
			return is_scalar( $general['gtm_id'] ) ? ( new GTMIdProcessor() )->process( (string) $general['gtm_id'], null ) : '';
		}

		return (string) OptionsFactory::get_instance()->get( 'general', 'gtm_id' );
	}

	/**
	 * Handle side effects after save.
	 *
	 * Switching the gateway on or off changes whether the daily check should
	 * be running at all, and the answer should not wait for the next admin
	 * page load to take effect. A gateway that starts serving with no daily
	 * check behind it would keep serving on its switch-on result however long
	 * ago that was, so the check is scheduled the moment the switch lands.
	 *
	 * @param mixed $value New value.
	 * @param mixed $old_value Previous value.
	 *
	 * @return void
	 */
	public function after_save( $value, $old_value ): void {

		if ( (bool) $value === (bool) $old_value ) {
			return;
		}

		if ( $value ) {
			GoogleTagGatewayHealth::schedule();

			return;
		}

		GoogleTagGatewayHealth::clear_scheduled_event();
	}
}
