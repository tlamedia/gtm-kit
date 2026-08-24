<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\CMPDetection;
use TLA_Media\GTM_Kit\Common\SiteEnvironment;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * Reports GTM Kit's configuration in the WordPress Site Health screens.
 *
 * Two direct tests under Site Health → Status and one section under
 * Site Health → Info. Everything is resolved from options already loaded
 * for the request: no test performs an HTTP request, and nothing here
 * sends data anywhere.
 */
final class SiteHealth {

	/**
	 * The badge colour shared by every GTM Kit test.
	 *
	 * WordPress reserves blue for performance and purple for security, so
	 * GTM Kit's tests carry their own colour and group together in the
	 * Status list.
	 *
	 * @var string
	 */
	public const BADGE_COLOR = 'orange';

	/**
	 * Where the Tracking Health Check link lands.
	 *
	 * Calls to action go through one jump.gtmkit.com short link per surface,
	 * so each is individually attributable and its destination can be
	 * repointed without a plugin release. This surface has its own code; the
	 * URL it redirects to is set on the short link itself, not here.
	 *
	 * @var string
	 */
	private const HEALTH_CHECK_URL = 'https://jump.gtmkit.com/link/18-8E808';

	/**
	 * GTM Kit add-ons reported in the debug section.
	 *
	 * @var array<string, string>
	 */
	private const ADD_ON_PLUGINS = [
		'gtm-kit-woo/gtm-kit-woo.php'         => 'GTM Kit Woo Add-On',
		'gtm-kit-premium/gtm-kit-premium.php' => 'GTM Kit Premium',
	];

	/**
	 * Ecommerce plugins GTM Kit integrates with.
	 *
	 * @var array<string, string>
	 */
	private const ECOMMERCE_PLUGINS = [
		'woocommerce/woocommerce.php' => 'WooCommerce',
		'easy-digital-downloads/easy-digital-downloads.php' => 'Easy Digital Downloads',
		'easy-digital-downloads-pro/easy-digital-downloads.php' => 'Easy Digital Downloads Pro',
	];

	/**
	 * Form plugins GTM Kit integrates with.
	 *
	 * @var array<string, string>
	 */
	private const FORM_PLUGINS = [
		'contact-form-7/wp-contact-form-7.php' => 'Contact Form 7',
		'gravityforms/gravityforms.php'        => 'Gravity Forms',
	];

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Utility.
	 *
	 * @var Util
	 */
	private Util $util;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Register the Site Health integration.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 *
	 * @return void
	 */
	public static function register( Options $options, Util $util ): void {
		$site_health = new self( $options, $util );

		add_filter( 'site_status_tests', [ $site_health, 'add_status_tests' ] );
		add_filter( 'debug_information', [ $site_health, 'add_debug_information' ] );
	}

	/**
	 * Add GTM Kit's tests to the Site Health status screen.
	 *
	 * Every test is `direct`: they read options and the active-plugin list,
	 * so none of them needs the asynchronous queue.
	 *
	 * @param array<string, array<string, mixed>> $tests The registered Site Health tests.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_status_tests( array $tests ): array {

		$gtmkit_tests = [
			'gtmkit_container' => [
				'label' => __( 'Google Tag Manager container', 'gtm-kit' ),
				'test'  => [ $this, 'test_container' ],
			],
			'gtmkit_consent'   => [
				'label' => __( 'Consent configuration', 'gtm-kit' ),
				'test'  => [ $this, 'test_consent' ],
			],
		];

		/**
		 * Filters the Site Health tests GTM Kit registers.
		 *
		 * The extension point for GTM Kit add-ons. An add-on adds its tests
		 * here instead of hooking `site_status_tests` itself, so every GTM
		 * Kit test carries the same badge, the same severity vocabulary and
		 * the same link handling, and the whole group stays together in the
		 * Status list however many add-ons are active.
		 *
		 * Each entry is keyed by its test id and holds a `label` and a `test`
		 * callback, the shape WordPress expects for a direct test. The
		 * callback must return the array built by
		 * {@see SiteHealth::build_result()}, whose `$status` is one of `good`,
		 * `recommended` or `critical`, and should build any links with
		 * {@see SiteHealth::action_link()}. Tests registered here run
		 * synchronously on the Status screen, so a test that needs a remote
		 * call belongs in the `async` group and must be registered on
		 * `site_status_tests` directly.
		 *
		 * @param array<string, array<string, mixed>> $gtmkit_tests Test id => test definition.
		 * @param SiteHealth                          $site_health The instance, for the result and link builders.
		 */
		$gtmkit_tests = (array) apply_filters( 'gtmkit_site_health_tests', $gtmkit_tests, $this );

