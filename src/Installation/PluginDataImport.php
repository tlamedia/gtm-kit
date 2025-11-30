<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

/**
 * Class for preparing import data from other GTM plugins.
 */
class PluginDataImport {

	/**
	 * Get the data for all plugins.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {

		$first_install = (bool) \get_transient( 'gtmkit_first_install' );
		\delete_transient( 'gtmkit_first_install' );

		$plugin_data = [
			'firstInstall'            => $first_install,
			'importAvailable'         => false,
			'woocommerce_integration' => $this->is_plugin_active( 'woocommerce/woocommerce.php' ),
			'cf7_integration'         => $this->is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			'edd_integration'         => ( $this->is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || $this->is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
		];

		$plugins = [
			'gtm4wp',
			'gtm_for_woocommerce',
			'metronet_tag_manager',
			'google_analytics_and_google_tag_manager',
			'google_tag_manager',
		];

		foreach ( $plugins as $plugin ) {
			$settings = $this->get( $plugin );
			if ( ! empty( $settings ) ) {
				$plugin_data['import_data'][ $plugin ] = $settings;
				$plugin_data['importAvailable']        = true;
			}
		}

		return $plugin_data;
	}

	/**
	 * Get the data for the current plugin slug.
	 *
	 * @param string $slug The plugin slug.
	 *
	 * @return array<string, mixed>
	 */
	public function get( string $slug ): array {

		$method_name = preg_replace( '/-/', '_', \sanitize_key( "get_$slug" ) );

		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name();
		}

		return [];
	}

	/**
	 * Extract the container ID from the container script
	 *
	 * @param string $container_script The GTM container script.
	 *
	 * @return string
	 */
	private function extract_container_id( string $container_script ): string {
		$container_id = '';

		if ( preg_match( "/'GTM-\w+'/im", $container_script, $matches ) ) {
			$container_id = trim( $matches[0], "'" );
		}

		return $container_id;
	}

	/**
	 * Check if GTM4WP plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_gtm4wp(): array {

		$options = \get_option( 'gtm4wp-options' );

		if ( empty( $options ) ) {
			return [];
		}

		return [
			'name'         => 'GTM4WP',
			'general'      => [
				'gtm_id'                     => $options['gtm-code'] ?? '',
				'datalayer_name'             => $options['gtm-datalayer-variable-name'] ?? '',
				'sgtm_domain'                => $options['gtm-domain-name'] ?? '',
				'datalayer_post_type'        => $options['include-posttype'] ?? '',
				'datalayer_categories'       => $options['include-categories'] ?? '',
				'datalayer_tags'             => $options['include-tags'] ?? '',
				'datalayer_post_author_id'   => $options['include-authorid'] ?? '',
				'datalayer_post_author_name' => $options['include-author'] ?? '',
				'datalayer_post_date'        => $options['include-postdate'] ?? '',
				'datalayer_post_id'          => $options['include-postid'] ?? '',
				'datalayer_logged_in'        => $options['include-loggedin'] ?? '',
				'datalayer_user_id'          => $options['include-userid'] ?? '',
				'datalayer_user_role'        => $options['include-userrole'] ?? '',
			],
			'integrations' => [
				'woocommerce_integration'              => $options['integrate-woocommerce-track-enhanced-ecommerce'] ?? '',
				'woocommerce_brand'                    => $options['integrate-woocommerce-brand-taxonomy'] ?? '',
				'woocommerce_use_sku'                  => $options['integrate-woocommerce-remarketing-usesku'] ?? '',
				'woocommerce_google_business_vertical' => $options['integrate-woocommerce-business-vertical'] ?? '',
				'woocommerce_product_id_prefix'        => $options['integrate-woocommerce-remarketing-productidprefix'] ?? '',
				'woocommerce_exclude_tax'              => $options['integrate-woocommerce-exclude-tax'] ?? '',
				'woocommerce_exclude_shipping'         => $options['integrate-woocommerce-exclude-shipping'] ?? '',
				'woocommerce_include_customer_data'    => $options['integrate-woocommerce-order-data'] ?? '',
				'cf7_integration'                      => $options['integrate-wpcf7'] ?? '',
			],

		];
	}

	/**
	 * Check if GTM for WooCommerce plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_gtm_for_woocommerce(): array {

		$gtm_id = $this->extract_container_id( get_option( 'gtm_ecommerce_woo_gtm_snippet_head', '' ) );

		if ( empty( $gtm_id ) ) {
			return [];
		}

		return [
			'name'         => 'GTM for WooCommerce',
			'general'      => [
				'gtm_id' => $gtm_id,
			],
			'integrations' => [
				'woocommerce_integration' => 'On',
			],
		];
	}


	/**
	 * Check if Metronet Tag Manager plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_metronet_tag_manager(): array {

		$options = \get_option( 'metronet_tag_manager' );

		if ( empty( $options ) ) {
			return [];
		}

		$gtm_id = $this->extract_container_id( $options['code_head'] );

		if ( empty( $gtm_id ) ) {
			return [];
		}

		return [
			'name'    => 'Metronet Tag Manager',
			'general' => [
				'gtm_id' => $gtm_id,
			],
		];
	}

	/**
	 * Check if Google Analytics and Google Tag Manager plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_google_analytics_and_google_tag_manager(): array {

		$gtm_id          = \get_option( 'ga_tag_manager_id' );
		$use_tag_manager = \get_option( 'ga_use_tag_manager' );

		if ( empty( $gtm_id ) || empty( $use_tag_manager ) ) {
			return [];
		}

		return [
			'name'    => 'Google Analytics and Google Tag Manager',
			'general' => [
				'gtm_id' => $gtm_id,
			],
		];
	}

	/**
	 * Check if Google Tag Manager plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_google_tag_manager(): array {

		$gtm_id = \get_option( 'google_tag_manager_id' );

		if ( empty( $gtm_id ) ) {
			return [];
		}

		return [
			'name'    => 'Google Tag Manager',
			'general' => [
				'gtm_id' => $gtm_id,
			],
		];
	}

	/**
	 * Is plugin active
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 *
	 * @return bool
	 */
	private function is_plugin_active( string $plugin ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

		}

		return \is_plugin_active( $plugin );
	}
}
