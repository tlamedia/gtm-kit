<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Options;

/**
 * Upgrade
 */
final class Upgrade {

	/**
	 * Constructor
	 */
	public function __construct() {

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
	 * @return array
	 */
	protected function get_upgrades(): array {

		$available_upgrades = [
			'1.11' => 'v111_upgrade',
			'1.14' => 'v114_upgrade',
			'1.15' => 'v115_upgrade',
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

		$script_implementation = Options::init()->get( 'general', 'script_implementation' );

		if ( $script_implementation === 2 ) {
			$values = [
				'general' => [
					'script_implementation' => 1,
				],
			];

			Options::init()->set( $values, false, false );
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

		$options = Options::init()->get_all_raw();

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

		Options::init()->set( $values, false, false );
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

		Options::init()->set( $values, false, false );
	}
}
