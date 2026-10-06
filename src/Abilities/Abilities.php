<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Abilities;

use TLA_Media\GTM_Kit\Admin\ConsentDefaultsCheck;
use TLA_Media\GTM_Kit\Admin\GoogleTagGatewaySiteHealth;
use TLA_Media\GTM_Kit\Admin\Integrations;
use TLA_Media\GTM_Kit\Admin\SiteHealth;
use TLA_Media\GTM_Kit\Admin\SnippetScanSiteHealth;
use TLA_Media\GTM_Kit\Common\CMPDetection;
use TLA_Media\GTM_Kit\Common\GoogleTagGateway;
use TLA_Media\GTM_Kit\Common\GoogleTagGatewayHealth;
use TLA_Media\GTM_Kit\Common\RestAPIServer;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Common\StapeLoader;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Frontend\ConsentSignalSourceRegistry;
use TLA_Media\GTM_Kit\Frontend\Frontend;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Describes GTM Kit's setup to AI assistants through the WordPress Abilities API.
 *
 * Three abilities answer three questions: how the container is loaded, what
 * consent GTM Kit sets, and whether tracking is healthy. Each answer comes
 * from the class the settings screen, Site Health or the admin notices
 * already ask, so an assistant and the site owner can never be told
 * different things about the same site.
 *
 * Every ability is read-only and limited to users who can manage options.
 * None of them makes an HTTP request: health checks that depend on one are
 * reported from the result the scheduled check stored, and a check that
 * stores nothing is left out. Container environment values and licence data
 * never appear in an answer.
 */
final class Abilities {

	/**
	 * The ability category slug.
	 *
	 * @var string
	 */
	public const CATEGORY = 'gtm-kit';

	/**
	 * The ability reporting how the container is loaded.
	 *
	 * @var string
	 */
	public const GET_CONFIGURATION = 'gtm-kit/get-configuration';

	/**
	 * The ability reporting the consent setup.
	 *
	 * @var string
	 */
	public const GET_CONSENT_STATUS = 'gtm-kit/get-consent-status';

	/**
	 * The ability reporting tracking health.
	 *
	 * @var string
	 */
	public const GET_TRACKING_HEALTH = 'gtm-kit/get-tracking-health';

	/**
	 * The Google tag gateway states.
	 *
	 * @var array<int, string>
	 */
	private const GATEWAY_STATES = [ 'off', 'active', 'falling_back', 'blocked_by_custom_domain' ];

	/**
	 * Plugin options.
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
	 * Hook the registration into the Abilities API.
	 *
	 * The registry fires its hooks the first time something asks for it, on
	 * any kind of request, so the callbacks are attached unconditionally and
	 * cost nothing until an ability is listed or run.
	 *
	 * @param Options $options An instance of Options.
	 *
	 * @return void
	 */
	public static function register( Options $options ): void {
		$abilities = new self( $options );

		add_action( 'wp_abilities_api_categories_init', [ $abilities, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $abilities, 'register_abilities' ] );
	}

	/**
	 * Whether GTM Kit registers its abilities on this site.
	 *
	 * @return bool
	 */
	private static function is_enabled(): bool {
		/**
		 * Filters whether GTM Kit registers its abilities with the WordPress Abilities API.
		 *
		 * The abilities only read, and only users who can manage options may
		 * run them, so they are registered by default. Return false to keep
		 * GTM Kit out of the Abilities API, and so out of every AI assistant
		 * connected to the site through it.
		 *
		 * WordPress builds the abilities registry once per request, the first
		 * time something asks for it, so add this filter from a plugin or the
		 * theme's functions.php rather than later in the request.
		 *
		 * @param bool $enabled Whether to register the abilities. Default true.
		 */
		return (bool) apply_filters( 'gtmkit_abilities_enabled', true );
	}

