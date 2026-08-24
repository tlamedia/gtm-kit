<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Common\Util;

/**
 * Class for preparing import data from other GTM plugins.
 */
class PluginDataImport {

	/**
	 * Get the data for all plugins.
	 *
	 * Reading import data has no side effects, so the settings screen and
	 * repeated wizard runs can both offer an import. An import is offered
	 * whenever another plugin's settings are present, not only on a fresh
	 * install.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {

		$plugin_data = [
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
	 * Extract the configured GTM4WP containers as a normalized list.
	 *
	 * Newer GTM4WP releases store one row per container under `gtm-containers`
	 * and keep the flat `gtm-*` keys as derived mirrors. Older releases only
	 * have the flat keys, where `gtm-code` holds every container ID joined by
	 * commas or semicolons. Both shapes are reduced to the same row structure
	 * so callers never have to care which release wrote the settings.
	 *
	 * @param array<string, mixed> $options The stored GTM4WP options.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_gtm4wp_containers( array $options ): array {
		$containers = [];

		if ( ! empty( $options['gtm-containers'] ) && \is_array( $options['gtm-containers'] ) ) {
			foreach ( $options['gtm-containers'] as $row ) {
				if ( ! \is_array( $row ) || empty( $row['id'] ) || ! \is_scalar( $row['id'] ) ) {
					continue;
				}

				$containers[] = [
					'id'          => trim( (string) $row['id'] ),
					'domain'      => \is_scalar( $row['domain'] ?? '' ) ? trim( (string) ( $row['domain'] ?? '' ) ) : '',
					'gtm_auth'    => \is_scalar( $row['gtm_auth'] ?? '' ) ? trim( (string) ( $row['gtm_auth'] ?? '' ) ) : '',
					'gtm_preview' => \is_scalar( $row['gtm_preview'] ?? '' ) ? trim( (string) ( $row['gtm_preview'] ?? '' ) ) : '',
				];
			}

			if ( ! empty( $containers ) ) {
				return $containers;
			}
		}

		// The shared environment and domain of the flat keys belong to every ID.
		$ids = preg_split( '/[,;]/', (string) ( $options['gtm-code'] ?? '' ) );

		if ( false === $ids ) {
			return $containers;
		}

		foreach ( $ids as $id ) {
			$id = trim( $id );

			if ( '' === $id ) {
				continue;
			}

			$containers[] = [
				'id'          => $id,
				'domain'      => trim( (string) ( $options['gtm-domain-name'] ?? '' ) ),
				'gtm_auth'    => trim( (string) ( $options['gtm-env-gtm-auth'] ?? '' ) ),
				'gtm_preview' => trim( (string) ( $options['gtm-env-gtm-preview'] ?? '' ) ),
			];
		}

		return $containers;
	}

	/**
	 * Check if GTM4WP plugin settings are present and extract them.
	 *
	 * @return array<string, mixed>
	 */
	private function get_gtm4wp(): array {

		$options = \get_option( 'gtm4wp-options' );

		if ( empty( $options ) || ! \is_array( $options ) ) {
			return [];
		}

		$containers = $this->get_gtm4wp_containers( $options );
		$container  = $containers[0] ?? null;

		// The container fields follow the same rule as every mapped setting:
		// a value the source never stored is left out rather than imported as
		// an empty one, so an import cannot blank a working container ID,
		// server container domain or environment on the way through.
		$general = [];

		if ( $container !== null ) {
			$container_map = [
				'gtm_id'      => 'id',
				'gtm_auth'    => 'gtm_auth',
				'gtm_preview' => 'gtm_preview',
				'sgtm_domain' => 'domain',
			];

			foreach ( $container_map as $target => $source ) {
				if ( '' !== $container[ $source ] ) {
					$general[ $target ] = $container[ $source ];
				}
			}
		}

		$general_map = [
			'datalayer_name'                  => 'gtm-datalayer-variable-name',
			'datalayer_post_type'             => 'include-posttype',
			'datalayer_categories'            => 'include-categories',
			'datalayer_tags'                  => 'include-tags',
			'datalayer_post_title'            => 'include-posttitle',
			'datalayer_post_author_id'        => 'include-authorid',
			'datalayer_post_author_name'      => 'include-author',
			'datalayer_post_date'             => 'include-postdate',
			'datalayer_post_id'               => 'include-postid',
			'datalayer_logged_in'             => 'include-loggedin',
			'datalayer_user_id'               => 'include-userid',
			'datalayer_user_role'             => 'include-userrole',
			'gcm_default_settings'            => 'integrate-consent-mode',
			'gcm_ad_storage'                  => 'integrate-consent-mode-ads',
			'gcm_ad_user_data'                => 'integrate-consent-mode-ad-user-data',
			'gcm_ad_personalization'          => 'integrate-consent-mode-ad-perso',
			'gcm_analytics_storage'           => 'integrate-consent-mode-analytics',
			'gcm_personalization_storage'     => 'integrate-consent-mode-perso',
			'gcm_functionality_storage'       => 'integrate-consent-mode-func',
			'gcm_security_storage'            => 'integrate-consent-mode-security',
			'engagement_event_login_enabled'  => 'event-user-logged-in',
			'engagement_event_signup_enabled' => 'event-new-user-registration',
		];

		$integrations_map = [
			'woocommerce_integration'              => 'integrate-woocommerce-track-enhanced-ecommerce',
			'woocommerce_brand'                    => 'integrate-woocommerce-brand-taxonomy',
			'woocommerce_use_sku'                  => 'integrate-woocommerce-remarketing-usesku',
			'woocommerce_google_business_vertical' => 'integrate-woocommerce-business-vertical',
			'woocommerce_product_id_prefix'        => 'integrate-woocommerce-remarketing-productidprefix',
			'woocommerce_exclude_tax'              => 'integrate-woocommerce-exclude-tax',
			'woocommerce_exclude_shipping'         => 'integrate-woocommerce-exclude-shipping',
			'cf7_integration'                      => 'integrate-wpcf7',
		];

		$general = array_merge( $general, $this->pick( $options, $general_map ) );

		if ( \array_key_exists( 'gtm-no-gtm-for-logged-in', $options ) ) {
			$general['exclude_user_roles'] = $this->split_role_list( $options['gtm-no-gtm-for-logged-in'] );
		}

		$integrations = $this->pick( $options, $integrations_map );

		// GTM4WP's own "customer data in data layer" toggle is the counterpart;
		// the order-data toggle is the pre-2.0 approximation kept as a fallback.
		$customer_data = $this->pick(
			$options,
			[ 'woocommerce_include_customer_data' => 'integrate-woocommerce-customer-data' ]
		);

		if ( [] === $customer_data ) {
			$customer_data = $this->pick(
				$options,
				[ 'woocommerce_include_customer_data' => 'integrate-woocommerce-order-data' ]
			);
		}

		return [
			'name'            => 'GTM4WP',
			'container_count' => \count( $containers ),
			'general'         => $general,
			'integrations'    => array_merge( $integrations, $customer_data ),
		];
	}

