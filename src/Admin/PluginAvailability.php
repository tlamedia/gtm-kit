<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * PluginAvailability
 */
final class PluginAvailability {

	/**
	 * Holds the plugins.
	 *
	 * @var array
	 */
	protected $plugins = [];

	/**
	 * Registers the plugins so we can access them.
	 *
	 * @return void
	 */
	public function register() {
		$this->seo_plugins();
		$this->conflicting_plugins();
		$this->register_plugins();
	}

	/**
	 * Supported SEO plugins.
	 *
	 * @return void
	 */
	protected function seo_plugins() {
		$this->plugins['seo'] = [
			'wordpress-seo' => [
				'url'  => 'https://wordpress.org/plugins/seo/',
				'name' => 'Yoast SEO',
				'slug' => 'wordpress-seo/wp-seo.php',
			],

			'rank-math'     => [
				'url'  => 'https://wordpress.org/plugins/seo-by-rank-math/',
				'name' => 'Rank Math SEO',
				'slug' => 'seo-by-rank-math/rank-math.php',
			],

		];
	}

	/**
	 * Plugins likely to cause conflicts.
	 *
	 * @return void
	 */
	protected function conflicting_plugins() {
		$this->plugins['conflicting'] = [
			'gtm4wp'                => [
				'name' => 'Google Tag Manager for WordPress',
				'slug' => 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager-for-wordpress.php',
			],

			'gtm-ecommerce-woo'     => [
				'name' => 'GTM for WooCommerce FREE',
				'slug' => 'gtm-ecommerce-woo/gtm-ecommerce-woo.php',
			],

			'gtm-ecommerce-woo-pro' => [
				'name' => 'Google Tag Manager for WooCommerce PRO',
				'slug' => 'gtm-ecommerce-woo-pro/gtm-ecommerce-woo-pro.php',
			],
		];
	}

	/**
	 * Registers the plugins status.
	 *
	 * @return void
	 */
	protected function register_plugins(): void {

		foreach ( $this->plugins as $category => $plugins ) {
			foreach ( $plugins as $id => $plugin ) {
				$this->plugins[ $category ][ $id ]           = $this->normalize_plugin( $plugin, $id );
				$this->plugins[ $category ][ $id ]['active'] = is_plugin_active( $plugin['slug'] );
			}
		}
	}

	/**
	 * Normalize plugin
	 *
	 * @param array  $plugin The plugin.
	 * @param string $id The plugin ID.
	 *
	 * @return array Normalized plugin.
	 */
	private function normalize_plugin( array $plugin, string $id ): array {
		$defaults = [
			'url'         => '',
			'description' => '',
		];

		$plugin = wp_parse_args( $plugin, $defaults );

		$plugin['id'] = $id;

		return $plugin;
	}

	/**
	 * Gets all the possibly available plugins.
	 *
	 * @param string $category Limit the plugins to a category.
	 *
	 * @return array Array containing the information about the plugins.
	 */
	public function get_plugins( string $category = '' ): array {
		return ( $category ) ? $this->plugins[ $category ] : $this->plugins;
	}

	/**
	 * Determines whether a plugin is active.
	 *
	 * @param array $plugin The plugin to check.
	 *
	 * @return bool Whether the plugin is active.
	 */
	public function is_active( array $plugin ): bool {
		return isset( $plugin['active'] ) && $plugin['active'] === true;
	}
}