	/**
	 * Register the GTM Kit ability category.
	 *
	 * @return void
	 */
	public function register_category(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'GTM Kit', 'gtm-kit' ),
				'description' => __( 'Read how GTM Kit loads Google Tag Manager on this site, which consent it sets, and whether tracking is healthy.', 'gtm-kit' ),
			]
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_register_ability(
			self::GET_CONFIGURATION,
			$this->ability_args(
				__( 'Get GTM Kit configuration', 'gtm-kit' ),
				__( 'Returns how GTM Kit loads Google Tag Manager on this site: the container ID, the data layer name, the server-side tagging domain, the loader in use, the Google tag gateway state, whether container environment parameters are set (never their values), the active GTM Kit integrations and the GTM Kit version.', 'gtm-kit' ),
				self::configuration_schema(),
				[ $this, 'get_configuration' ]
			)
		);

		wp_register_ability(
			self::GET_CONSENT_STATUS,
			$this->ability_args(
				__( 'Get GTM Kit consent status', 'gtm-kit' ),
				__( 'Returns the Consent Mode defaults GTM Kit sets, the consent platform detected on the site, which consent source owns consent, whether the defaults deny analytics storage with nothing on the site able to lift them, and how GTM Kit treats consent that is unknown.', 'gtm-kit' ),
				self::consent_schema(),
				[ $this, 'get_consent_status' ]
			)
		);

		wp_register_ability(
			self::GET_TRACKING_HEALTH,
			$this->ability_args(
				__( 'Get GTM Kit tracking health', 'gtm-kit' ),
				__( 'Returns the GTM Kit Site Health results the site owner sees, the stored Google tag gateway check when the gateway is on, and the stored result of the daily check of a sample page. Reads stored results only and runs no new check.', 'gtm-kit' ),
				self::tracking_health_schema(),
				[ $this, 'get_tracking_health' ]
			)
		);
	}

	/**
	 * Whether the current user may run the abilities.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Report how GTM Kit loads Google Tag Manager.
	 *
	 * @return array<string, mixed>
	 */
	public function get_configuration(): array {
		$stape_loader = new StapeLoader( $this->options );
		$loader_state = $stape_loader->get_client_state();
		$sgtm_domain  = (string) $this->options->get( 'general', 'sgtm_domain' );

		return [
			'gtm_id'                            => (string) $this->options->get( 'general', 'gtm_id' ),
			'datalayer_name'                    => $stape_loader->get_inputs()['datalayer_name'],
			'server_side_tagging_domain'        => ( '' !== $sgtm_domain ) ? $sgtm_domain : null,
			'loader'                            => [
				'in_use'              => ( 'standard' === $loader_state['source'] ) ? 'standard' : 'stape',
				'stape_loader_wanted' => $stape_loader->is_switched_on(),
				'stape_loader_stored' => null !== $stape_loader->get_stored(),
			],
			'google_tag_gateway'                => $this->get_gateway_state(),
			'environment_parameters_configured' => '' !== (string) $this->options->get( 'general', 'gtm_auth' )
				|| '' !== (string) $this->options->get( 'general', 'gtm_preview' ),
			'active_integrations'               => $this->get_active_integrations(),
			'version'                           => GTMKIT_VERSION,
		];
	}

	/**
	 * Report the consent setup.
	 *
	 * @return array<string, mixed>
	 */
	public function get_consent_status(): array {
		$registry = new ConsentSignalSourceRegistry( $this->options );
		$check    = new ConsentDefaultsCheck( $this->options, $registry );
		$source   = $registry->resolve();
		$platform = CMPDetection::get_display_name( CMPDetection::detect_active_cmp() );

		return [
			'consent_mode_defaults_enabled' => $check->are_defaults_on(),
			'consent_state'                 => $registry->read_state(),
			'consent_platform'              => ( '' !== $platform ) ? $platform : null,
			'consent_source'                => ( null !== $source ) ? (string) $source['id'] : null,
			'integration_owns_consent'      => $check->has_integration_source(),
			'defaults_are_only_source'      => $check->is_only_source(),
			'defaults_grant_analytics'      => $check->grants_analytics(),
			'nothing_to_lift_defaults'      => $check->is_nothing_to_lift(),
			'unknown_consent'               => Frontend::get_unknown_consent(),
		];
	}

	/**
	 * Report tracking health from the checks the site owner sees.
	 *
	 * The settings API test is not included: it works by sending a request
	 * from the server to itself, and stores no result to read instead.
	 *
	 * @return array<string, mixed>
	 */
	public function get_tracking_health(): array {
		$site_health  = new SiteHealth( $this->options, new Util( $this->options, new RestAPIServer() ) );
		$gateway      = new GoogleTagGateway( $this->options );
		$snippet_scan = new SnippetScan( $this->options );

		$results = [
			$site_health->test_container(),
			$site_health->test_consent(),
		];

		$gateway_health = null;

		if ( $gateway->is_enabled() ) {
			$gateway_site_health = new GoogleTagGatewaySiteHealth();

			$results[] = $gateway_site_health->service_result( $site_health );
			$results[] = $gateway_site_health->script_result( $site_health );

			$stored         = GoogleTagGatewayHealth::get_result();
			$gateway_health = [
				'checked'           => null !== $stored,
				'passing'           => GoogleTagGatewayHealth::is_passing(),
				'service_reachable' => ( null !== $stored ) ? ! empty( $stored['service'] ) : null,
				'address_reachable' => ( null !== $stored ) ? ! empty( $stored['script'] ) : null,
				'checked_at'        => ( null !== $stored ) ? (int) $stored['checked'] : null,
			];
		}

		$results[] = ( new SnippetScanSiteHealth( $snippet_scan, $this->options ) )->run_test( $site_health );

		return [
			'checks'             => array_map( [ self::class, 'to_check' ], $results ),
			'google_tag_gateway' => $gateway_health,
			'snippet_scan'       => $this->get_snippet_scan( $snippet_scan ),
		];
	}

	/**
	 * Build the arguments shared by every ability.
	 *
	 * @param string               $label The ability label.
	 * @param string               $description The ability description.
	 * @param array<string, mixed> $output_schema The output schema.
	 * @param callable             $execute_callback The callback building the answer.
	 *
	 * @return array<string, mixed>
	 */
	private function ability_args( string $label, string $description, array $output_schema, callable $execute_callback ): array {
		return [
			'label'               => $label,
			'description'         => $description,
			'category'            => self::CATEGORY,
			// No `properties` key: an empty PHP array would reach clients as
			// a JSON list, which is not a valid JSON Schema `properties`.
			'input_schema'        => [
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => [],
			],
			'output_schema'       => $output_schema,
			'execute_callback'    => $execute_callback,
			'permission_callback' => [ $this, 'can_read' ],
			'meta'                => [
				'annotations'  => [
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				],
				// WordPress 7.1 derives the REST flag from `public`; 6.9 and
				// 7.0 read only `show_in_rest`, so both are set.
				'public'       => true,
				'show_in_rest' => true,
			],
		];
	}

	/**
	 * The Google tag gateway state, as the gateway itself resolves it.
	 *
	 * @return string One of GATEWAY_STATES.
	 */
	private function get_gateway_state(): string {
		$gateway = new GoogleTagGateway( $this->options );

		if ( $gateway->is_blocked_by_configuration() ) {
			return 'blocked_by_custom_domain';
		}

		if ( $gateway->is_falling_back() ) {
			return 'falling_back';
		}

		if ( $gateway->is_active() ) {
			return 'active';
		}

		return 'off';
	}

	/**
	 * The names of the integrations that are switched on and whose plugin is active.
	 *
	 * Read from the same lists the Integrations settings page shows.
	 *
	 * @return array<int, string>
	 */
	private function get_active_integrations(): array {
		$plugins = Integrations::get_plugins();
		$active  = [];

		foreach ( Integrations::get_integrations() as $key => $integration ) {
			if ( empty( $plugins[ $key ] ) || ! isset( $integration['option'], $integration['title'] ) ) {
				continue;
			}

			if ( $this->options->get( 'integrations', $integration['option'] ) ) {
				$active[] = (string) $integration['title'];
			}
		}

		return $active;
	}

	/**
	 * The stored result of the sample page check.
	 *
	 * The answers stay three-valued: null means the last check could not
	 * settle the question, which is not the same as no.
	 *
	 * @param SnippetScan $snippet_scan The scan engine.
	 *
	 * @return array<string, mixed>|null The result, or null when no check is stored.
	 */
	private function get_snippet_scan( SnippetScan $snippet_scan ): ?array {
		$result = $snippet_scan->get_result();

		if ( null === $result ) {
			return null;
		}

		$duplicate = is_array( $result['duplicate'] ?? null ) ? $result['duplicate'] : [];

		return [
			'state'                  => (string) $result['state'],
			'reason'                 => (string) ( $result['reason'] ?? '' ),
			'has_container'          => $snippet_scan->has_container(),
			'has_duplicate_tracking' => $snippet_scan->has_duplicate_tracking(),
			'duplicate_type'         => (string) ( $duplicate['type'] ?? '' ),
			'scanned_at'             => (int) ( $result['scanned_at'] ?? 0 ),
		];
	}

	/**
	 * Reduce a Site Health result to plain text an assistant can read.
	 *
	 * @param array<string, mixed> $result A result built by SiteHealth::build_result().
	 *
	 * @return array<string, string>
	 */
	private static function to_check( array $result ): array {
		return [
			'test'        => (string) $result['test'],
			'status'      => (string) $result['status'],
			'label'       => self::to_plain_text( (string) $result['label'] ),
			'description' => self::to_plain_text( (string) $result['description'] ),
		];
	}

	/**
	 * Strip markup from Site Health copy, keeping paragraphs apart.
	 *
	 * @param string $html The HTML.
	 *
	 * @return string
	 */
	private static function to_plain_text( string $html ): string {
		$text = wp_strip_all_tags( str_replace( '</p>', "</p>\n\n", $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/**
	 * The output schema of the configuration ability.
	 *
	 * @return array<string, mixed>
	 */
	private static function configuration_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'gtm_id'                            => [
					'type'        => 'string',
					'description' => __( 'The Google Tag Manager container ID, or an empty string when none is saved.', 'gtm-kit' ),
				],
				'datalayer_name'                    => [
					'type'        => 'string',
					'description' => __( 'The name of the data layer GTM Kit pushes to.', 'gtm-kit' ),
				],
				'server_side_tagging_domain'        => [
					'type'        => [ 'string', 'null' ],
					'description' => __( 'The custom domain the container is loaded from for server-side tagging, or null when none is set.', 'gtm-kit' ),
				],
				'loader'                            => [
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => [
						'in_use'              => [
							'type'        => 'string',
							'enum'        => [ 'standard', 'stape' ],
							'description' => __( 'The loader in use: the standard Google Tag Manager loader, or the one Stape issues for this site.', 'gtm-kit' ),
						],
						'stape_loader_wanted' => [
							'type'        => 'boolean',
							'description' => __( 'Whether the site owner has asked for the loader Stape issues.', 'gtm-kit' ),
						],
						'stape_loader_stored' => [
							'type'        => 'boolean',
							'description' => __( 'Whether a loader issued by Stape is stored.', 'gtm-kit' ),
						],
					],
				],
				'google_tag_gateway'                => [
					'type'        => 'string',
					'enum'        => self::GATEWAY_STATES,
					'description' => __( 'The Google tag gateway state: off; on and serving the container; on but falling back to the standard loader because its checks are not passing; or on but ruled out by a custom server-side tagging domain.', 'gtm-kit' ),
				],
				'environment_parameters_configured' => [
					'type'        => 'boolean',
					'description' => __( 'Whether container environment parameters are set. Their values are never returned.', 'gtm-kit' ),
				],
				'active_integrations'               => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => __( 'The GTM Kit integrations that are switched on and whose plugin is active.', 'gtm-kit' ),
				],
				'version'                           => [
					'type'        => 'string',
					'description' => __( 'The GTM Kit version.', 'gtm-kit' ),
				],
			],
		];
	}

	/**
	 * The output schema of the consent ability.
	 *
	 * @return array<string, mixed>
	 */
	private static function consent_schema(): array {
		$categories = [];

		foreach ( [ 'ad_personalization', 'ad_storage', 'ad_user_data', 'analytics_storage', 'personalization_storage', 'functionality_storage', 'security_storage' ] as $category ) {
			$categories[ $category ] = [
				'type' => 'string',
				'enum' => [ 'granted', 'denied' ],
			];
		}

		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'consent_mode_defaults_enabled' => [
					'type'        => 'boolean',
					'description' => __( 'Whether GTM Kit sets Consent Mode defaults before the container loads.', 'gtm-kit' ),
				],
				'consent_state'                 => [
					'type'                 => [ 'object', 'null' ],
					'additionalProperties' => false,
					'properties'           => $categories,
					'description'          => __( 'The Consent Mode state, per category, that the consent source in charge sets before the visitor answers, or null when no source sets one.', 'gtm-kit' ),
				],
				'consent_platform'              => [
					'type'        => [ 'string', 'null' ],
					'description' => __( 'The consent platform detected on the site, or null when none was detected. Detection cannot see a platform loaded by the theme or inside the container.', 'gtm-kit' ),
				],
				'consent_source'                => [
					'type'        => [ 'string', 'null' ],
					'description' => __( 'The id of the consent source in charge, such as gtmkit_default for GTM Kit\'s own defaults, or null when none is.', 'gtm-kit' ),
				],
				'integration_owns_consent'      => [
					'type'        => 'boolean',
					'description' => __( 'Whether a consent integration, rather than GTM Kit\'s own defaults, is in charge of consent.', 'gtm-kit' ),
				],
				'defaults_are_only_source'      => [
					'type'        => 'boolean',
					'description' => __( 'Whether nothing on the site but GTM Kit\'s defaults sets consent: no consent platform was detected and no consent integration is in charge.', 'gtm-kit' ),
				],
				'defaults_grant_analytics'      => [
					'type'        => 'boolean',
					'description' => __( 'Whether analytics storage is granted before the visitor answers.', 'gtm-kit' ),
				],
				'nothing_to_lift_defaults'      => [
					'type'        => 'boolean',
					'description' => __( 'Whether the defaults are on, deny analytics storage, and nothing on the site can lift them, so every visitor is measured without cookies.', 'gtm-kit' ),
				],
				'unknown_consent'               => [
					'type'        => 'string',
					'enum'        => [ 'allow', 'deny' ],
					'description' => __( 'How a purchase\'s contact details are treated when the page holds no Consent Mode state for advertising.', 'gtm-kit' ),
				],
			],
		];
	}

	/**
	 * The output schema of the tracking health ability.
	 *
	 * @return array<string, mixed>
	 */
	private static function tracking_health_schema(): array {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => [
				'checks'             => [
					'type'        => 'array',
					'description' => __( 'The GTM Kit Site Health results, as the site owner sees them under Tools, Site Health.', 'gtm-kit' ),
					'items'       => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => [
							'test'        => [ 'type' => 'string' ],
							'status'      => [
								'type' => 'string',
								'enum' => [ 'good', 'recommended', 'critical' ],
							],
							'label'       => [ 'type' => 'string' ],
							'description' => [ 'type' => 'string' ],
						],
					],
				],
				'google_tag_gateway' => [
					'type'                 => [ 'object', 'null' ],
					'additionalProperties' => false,
					'description'          => __( 'The stored result of the Google tag gateway checks, or null when the gateway is off.', 'gtm-kit' ),
					'properties'           => [
						'checked'           => [
							'type'        => 'boolean',
							'description' => __( 'Whether the checks have run.', 'gtm-kit' ),
						],
						'passing'           => [
							'type'        => 'boolean',
							'description' => __( 'Whether both checks passed the last time they ran.', 'gtm-kit' ),
						],
						'service_reachable' => [
							'type'        => [ 'boolean', 'null' ],
							'description' => __( 'Whether the server could reach the gateway service, or null when the checks have not run.', 'gtm-kit' ),
						],
						'address_reachable' => [
							'type'        => [ 'boolean', 'null' ],
							'description' => __( 'Whether the gateway address on the site answered, or null when the checks have not run.', 'gtm-kit' ),
						],
						'checked_at'        => [
							'type'        => [ 'integer', 'null' ],
							'description' => __( 'When the checks last ran, as a Unix timestamp, or null when they have not.', 'gtm-kit' ),
						],
					],
				],
				'snippet_scan'       => [
					'type'                 => [ 'object', 'null' ],
					'additionalProperties' => false,
					'description'          => __( 'The stored result of the daily check of a sample page, as the server sends it, or null when no check is stored. A container a consent platform injects later in the browser is not visible to it.', 'gtm-kit' ),
					'properties'           => [
						'state'                  => [
							'type' => 'string',
							'enum' => [ SnippetScan::STATE_FOUND, SnippetScan::STATE_NOT_FOUND, SnippetScan::STATE_INCONCLUSIVE ],
						],
						'reason'                 => [
							'type'        => 'string',
							'description' => __( 'Why the check was inconclusive, or an empty string.', 'gtm-kit' ),
						],
						'has_container'          => [
							'type'        => [ 'boolean', 'null' ],
							'description' => __( 'Whether tracking was found in the page. Null means the check could not tell, which is not the same as no.', 'gtm-kit' ),
						],
						'has_duplicate_tracking' => [
							'type'        => [ 'boolean', 'null' ],
							'description' => __( 'Whether tracking loads more than once. Null means the check could not tell, which is not the same as no.', 'gtm-kit' ),
						],
						'duplicate_type'         => [
							'type'        => 'string',
							'description' => __( 'How tracking is duplicated, or an empty string.', 'gtm-kit' ),
						],
						'scanned_at'             => [
							'type'        => 'integer',
							'description' => __( 'When the page was checked, as a Unix timestamp.', 'gtm-kit' ),
						],
					],
				],
			],
		];
	}
}