	/**
	 * Copy the source options a map names onto their GTM Kit keys.
	 *
	 * A source key that is absent is skipped rather than imported as an empty
	 * value, so an import only overwrites settings the other plugin actually
	 * stored and the confirmation step can list exactly what will change.
	 *
	 * @param array<string, mixed>  $options The source plugin's options.
	 * @param array<string, string> $map GTM Kit key => source option key.
	 *
	 * @return array<string, mixed>
	 */
	private function pick( array $options, array $map ): array {
		$picked = [];

		foreach ( $map as $target => $source ) {
			if ( \array_key_exists( $source, $options ) ) {
				$picked[ $target ] = $options[ $source ];
			}
		}

		return $picked;
	}

	/**
	 * Convert GTM4WP's comma separated user role list into an array of role slugs.
	 *
	 * @param mixed $roles The stored role list.
	 *
	 * @return string[]
	 */
	private function split_role_list( $roles ): array {
		if ( \is_array( $roles ) ) {
			$roles = implode( ',', array_filter( $roles, 'is_scalar' ) );
		}

		if ( ! \is_scalar( $roles ) ) {
			return [];
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $roles ) ) ) );
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
				'woocommerce_integration' => true,
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
		Util::load_plugin_api();

		return \is_plugin_active( $plugin );
	}
}
