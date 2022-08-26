<?php

namespace TLA\GTM_Kit\Installation;

use TLA\GTM_Kit\Options;

class Upgrade {

	public function __construct() {

		$upgrades = $this->get_upgrades();

		// Run any available upgrades.
		foreach ( $upgrades as $upgrade ) {
			$this->{$upgrade}();
		}

		wp_cache_delete('gtmkit', 'options');

		update_option( 'gtmkit_version', GTMKIT_VERSION );
	}

	/**
	 * Get upgrades if applicable.
	 *
	 * @return array
	 */
	protected function get_upgrades(): array {

		$available_upgrades = [
			'0.12' => 'v012_upgrade',
		];

		$current_version  = get_option( 'gtmkit_version' );
		$upgrades = [];

		foreach ( $available_upgrades as $version => $upgrade ) {
			if ( version_compare( $current_version, $version, '<' ) ) {
				$upgrades[] = $upgrade;
			}
		}

		return $upgrades;
	}

	/**
	 * Upgrade routine for v0.12
	 */
	protected function v012_upgrade(): void {

		$values = [
			'integrations' => [
				'woocommerce_integration' => Options::init()->get( 'general', 'datalayer_integration_woocommerce' ),
				'woocommerce_brand' => Options::init()->get( 'general', 'woocommerce_brand' ),
				'woocommerce_use_sku' => Options::init()->get( 'general', 'woocommerce_use_sku' ),
				'woocommerce_exclude_tax' => Options::init()->get( 'general', 'woocommerce_exclude_tax' ),
				'woocommerce_exclude_shipping' => Options::init()->get( 'general', 'woocommerce_exclude_shipping' ),
				'woocommerce_shipping_info' => Options::init()->get( 'general', 'woocommerce_shipping_info' ),
				'woocommerce_payment_info' => Options::init()->get( 'general', 'woocommerce_payment_info' ),
			],
		];

		Options::init()->set( $values, false, false );
	}

}
