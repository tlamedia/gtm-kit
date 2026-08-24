<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Common\SnippetScan;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Upgrade
 */
final class Upgrade {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Constructor
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;

		$upgrades = $this->get_upgrades();

		// Run any available upgrades.
		foreach ( $upgrades as $upgrade ) {
			$this->{$upgrade}();
		}

		\wp_cache_delete( 'gtmkit', 'options' );

		\update_option( 'gtmkit_version', GTMKIT_VERSION, false );
	}

	/**
	 * Get upgrades if applicable.
	 *
	 * @return array<string>
	 */
	protected function get_upgrades(): array {

		$available_upgrades = [
			'1.11'   => 'v111_upgrade',
			'1.14'   => 'v114_upgrade',
			'1.15'   => 'v115_upgrade',
			'1.20'   => 'v120_upgrade',
			'1.22'   => 'v122_upgrade',
			'2.2'    => 'v22_upgrade',
			'2.4'    => 'v24_upgrade',
			'2.7'    => 'v27_upgrade',
			'2.8.0'  => 'v280_upgrade',
			'2.10'   => 'v210_upgrade',
			'2.18.1' => 'v2181_upgrade',
		];

		$current_version = \get_option( 'gtmkit_version' );
		$upgrades        = [];

		foreach ( $available_upgrades as $version => $upgrade ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				$upgrades[] = $upgrade;
			}
		}

		return $upgrades;
	}

	/**
	 * Upgrade routine for v1.11
	 */
	protected function v111_upgrade(): void {

		$script_implementation = $this->options->get( 'general', 'script_implementation' );

		if ( $script_implementation === 2 ) {
			$values = [
				'general' => [
					'script_implementation' => 1,
				],
			];

			$this->options->set( $values, false, false );
		}
	}

	/**
	 * Upgrade routine for v1.14
	 */
	protected function v114_upgrade(): void {
		global $wpdb;

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'yes' WHERE option_name = 'gtmkit'" );

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'gtmkit_version'" );

		$wpdb->query( "UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'gtmkit_activation_prevent_redirect'" );

		$values = [
			'integrations' => [
				'gui-upgrade' => '',
			],
		];

		$options = $this->options->get_all_raw();

		if ( ! isset( $options['integrations']['cf7_load_js'] ) ) {
			$values['integrations']['cf7_load_js'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_shipping_info'] ) ) {
			$values['integrations']['woocommerce_shipping_info'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_payment_info'] ) ) {
			$values['integrations']['woocommerce_payment_info'] = 1;
		}
		if ( ! isset( $options['integrations']['woocommerce_variable_product_tracking'] ) ) {
			$values['integrations']['woocommerce_variable_product_tracking'] = 0;
		}

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.15
	 */
	protected function v115_upgrade(): void {

		$values = [
			'integrations' => [
				'woocommerce_view_item_list_limit' => 0,
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.20
	 */
	protected function v120_upgrade(): void {

		$values = [
			'premium' => [
				'addon_installed' => 0,
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v1.22
	 */
	protected function v122_upgrade(): void {

		$values = [
			'premium' => [
				'addon_installed' => false,
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.2
	 */
	protected function v22_upgrade(): void {
		$auto_update_plugins = (array) get_site_option( 'auto_update_plugins', [] );

		$automatic_updates = in_array( 'gtm-kit/gtm-kit.php', $auto_update_plugins, true );

		$values = [
			'misc' => [
				'auto_update' => $automatic_updates,
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.4
	 */
	protected function v24_upgrade(): void {
		$values = [
			'general' => [
				'event_inspector' => false,
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.7
	 */
	protected function v27_upgrade(): void {
		delete_transient( 'gtmkit_templates' );
	}

	/**
	 * Upgrade routine for v2.8.0
	 *
	 * Convert legacy string 'on' values to proper boolean true or integer 1.
	 * Legacy data from earlier versions stored toggle values as 'on' strings
	 * instead of proper booleans, causing integration settings to appear disabled.
	 */
	protected function v280_upgrade(): void {
		$options = $this->options->get_all_raw();
		$updated = false;

		// Settings groups to check for 'on' string values.
		$groups_to_check = [ 'general', 'integrations', 'premium', 'misc' ];

		foreach ( $groups_to_check as $group ) {
			if ( ! isset( $options[ $group ] ) || ! is_array( $options[ $group ] ) ) {
				continue;
			}

			foreach ( $options[ $group ] as $key => $value ) {
				// Convert string 'on' to boolean true.
				if ( $value === 'on' || $value === '1' ) {
					$options[ $group ][ $key ] = true;
					$updated                   = true;
				} elseif ( $value === 'off' || $value === '0' ) {
					// Convert string 'off' to boolean false.
					$options[ $group ][ $key ] = false;
					$updated                   = true;
				}
			}
		}

		// Only update if changes were made.
		if ( $updated ) {
			$this->options->set( $options, false, true );
		}
	}

	/**
	 * Upgrade routine for v2.10
	 *
	 * Seed the new `cmp_script_attributes` option for upgraders. Earlier
	 * releases hardcoded `data-cookieconsent="ignore"` on every install,
	 * so to preserve current behavior the Cookiebot toggle defaults true
	 * here regardless of which CMP plugin is active. Users who do not
	 * want the attribute can turn it off explicitly in Settings → Consent
	 * → CMP script attributes. Skipped if the option is already stored
	 * (already-migrated install or admin save before this routine ran).
	 */
	protected function v210_upgrade(): void {
		$options = $this->options->get_all_raw();
		if ( isset( $options['general']['cmp_script_attributes'] ) && is_array( $options['general']['cmp_script_attributes'] ) ) {
			return;
		}

		$values = [
			'general' => [
				'cmp_script_attributes' => [
					'cookiebot' => true,
					'iubenda'   => false,
					'cookieyes' => false,
					'custom'    => [
						'name'  => '',
						'value' => '',
					],
				],
			],
		];

		$this->options->set( $values, false, false );
	}

	/**
	 * Upgrade routine for v2.18.1
	 *
	 * 2.18.0 armed the daily scan as a one-shot Action Scheduler action, which
	 * fires once and leaves no successor. The scheduler only arms a recurrence
	 * when nothing is pending, so a site still carrying that one-shot would run
	 * one more cycle of the old behaviour before the fix took hold. Cancelling
	 * it here lets the recurrence arm on this same request, since this runs on
	 * `plugins_loaded` and the scan schedules itself on `admin_init`.
	 */
	protected function v2181_upgrade(): void {
		SnippetScan::clear_scheduled_event();
	}
}