		foreach ( $gtmkit_tests as $test_id => $test ) {
			if ( ! is_array( $test ) || ! isset( $test['label'], $test['test'] ) ) {
				continue;
			}

			$tests['direct'][ $test_id ] = $test;
		}

		return $tests;
	}

	/**
	 * Add the GTM Kit section to the Site Health info screen.
	 *
	 * @param array<string, array<string, mixed>> $info The debug information sections.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function add_debug_information( array $info ): array {

		$info['gtmkit'] = [
			'label'       => __( 'GTM Kit', 'gtm-kit' ),
			'description' => __( 'The GTM Kit configuration of this site. Copy this section into a support request to save a round of questions.', 'gtm-kit' ),
			'show_count'  => true,
			'fields'      => $this->get_debug_fields(),
		];

		return $info;
	}

	/**
	 * Assemble the fields of the debug information section.
	 *
	 * Everything is read from options and the active-plugin list, and
	 * rendered locally. The container environment values are marked private
	 * so they stay out of the copied report.
	 *
	 * @return array<string, array<string, mixed>> The Site Health fields.
	 */
	private function get_debug_fields(): array {

		Util::load_plugin_api();

		$gtm_id      = (string) $this->options->get( 'general', 'gtm_id' );
		$sgtm_domain = (string) $this->options->get( 'general', 'sgtm_domain' );
		$gtm_auth    = (string) $this->options->get( 'general', 'gtm_auth' );
		$gtm_preview = (string) $this->options->get( 'general', 'gtm_preview' );
		$cmp         = CMPDetection::detect_active_cmp();

		$not_set = __( 'Not set', 'gtm-kit' );
		$none    = __( 'None', 'gtm-kit' );

		$fields = [
			'version'                 => [
				'label' => __( 'Plugin version', 'gtm-kit' ),
				'value' => GTMKIT_VERSION,
			],
			'container_id'            => [
				'label' => __( 'Container ID', 'gtm-kit' ),
				'value' => ( '' !== $gtm_id ) ? $gtm_id : $not_set,
			],
			'container_output'        => [
				'label' => __( 'Container added to pages by GTM Kit', 'gtm-kit' ),
				'value' => $this->get_container_output_label(),
			],
			'script_implementation'   => [
				'label' => __( 'Container code implementation', 'gtm-kit' ),
				'value' => $this->get_script_implementation_label(),
			],
			'noscript_implementation' => [
				'label' => __( 'Noscript implementation', 'gtm-kit' ),
				'value' => $this->get_noscript_implementation_label(),
			],
		];

		if ( '' !== $sgtm_domain ) {
			$fields['sgtm_domain'] = [
				'label' => __( 'Server container domain', 'gtm-kit' ),
				'value' => $sgtm_domain,
			];
		}

		$fields['container_environment'] = [
			'label' => __( 'Container environment', 'gtm-kit' ),
			'value' => ( '' !== $gtm_auth || '' !== $gtm_preview )
				? __( 'Configured', 'gtm-kit' )
				: __( 'Not configured', 'gtm-kit' ),
		];

		if ( '' !== $gtm_auth ) {
			$fields['gtm_auth'] = [
				'label'   => __( 'Environment authentication token', 'gtm-kit' ),
				'value'   => $gtm_auth,
				'private' => true,
			];
		}

		if ( '' !== $gtm_preview ) {
			$fields['gtm_preview'] = [
				'label'   => __( 'Environment preview ID', 'gtm-kit' ),
				'value'   => $gtm_preview,
				'private' => true,
			];
		}

		$excluded_url_patterns = $this->options->get( 'general', 'excluded_url_patterns' );
		$excluded_user_roles   = $this->options->get( 'general', 'exclude_user_roles' );

		$fields['consent_mode_defaults'] = [
			'label' => __( 'Consent Mode defaults', 'gtm-kit' ),
			'value' => $this->options->get( 'general', 'gcm_default_settings' )
				? __( 'Enabled', 'gtm-kit' )
				: __( 'Disabled', 'gtm-kit' ),
		];

		$fields['consent_gating_mode'] = [
			'label' => __( 'Consent gating mode', 'gtm-kit' ),
			'value' => $this->get_gating_mode_label(),
		];

		$fields['cmp'] = [
			'label' => __( 'Detected consent platform', 'gtm-kit' ),
			'value' => ( null !== $cmp ) ? CMPDetection::get_display_name( $cmp ) : __( 'None detected', 'gtm-kit' ),
		];

		$fields['excluded_url_patterns'] = [
			'label' => __( 'Excluded URL patterns', 'gtm-kit' ),
			'value' => (string) ( is_array( $excluded_url_patterns ) ? count( $excluded_url_patterns ) : 0 ),
		];

		$fields['excluded_user_roles'] = [
			'label' => __( 'Excluded user roles', 'gtm-kit' ),
			'value' => ( is_array( $excluded_user_roles ) && [] !== $excluded_user_roles )
				? implode( ', ', array_map( 'strval', $excluded_user_roles ) )
				: $none,
		];

		$add_ons     = $this->get_plugin_versions( self::ADD_ON_PLUGINS );
		$ecommerce   = $this->get_plugin_versions( self::ECOMMERCE_PLUGINS );
		$form_plugin = $this->get_plugin_versions( self::FORM_PLUGINS );

		$fields['add_ons'] = [
			'label' => __( 'GTM Kit add-ons', 'gtm-kit' ),
			'value' => ( [] !== $add_ons ) ? implode( ', ', $add_ons ) : $none,
		];

		$fields['ecommerce'] = [
			'label' => __( 'Ecommerce plugins', 'gtm-kit' ),
			'value' => ( [] !== $ecommerce ) ? implode( ', ', $ecommerce ) : __( 'None detected', 'gtm-kit' ),
		];

		$fields['forms'] = [
			'label' => __( 'Form plugins', 'gtm-kit' ),
			'value' => ( [] !== $form_plugin ) ? implode( ', ', $form_plugin ) : __( 'None detected', 'gtm-kit' ),
		];

		$fields['environment_type'] = [
			'label' => __( 'Environment type', 'gtm-kit' ),
			'value' => SiteEnvironment::get_type(),
		];

		$fields['multisite'] = [
			'label' => __( 'Multisite', 'gtm-kit' ),
			'value' => is_multisite() ? __( 'Yes', 'gtm-kit' ) : __( 'No', 'gtm-kit' ),
		];

		return $fields;
	}

	/**
	 * Describe whether GTM Kit adds the container to the site's pages.
	 *
	 * @return string The value for the debug field.
	 */
	private function get_container_output_label(): string {

		if ( ! $this->options->get( 'general', 'container_active' ) ) {
			return __( 'No', 'gtm-kit' );
		}

		if ( ! apply_filters( 'gtmkit_container_active', true ) ) {
			return __( 'No, switched off by a filter', 'gtm-kit' );
		}

		if ( SiteEnvironment::suppresses_container( $this->options ) ) {
			return sprintf(
				/* translators: %s is the kind of site WordPress reports, for example "staging". */
				__( 'No, WordPress reports this site as %s', 'gtm-kit' ),
				SiteEnvironment::get_type()
			);
		}

		return __( 'Yes', 'gtm-kit' );
	}

	/**
	 * Describe how the container script is loaded.
	 *
	 * @return string The value for the debug field.
	 */
	private function get_script_implementation_label(): string {

		if ( (int) $this->options->get( 'general', 'script_implementation' ) > 0 ) {
			return __( 'Load when the browser is idle', 'gtm-kit' );
		}

		return __( 'Standard', 'gtm-kit' );
	}

	/**
	 * Describe where the noscript container code is placed.
	 *
	 * @return string The value for the debug field.
	 */
	private function get_noscript_implementation_label(): string {

		$labels = [
			0 => __( 'After the opening body tag', 'gtm-kit' ),
			1 => __( 'Page footer', 'gtm-kit' ),
			2 => __( 'Custom, inserted from the theme', 'gtm-kit' ),
			3 => __( 'Disabled', 'gtm-kit' ),
		];

		$value = (int) $this->options->get( 'general', 'noscript_implementation' );

		return $labels[ $value ] ?? (string) $value;
	}

	/**
	 * Describe the consent gating mode.
	 *
	 * @return string The value for the debug field.
	 */
	private function get_gating_mode_label(): string {

		$labels = [
			OptionSchema::GATING_MODE_ALWAYS_LOAD  => __( 'Always load', 'gtm-kit' ),
			OptionSchema::GATING_MODE_WEAK_BLOCK   => __( 'Weak block', 'gtm-kit' ),
			OptionSchema::GATING_MODE_STRONG_BLOCK => __( 'Strong block', 'gtm-kit' ),
		];

		$value = (string) $this->options->get( 'general', 'consent_gating_mode' );

		return $labels[ $value ] ?? $value;
	}

	/**
	 * Resolve the active plugins of a group to `Name version` strings.
	 *
	 * Versions come from the same lookup the shared site data uses, so a
	 * version reported here matches the one support already sees.
	 *
	 * @param array<string, string> $plugins Plugin file path => display name.
	 *
	 * @return array<int, string> One entry per active plugin.
	 */
	private function get_plugin_versions( array $plugins ): array {

		$detected = [];

		foreach ( $plugins as $plugin_file => $name ) {
			$data = $this->util->add_active_plugin_and_version( $plugin_file, $name, [], false );

			if ( isset( $data[ $name ] ) ) {
				$detected[] = $name . ' ' . $data[ $name ];
			}
		}

		return $detected;
	}

	/**
	 * Test the container configuration.
	 *
	 * Reports what GTM Kit itself does with the container: whether an ID is
	 * saved and whether GTM Kit outputs the container snippet. Whether a
	 * container actually loads in the browser is a wider question than this
	 * test answers, since another component can load it instead.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function test_container(): array {

		$gtm_id        = (string) $this->options->get( 'general', 'gtm_id' );
		$option_active = (bool) $this->options->get( 'general', 'container_active' );
		$filter_active = (bool) apply_filters( 'gtmkit_container_active', true );

		$settings_link = $this->action_link(
			$this->util->get_admin_page_url() . 'general#/container',
			__( 'Go to the container settings', 'gtm-kit' )
		);

		if ( '' !== $gtm_id && $option_active && $filter_active && SiteEnvironment::suppresses_container( $this->options ) ) {
			// Everything is configured to load the container; the site itself
			// says this is not a place where it should. That is a correct
			// outcome, so it is reported as one rather than as a fault.
			return $this->build_result(
				'gtmkit_container',
				__( 'Your container is left out of this site on purpose', 'gtm-kit' ),
				'good',
				'<p>' . sprintf(
					/* translators: %1$s is the Google Tag Manager container ID, %2$s is the kind of site WordPress reports, for example "staging". */
					esc_html__( 'Container %1$s is saved, but WordPress reports this site as %2$s, so GTM Kit leaves the container out of its pages and traffic from here stays out of your analytics and advertising audiences.', 'gtm-kit' ),
					'<code>' . esc_html( $gtm_id ) . '</code>',
					'<code>' . esc_html( SiteEnvironment::get_type() ) . '</code>'
				) . '</p>'
				. '<p>' . sprintf(
					/* translators: %s is the name of the setting, in quotation marks. */
					esc_html__( 'The data layer is still built, so you can see what a live site would send. If you measure this site on purpose, switch on %s.', 'gtm-kit' ),
					'&#8220;' . esc_html__( 'Load the container on staging and test sites', 'gtm-kit' ) . '&#8221;'
				) . '</p>',
				$settings_link
			);
		}

		if ( '' !== $gtm_id && $option_active && $filter_active ) {
			return $this->build_result(
				'gtmkit_container',
				__( 'GTM Kit loads your Google Tag Manager container', 'gtm-kit' ),
				'good',
				'<p>' . sprintf(
					/* translators: %s is the Google Tag Manager container ID. */
					esc_html__( 'Container %s is saved and GTM Kit adds its snippet to your pages.', 'gtm-kit' ),
					'<code>' . esc_html( $gtm_id ) . '</code>'
				) . '</p>'
				. '<p>' . esc_html__( 'What the container does once it loads, such as which tags fire and how consent reaches them, is beyond what this screen can see. The Tracking Health Check loads one of your pages and reports on that.', 'gtm-kit' ) . '</p>',
				$this->action_link( self::HEALTH_CHECK_URL, __( 'Run the Tracking Health Check', 'gtm-kit' ), true )
			);
		}

		$description = '';

		if ( '' === $gtm_id ) {
			$label       = __( 'No Google Tag Manager container ID is saved', 'gtm-kit' );
			$status      = 'critical';
			$description = '<p>' . esc_html__( 'GTM Kit has no container ID to work with, so nothing is measured on this site. Add the ID of your Google Tag Manager container to start collecting data.', 'gtm-kit' ) . '</p>';

			$settings_link .= $this->documentation_link(
				'run-the-setup-wizard',
				'container-setup',
				__( 'Read how to set up your container', 'gtm-kit' )
			);
		} else {
			$label  = __( 'GTM Kit does not add the container to your pages', 'gtm-kit' );
			$status = 'recommended';

			if ( ! $option_active ) {
				$description = '<p>' . sprintf(
					/* translators: %1$s is the Google Tag Manager container ID, %2$s is the name of the setting, in quotation marks. */
					esc_html__( 'Container %1$s is saved, but the %2$s setting is switched off, so GTM Kit does not output the container snippet.', 'gtm-kit' ),
					'<code>' . esc_html( $gtm_id ) . '</code>',
					'&#8220;' . esc_html__( 'Inject Container Code', 'gtm-kit' ) . '&#8221;'
				) . '</p>';
			} else {
				$description = '<p>' . sprintf(
					/* translators: %1$s is the Google Tag Manager container ID, %2$s is a filter name. */
					esc_html__( 'Container %1$s is saved, but custom code on this site switches container output off through the %2$s filter, so GTM Kit does not output the container snippet.', 'gtm-kit' ),
					'<code>' . esc_html( $gtm_id ) . '</code>',
					'<code>gtmkit_container_active</code>'
				) . '</p>';
			}

			$description .= '<p>' . esc_html__( 'That is a valid setup when something else loads the container for you, for example a consent platform, a caching plugin or your theme. If nothing else loads it, switch container output back on.', 'gtm-kit' ) . '</p>';

			$settings_link .= $this->documentation_link(
				'disable-container-injection',
				'container-injection',
				__( 'Read when switching container output off makes sense', 'gtm-kit' )
			);
		}

		$environment = SiteEnvironment::get_type();

		if ( in_array( $environment, [ 'local', 'development' ], true ) ) {
			// A local or development install is expected to be incomplete, so
			// the finding is worth showing but never worth flagging as urgent.
			$status       = 'recommended';
			$description .= '<p>' . sprintf(
				/* translators: %s is the WordPress environment type, for example "local" or "development". */
				esc_html__( 'This site reports its environment type as %s, where an unfinished measurement setup is normal.', 'gtm-kit' ),
				'<code>' . esc_html( $environment ) . '</code>'
			) . '</p>';
		}

		return $this->build_result( 'gtmkit_container', $label, $status, $description, $settings_link );
	}

	/**
	 * Test the consent configuration.
	 *
	 * Consent belongs to whoever owns it on the site: GTM Kit's own Consent
	 * Mode defaults, or a consent platform running alongside it. The test
	 * reports which of the two is in place and never treats the answer as an
	 * error, because how a site handles consent is the site owner's decision.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function test_consent(): array {

		$defaults = (bool) $this->options->get( 'general', 'gcm_default_settings' );
		$cmp      = CMPDetection::detect_active_cmp();
		$cmp_name = CMPDetection::get_display_name( $cmp );

		$settings_link = $this->action_link(
			$this->util->get_admin_page_url() . 'general#/consent',
			__( 'Go to the consent settings', 'gtm-kit' )
		);

		if ( $defaults && '' !== $cmp_name ) {
			return $this->build_result(
				'gtmkit_consent',
				__( 'Consent is configured', 'gtm-kit' ),
				'good',
				'<p>' . sprintf(
					/* translators: %s is the name of the detected consent platform. */
					esc_html__( 'GTM Kit sets Consent Mode defaults before your container loads, and %s is active to collect and update the visitor\'s choice.', 'gtm-kit' ),
					'<strong>' . esc_html( $cmp_name ) . '</strong>'
				) . '</p>'
			);
		}

		if ( $defaults ) {
			return $this->build_result(
				'gtmkit_consent',
				__( 'Consent is configured', 'gtm-kit' ),
				'good',
				'<p>' . esc_html__( 'GTM Kit sets Consent Mode defaults before your container loads, so Google tags know what they may do until the visitor makes a choice.', 'gtm-kit' ) . '</p>'
			);
		}

		if ( '' !== $cmp_name ) {
			return $this->build_result(
				'gtmkit_consent',
				__( 'Consent is configured', 'gtm-kit' ),
				'good',
				'<p>' . sprintf(
					/* translators: %s is the name of the detected consent platform. */
					esc_html__( '%s is active on this site and owns consent, so GTM Kit leaves the Consent Mode defaults to it.', 'gtm-kit' ),
					'<strong>' . esc_html( $cmp_name ) . '</strong>'
				) . '</p>'
				. '<p>' . esc_html__( 'If your consent platform does not set the defaults itself, you can switch on GTM Kit\'s Consent Mode defaults instead.', 'gtm-kit' ) . '</p>',
				$this->documentation_link(
					'coexist-with-a-cmp',
					'cmp-coexistence',
					__( 'Read how GTM Kit works alongside a consent platform', 'gtm-kit' )
				)
			);
		}

		return $this->build_result(
			'gtmkit_consent',
			__( 'Consent is not configured', 'gtm-kit' ),
			'recommended',
			'<p>' . esc_html__( 'GTM Kit\'s Consent Mode defaults are switched off and no supported consent platform was found. Without defaults, Google tags start out with no instructions about what they may store, which is a problem wherever consent is required before measurement.', 'gtm-kit' ) . '</p>'
			. '<p>' . esc_html__( 'Switch on the Consent Mode defaults and set each category to what should apply before the visitor answers, or run a consent platform that sets them for you.', 'gtm-kit' ) . '</p>',
			$settings_link
			. $this->documentation_link(
				'turn-on-google-consent-mode-v2-defaults',
				'consent-defaults',
				__( 'Read how to switch on the Consent Mode defaults', 'gtm-kit' )
			)
			. $this->documentation_link(
				'consent-default-must-run-before-the-container',
				'consent-order',
				__( 'Read why the defaults must run before the container', 'gtm-kit' )
			)
		);
	}

	/**
	 * Build a Site Health result carrying the GTM Kit badge.
	 *
	 * @param string $test_id The test id, returned to Site Health as `test`.
	 * @param string $label The one-line result headline.
	 * @param string $status One of `good`, `recommended` or `critical`.
	 * @param string $description The result body, as one or more HTML paragraphs.
	 * @param string $actions The result's links, as HTML, or an empty string.
	 *
	 * @return array<string, mixed> The Site Health result.
	 */
	public function build_result( string $test_id, string $label, string $status, string $description, string $actions = '' ): array {
		return [
			'label'       => $label,
			'status'      => $status,
			'badge'       => [
				'label' => __( 'GTM Kit', 'gtm-kit' ),
				'color' => self::BADGE_COLOR,
			],
			'description' => $description,
			'actions'     => $actions,
			'test'        => $test_id,
		];
	}

	/**
	 * Build a link to a documentation page.
	 *
	 * Documentation is cited as evidence rather than used as a conversion
	 * target, so these are direct URLs carrying the plugin's campaign
	 * fragment, not short links.
	 *
	 * @param string $slug The documentation page slug.
	 * @param string $term The campaign term identifying the topic.
	 * @param string $label The link text.
	 *
	 * @return string The paragraph-wrapped anchor markup.
	 */
	public function documentation_link( string $slug, string $term, string $label ): string {

		$url = 'https://gtmkit.com/documentation/' . $slug . '/#utm_source=gtmkit-plugin&utm_medium=software&utm_term=' . $term . '&utm_content=site-health';

		return $this->action_link( $url, $label, true );
	}

	/**
	 * Build a result's action link.
	 *
	 * @param string $url The link target.
	 * @param string $label The link text.
	 * @param bool   $external Whether the link leaves the admin.
	 *
	 * @return string The paragraph-wrapped anchor markup.
	 */
	public function action_link( string $url, string $label, bool $external = false ): string {

		$anchor = sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$external ? ' target="_blank" rel="noopener noreferrer"' : '',
			esc_html( $label )
		);

		if ( $external ) {
			$anchor .= ' <span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'gtm-kit' ) . '</span><span aria-hidden="true" class="dashicons dashicons-external"></span>';
		}

		return '<p>' . $anchor . '</p>';
	}
}
