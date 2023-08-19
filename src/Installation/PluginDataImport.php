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

		$pluginData = [
			'first_install'           => $firstInstall,
			'import_available'        => false,
			'woocommerce_integration' => is_plugin_active( 'woocommerce/woocommerce.php' ),
			'cf7_integration'         => is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			'edd_integration'         => ( is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
		];

		$plugins = [
			'gtm4wp',
			'gtm_for_woocommerce',
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

	/**
	 * Check if GTM4WP plugin settings are present and extract them.
	 *
	 * @return array
	 */
	private function get_gtm4wp(): array {

		$options = get_option( 'gtm4wp-options' );

		if ( empty( $options ) ) {
			return [];
		}

		return [
			'general' => [
				'gtm_id'         => $options['gtm-code'] ?? '',
				'datalayer_name' => $options['gtm-datalayer-variable-name'] ?? '',
				'sgtm_domain' => $options['gtm-domain-name'] ?? '',
				'datalayer_post_type' => $options['include-posttype'] ?? '',
				'datalayer_categories' => $options['include-categories'] ?? '',
				'datalayer_tags' => $options['include-tags'] ?? '',
				'datalayer_post_author_id' => $options['include-authorid'] ?? '',
				'datalayer_post_author_name' => $options['include-author'] ?? '',
				'datalayer_post_date' => $options['include-postdate'] ?? '',
				'datalayer_post_id' => $options['include-postid'] ?? '',
				'datalayer_logged_in' => $options['include-loggedin'] ?? '',
				'datalayer_user_id' => $options['include-userid'] ?? '',
				'datalayer_user_role' => $options['include-userrole'] ?? '',
			],
			'integrations' => [
				'woocommerce_integration'         => $options['integrate-woocommerce-track-enhanced-ecommerce'] ?? '',
				'woocommerce_brand' => $options['integrate-woocommerce-brand-taxonomy'] ?? '',
				'woocommerce_use_sku' => $options['integrate-woocommerce-remarketing-usesku'] ?? '',
				'woocommerce_google_business_vertical' => $options['integrate-woocommerce-business-vertical'] ?? '',
				'woocommerce_product_id_prefix' => $options['integrate-woocommerce-remarketing-productidprefix'] ?? '',
				'woocommerce_exclude_tax' => $options['integrate-woocommerce-exclude-tax'] ?? '',
				'woocommerce_exclude_shipping' => $options['integrate-woocommerce-exclude-shipping'] ?? '',
				'woocommerce_include_customer_data' => $options['integrate-woocommerce-order-data'] ?? '',
				'cf7_integration' => $options['integrate-wpcf7'] ?? '',
			],

		];
	}

	/**
	 * Check if GTM for WooCommerce plugin settings are present and extract them.
	 *
	 * @return array
	 */
	private function get_gtm_for_woocommerce(): array {

		$options = get_option( 'gtm_ecommerce_woo_earliest_active_at' );

		if ( empty( $options ) ) {
			return [];
		}

		return [
			'general' => [
				'gtm_id'         => $this->extract_container_id( get_option( 'gtm_ecommerce_woo_gtm_snippet_head', '' ) ),
			],
			'integrations' => [
				'woocommerce_integration'         => 'On',
			],

		];
	}

	/**
	 * Extract the container ID from the container script
	 *
	 * @param string $container_script
	 *
	 * @return string
	 */
	private function extract_container_id( string $container_script ): string {
		$container_id = '';

		if (preg_match("/'GTM-\w+'/i", $$container_script, $matches)) {
			// Remove the single quotes around the ID
			$container_id = trim($matches[0], "'");
		}

		return $container_id;
	}

}
