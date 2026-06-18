<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\ContactForm7Conditional;
use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;

/**
 * Class for common integrations.
 */
final class Integrations {

	/**
	 * Get the integrations
	 *
	 * @return array<string, array<string, string>>
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

	/**
	 * Get the active state of the integration plugins.
	 *
	 * Routed through the Conditional classes so detection survives
	 * renamed plugin files and mu-plugin installs.
	 *
	 * @return array<string, bool>
	 */
	public static function get_plugins(): array {
		$plugins = [
			'woocommerce' => ( new WooCommerceConditional() )->is_met(),
			'cf7'         => ( new ContactForm7Conditional() )->is_met(),
			'edd'         => ( new EasyDigitalDownloadsConditional() )->is_met(),
		];

		return apply_filters( 'gtmkit_integrations_plugins', $plugins );
	}
}
