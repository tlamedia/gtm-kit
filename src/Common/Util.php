<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Common;

use TLA_Media\GTM_Kit\Integration\WooCommerce;
use TLA_Media\GTM_Kit\Options;

/**
 * Class for common utilities.
 */
final class Util {

	/**
	 * Instance of Options
	 *
	 * @var Options
	 */
	public $options;

	/**
	 * Instance of RestAPIServer
	 *
	 * @var RestAPIServer
	 */
	public $rest_api_server;

	/**
	 * Asset path
	 *
	 * @var string
	 */
	public $asset_path;

	/**
	 * Asset URL
	 *
	 * @var string
	 */
	public $asset_url;

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	private $api_namespace = '/api/v1';

	/**
	 * API host.
	 *
	 * @var string
	 */
	private $api_host;

	/**
	 * Constructor.
	 *
	 * @param Options       $options Instance of Options.
	 * @param RestAPIServer $rest_api_server Instance of RestAPIServer.
	 * @param string        $path The plugin path.
	 * @param string        $url The plugin URL.
	 */
	public function __construct( Options $options, RestAPIServer $rest_api_server, string $path = GTMKIT_PATH, string $url = GTMKIT_URL ) {
		$this->options         = $options;
		$this->rest_api_server = $rest_api_server;
		$this->asset_path      = $path . 'assets/';
		$this->asset_url       = $url . 'assets/';

		if ( $options->is_const_enabled() && defined( 'GTMKIT_API_HOST' ) ) {
			$this->api_host = GTMKIT_API_HOST;
		} else {
			$this->api_host = 'https://app.gtmkit.com';
		}
	}

	/**
	 * Get the site data
	 *
	 * @param array $options The options.
	 * @param bool  $anonymize Anonymize the data.
	 *
	 * @return array
	 */
	public function get_site_data( array $options, bool $anonymize = true ): array {

		global $wp_version;

		$data = [];
		$data = $this->set_site_data( $data, $options, $wp_version, $anonymize );

		$plugins = [
			'gtm-kit/gtm-kit.php'         => 'gtmkit_version',
			'woocommerce/woocommerce.php' => 'woocommerce_version',
			'easy-digital-downloads/easy-digital-downloads.php' => 'edd_version',
			'easy-digital-downloads-pro/easy-digital-downloads.php' => 'edd-pro_version',
		];

		foreach ( $plugins as $plugin => $key ) {
			$data = $this->add_active_plugin_and_version( $plugin, $key, $data );
		}
		$data['locale'] = explode( '_', get_locale() )[0];
		if ( $anonymize ) {
			$data = $this->add_shared_data( $data, $wp_version );
		} else {
			$data['support_data']['site_url'] = site_url();
			if ( function_exists( 'WC' ) ) {
				$data['support_data']['pages'] = WooCommerce::instance()->get_pages_property( [] )['pages'];
			}
		}

		return $data;
	}

	/**
	 * Set the site data
	 *
	 * @param array  $data Current data.
	 * @param array  $options The options.
	 * @param string $wp_version The WordPress version.
	 * @param bool   $anonymize Anonymize the data.
	 *
	 * @return array
	 */
	private function set_site_data( array $data, array $options, string $wp_version, bool $anonymize ): array {
		$data['options']           = ( $anonymize ) ? $this->anonymize_options( $options ) : $options;
		$data['web_server']        = $this->get_web_server();
		$data['php_version']       = $this->shorten_version( phpversion() );
		$data['wordpress_version'] = $this->shorten_version( $wp_version );
		$data['current_theme']     = ( wp_get_theme()->get( 'Template' ) ) ? ucwords( wp_get_theme()->get( 'Template' ) ) : \wp_get_theme()->get( 'Name' );
		$data['active_plugins']    = $this->get_active_plugins();
		$data['multisite']         = \is_multisite();

		return $data;
	}

