<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Whether GTM Kit's Consent Mode defaults have anything that can lift them.
 *
 * The defaults are a starting point that a consent platform is expected to
 * update once the visitor answers. With no platform, the defaults are all
 * the visitor ever gets: when they deny analytics storage, Google's tags run
 * without cookies for every visit, which looks healthy because data keeps
 * arriving.
 *
 * "No platform" is read from two places. The consent signal registry says
 * whether anything above GTM Kit's own defaults owns consent, such as an
 * integration with the WP Consent API. Plugin detection covers the consent
 * platforms GTM Kit knows by name. Neither can see a platform loaded by the
 * theme or inside the container, which is why the wording built on this
 * asks the site owner to check rather than asserting the site is broken.
 */
final class ConsentDefaultsCheck {

	/**
	 * The id of the badge on the Consent settings page.
	 *
	 * @var string
	 */
	public const BADGE_ID = 'gtmkit_consent_defaults_without_platform';

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * The consent signal registry.
	 *
	 * @var ConsentSignalSourceRegistry
	 */
	private ConsentSignalSourceRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @param Options                          $options  An instance of Options.
	 * @param ConsentSignalSourceRegistry|null $registry The consent signal registry, built from the options when omitted.
	 */
	public function __construct( Options $options, ?ConsentSignalSourceRegistry $registry = null ) {
		$this->options  = $options;
		$this->registry = $registry ?? new ConsentSignalSourceRegistry( $options );
	}

	/**
	 * Register the Consent settings page badge.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {
		$check = new self( $options );

		add_filter( 'gtmkit_consent_admin_badges', [ $check, 'add_badge' ] );
	}

	/**
	 * Whether the Consent Mode defaults are switched on.
	 *
	 * @return bool
	 */
	public function are_defaults_on(): bool {
		return (bool) $this->options->get( 'general', 'gcm_default_settings' );
	}

	/**
	 * Whether GTM Kit's defaults are the only thing on the site that owns consent.
	 *
	 * @return bool
	 */
	public function is_only_source(): bool {

		if ( '' !== CMPDetection::get_display_name( CMPDetection::detect_active_cmp() ) ) {
			return false;
		}

		return ! $this->has_integration_source();
	}

	/**
	 * Whether a consent source other than GTM Kit's own defaults owns consent.
	 *
	 * True when the consent signal registry resolves to a source registered
	 * by an integration, such as one reading the WP Consent API.
	 *
	 * @return bool
	 */
	public function has_integration_source(): bool {
		$source = $this->registry->resolve();

		return null !== $source && ConsentSignalSourceRegistry::DEFAULT_SOURCE_ID !== $source['id'];
	}

	/**
	 * Whether the defaults grant analytics storage before the visitor answers.
	 *
	 * @return bool
	 */
	public function grants_analytics(): bool {
		$state = $this->registry->read_state();

		if ( null !== $state ) {
			return 'granted' === $state['analytics_storage'];
		}

		return (bool) $this->options->get( 'general', 'gcm_analytics_storage' );
	}

	/**
	 * Whether the defaults are on, deny analytics storage, and nothing can lift them.
	 *
	 * @return bool
	 */
	public function is_nothing_to_lift(): bool {
		return $this->are_defaults_on() && $this->is_only_source() && ! $this->grants_analytics();
	}

	/**
	 * Add the warning badge to the Consent settings page while the defaults have nothing to lift them.
	 *
	 * @param mixed $badges The badges collected so far.
	 *
	 * @return array<int|string, mixed>
	 */
	public function add_badge( $badges ): array {
		$badges = is_array( $badges ) ? $badges : [];

		if ( ! $this->is_nothing_to_lift() ) {
			return $badges;
		}

		$badges[] = [
			'id'       => self::BADGE_ID,
			'message'  => __( 'No consent platform was found on this site, so nothing will grant the categories these defaults deny. Visitors will be measured without cookies until something does.', 'gtm-kit' ),
			'severity' => 'warning',
		];

		return $badges;
	}
}
