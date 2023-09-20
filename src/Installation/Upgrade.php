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
					'script_implementation' => '1',
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
	}
}
