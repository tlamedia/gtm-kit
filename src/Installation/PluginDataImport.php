<?php

namespace TLA_Media\GTM_Kit\Installation;

/**
 * Class for preparing import data from other GTM plugins.
 */
class PluginDataImport {

	/**
	 * Get the data for all plugins.
	 *
	 * @return array
	 */
	public function get_all(): array {

		$firstInstall = (bool) get_transient( 'gtmkit_first_install' );
		delete_transient( 'gtmkit_first_install' );

		$firstInstall = true;

		$pluginData = [
			'first_install'           => $firstInstall,
			'import_available'        => false,
			'woocommerce_integration' => is_plugin_active( 'woocommerce/woocommerce.php' ),
			'cf7_integration'         => is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			'edd_integration'         => ( is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
		];

		$plugins = [
		];

		foreach ( $plugins as $plugin ) {
			$settings = $this->get( $plugin );
			if ( ! empty( $settings ) ) {
				$pluginData[ $plugin ]          = $settings;
				$pluginData['import_available'] = true;
			}
		}

		return $pluginData;
	}

	/**
	 * Get the data for the current plugin slug.
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return array
	 */
	public function get( string $slug ): array {

		$method_name = preg_replace( '/[\-]/', '_', sanitize_key( "get_$slug" ) );

		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name();
		}

		return [];
	}
	
}
