<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

/**
 * Class for common utilities.
 */
final class Util {

	/**
	 * Instance of RestAPIServer
	 *
	 * @var RestAPIServer
	 */
	public $rest_api_server;

	/**
	 * Constructor.
	 *
	 * @param RestAPIServer $rest_api_server Instance of RestAPIServer.
	 */
	public function __construct( RestAPIServer $rest_api_server ) {
		$this->rest_api_server = $rest_api_server;
	}

	/**
	 * Get the site data
	 *
	 * @param array $options The options.
	 *
	 * @return array
	 */
	public function get_site_data( array $options ): array {

		global $wp_version;

		$data                      = [];
		$data['options']           = $this->anonymize_options( $options );
		$data['web_server']        = $this->get_web_server();
		$data['php_version']       = $this->shorten_version( phpversion() );
		$data['wordpress_version'] = $this->shorten_version( $wp_version );
		$data['current_theme']     = \wp_get_theme()->get( 'Name' );
		$data['active_plugins']    = $this->get_active_plugins();
		$data                      = $this->add_active_plugin_and_version( 'gtm-kit/gtm-kit.php', 'gtmkit_version', $data );
		$data                      = $this->add_active_plugin_and_version( 'woocommerce/woocommerce.php', 'woocommerce_version', $data );
		$data                      = $this->add_active_plugin_and_version( 'easy-digital-downloads/easy-digital-downloads.php', 'edd_version', $data );
		$data                      = $this->add_active_plugin_and_version( 'easy-digital-downloads-pro/easy-digital-downloads.php', 'edd-pro_version', $data );
		$data['locale']            = explode( '_', get_locale() )[0];
		$data['multisite']         = \is_multisite();
		$data['shared_data']       = [
			1 => [
				'label' => __( 'Server type:', 'gtm-kit' ),
				'value' => $this->get_web_server(),
				'tag'   => 'code',
			],
			2 => [
				'label' => __( 'PHP version number:', 'gtm-kit' ),
				'value' => $this->shorten_version( phpversion() ),
				'tag'   => 'code',
			],
			3 => [
				'label' => __( 'WordPress version number:', 'gtm-kit' ),
				'value' => $this->shorten_version( $wp_version ),
				'tag'   => 'code',
			],
			4 => [
				'label' => __( 'WordPress multisite:', 'gtm-kit' ),
				'value' => ( \is_multisite() ) ? __( 'Yes', 'gtm-kit' ) : __( 'No', 'gtm-kit' ),
				'tag'   => 'code',
			],
			5 => [
				'label' => __( 'Current theme:', 'gtm-kit' ),
				'value' => \wp_get_theme()->get( 'Name' ),
				'tag'   => 'code',
			],
			6 => [
				'label' => __( 'Current site language:', 'gtm-kit' ),
				'value' => explode( '_', get_locale() )[0],
				'tag'   => 'code',
			],
			7 => [
				'label' => __( 'Active plugins:', 'gtm-kit' ),
				'value' => __( 'Plugin name and version of all active plugins', 'gtm-kit' ),
				'tag'   => 'em',
			],
			8 => [
				'label' => __( 'Anonymized GTM Kit settings:', 'gtm-kit' ),
				'value' => __( 'Which GTM Kit settings are active', 'gtm-kit' ),
				'tag'   => 'em',
			],
		];

		return $data;
	}

	/**
	 * Gets names of all active plugins.
	 *
	 * @return array An array of active plugins names.
	 */
	public function get_active_plugins(): array {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins        = [];
		$active_plugins = array_intersect_key( \get_plugins(), array_flip( array_filter( array_keys( \get_plugins() ), 'is_plugin_active' ) ) );

		foreach ( $active_plugins as $plugin ) {
			$plugins[] = $plugin['Name'];
		}

		return $plugins;
	}

	/**
	 * Add plugin to array if active.
	 *
	 * @param string $plugin The plugin slug.
	 * @param string $key The key.
	 * @param array  $data The data.
	 *
	 * @return array An array of active plugins names.
	 */
	public function add_active_plugin_and_version( string $plugin, string $key, array $data ): array {

		if ( \is_plugin_active( $plugin ) ) {
			$version      = \get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin )['Version'];
			$data[ $key ] = $this->shorten_version( $version );
		}

		return $data;
	}

	/**
	 * Anonymize options
	 *
	 * @param array $options The options.
	 *
	 * @return array
	 */
	public function anonymize_options( array $options ): array {

		unset( $options['general']['gtm_id'] );

		$anonymize_general_options = [ 'datalayer_name', 'sgtm_domain', 'sgtm_container_identifier' ];

		foreach ( $anonymize_general_options as $option ) {
			if ( ! empty( $options['general'][ $option ] ) ) {
				$options['general'][ $option ] = $option;
			}
		}

		return $options;
	}

	/**
	 * Shorten version number
	 *
	 * @param string $version The version number.
	 *
	 * @return string
	 */
	public function shorten_version( string $version ): string {
		return preg_replace( '@^(\d\.\d+).*@', '\1', $version );
	}

	/**
	 * Get web server
	 *
	 * @return string
	 */
	public function get_web_server(): string {

		global $is_nginx, $is_apache, $is_iis7, $is_IIS;

		if ( $is_nginx ) {
			$web_server = 'NGINX';
		} elseif ( $is_apache ) {
			$web_server = 'Apache';
		} elseif ( $is_iis7 ) {
			$web_server = 'IIS 7';
		} elseif ( $is_IIS ) {
			$web_server = 'IIS';
		} else {
			$web_server = 'Unknown';
		}

		return $web_server;
	}

	/**
	 * Get the plugin version
	 *
	 * @return string
	 */
	public function get_plugin_version(): string {
		return ( \wp_get_environment_type() === 'local' ) ? time() : GTMKIT_VERSION;
	}

	/**
	 * Enqueue script in build
	 *
	 * @param string $handle The script hande.
	 * @param string $script The script name.
	 *
	 * @return void
	 */
	public function enqueue_script( string $handle, string $script ): void {

		$deps_file = GTMKIT_PATH . 'build/' . $script . '.asset.php';

		$dependency = [];
		$version    = false;

		if ( file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		\wp_enqueue_script( $handle, GTMKIT_URL . 'build/' . $script . '.js', $dependency, $version, true );
	}
}