	/**
	 * Add shared data
	 *
	 * @param array $data Current data.
	 * @param array $wp_version The WordPress version.
	 *
	 * @return array
	 */
	private function add_shared_data( array $data, $wp_version ): array {
		$data['shared_data'] = [
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
				'value' => ( wp_get_theme()->get( 'Template' ) ) ? ucwords( wp_get_theme()->get( 'Template' ) ) : \wp_get_theme()->get( 'Name' ),
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
	 * @param bool   $has_asset_file If the script has an asset file or not.
	 * @param array  $deps The script dependencies.
	 * @param array  $args The loading strategy.
	 *
	 * @return void
	 */
	public function enqueue_script( string $handle, string $script, bool $has_asset_file = false, array $deps = [], array $args = [ 'strategy' => 'defer' ] ): void {

		$ver = $this->get_plugin_version();

		if ( $has_asset_file ) {
			$file = $this->asset_path . substr_replace( $script, '.asset.php', - strlen( '.js' ) );
			if ( file_exists( $file ) ) {
				$deps_file = require $file;
				$deps      = $deps_file['dependencies'];
				$ver       = $deps_file['version'];
			}
		}

		$deps[] = 'gtmkit';

		$container_active = ( $this->options->get( 'general', 'container_active' ) && apply_filters( 'gtmkit_container_active', true ) );

		if ( $container_active ) {
			$deps[] = 'gtmkit-container';
		}

		\wp_enqueue_script( $handle, $this->asset_url . $script, $deps, $ver, $args );
	}

	/**
	 * Get API data
	 *
	 * @param string $endpoint The API endpoint.
	 * @param string $transient The transient.
	 *
	 * @return array
	 */
	public function get_data( string $endpoint, string $transient ): array {
		$data = get_transient( $transient );

		if ( ! WP_DEBUG && $data !== false ) {
			return $data;
		}

		$url = $this->api_host . $this->api_namespace . $endpoint;

		$url = add_query_arg(
			'plugins',
			[
				'woo' => \is_plugin_active( 'woocommerce/woocommerce.php' ),
				'cf7' => \is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
				'edd' => ( \is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || \is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
			],
			$url
		);

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$json = wp_remote_retrieve_body( $response );
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}

		set_transient( $transient, $data, 12 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Normalize and hash a string.
	 *
	 * @link https://developers.google.com/google-ads/api/docs/conversions/enhanced-conversions/web#php
	 *
	 * @param string $hash_algorithm The hash algorithm.
	 * @param string $value The string to normalize and hash.
	 * @param bool   $trim_intermediate_spaces Removes leading, trailing, and intermediate spaces.
	 *
	 * @return string The normalized and hashed string.
	 */
	public function normalize_and_hash(
		string $hash_algorithm,
		string $value,
		bool $trim_intermediate_spaces
	): string {
		// Normalizes by first converting all characters to lowercase, then trimming spaces.
		$normalized = strtolower( $value );
		if ( $trim_intermediate_spaces === true ) {
			// Removes leading, trailing, and intermediate spaces.
			$normalized = str_replace( ' ', '', $normalized );
		} else {
			// Removes only leading and trailing spaces.
			$normalized = trim( $normalized );
		}

		if ( $normalized === '' ) {
			return '';
		}

		return hash( $hash_algorithm, strtolower( trim( $normalized ) ) );
	}

	/**
	 * Returns the result of normalizing and hashing an email address. For this use case, Google
	 * Ads requires removal of any '.' characters preceding "gmail.com" or "googlemail.com".
	 *
	 * @param string $hash_algorithm The hash algorithm to use.
	 * @param string $email_address The email address to normalize and hash.
	 * @return string The normalized and hashed email address.
	 */
	public function normalize_and_hash_email_address(
		string $hash_algorithm,
		string $email_address
	): string {
		$normalized_email = strtolower( $email_address );
		$email_parts      = explode( '@', $normalized_email );
		if (
			count( $email_parts ) > 1
			&& preg_match( '/^(gmail|googlemail)\.com\s*/', $email_parts[1] )
		) {
			// Removes any '.' characters from the portion of the email address before the domain
			// if the domain is gmail.com or googlemail.com.
			$email_parts[0]   = str_replace( '.', '', $email_parts[0] );
			$normalized_email = sprintf( '%s@%s', $email_parts[0], $email_parts[1] );
		}
		return $this->normalize_and_hash( $hash_algorithm, $normalized_email, true );
	}
}
