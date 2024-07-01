<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * Class for common integrations.
 */
final class Integrations {

	/**
	 * Get the integrations
	 *
	 * @return array
	 */
	public static function get_integrations(): array {

		$integrations_data = [
			'woocommerce' => [
				'title'       => 'WooCommerce',
				'option'      => 'woocommerce_integration',
				'description' => __( 'The #1 open source eCommerce platform built for WordPress', 'gtm-kit' ),
				'path'        => 'woocommerce',
				'type'        => 'core',
			],
			'cf7'         => [
				'title'       => 'Contact Form 7',
				'option'      => 'cf7_integration',
				'description' => __( 'Just another contact form plugin for WordPress. Simple but flexible', 'gtm-kit' ),
				'path'        => 'cf7',
				'type'        => 'core',
			],
			'edd'         => [
				'title'       => 'Easy Digital Downloads',
				'option'      => 'edd_integration',
				'description' => __( 'Easy way to sell Digital Products With WordPress', 'gtm-kit' ),
				'path'        => 'edd',
				'type'        => 'core',
			],
		];

		return apply_filters( 'gtmkit_integrations_data', $integrations_data );
	}
}
