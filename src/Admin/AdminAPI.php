<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use TLA_Media\GTM_Kit\Options;
use WP_Error;

/**
 * Class for the admin REST API.
 */
final class AdminAPI {

	/**
	 * An instance of Options.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * An instance of Util.
	 *
	 * @var Util
	 */
	private $util;

	/**
	 * Constructor
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Initialize REST
	 *
	 * @return void
	 */
	public function rest_init() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}


	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$this->util->rest_api_server->register_rest_route(
			'/get-install-data',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_install_data' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/get-options',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_options' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/set-options',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'set_options' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/get-site-data',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_site_data' ],
			]
		);

		$this->util->rest_api_server->register_rest_route(
			'/send-support-data',
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'send_support_data' ],
			]
		);
	}

	/**
	 * Permission callback
	 *
	 * @return true|WP_Error
	 */
	public function permission_callback() {
		$capability = is_multisite() ? 'manage_network_options' : 'manage_options';
		$capability = apply_filters( 'gtmkit_admin_capability', $capability );

		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'Only authenticated users can access endpoint.', 'gtm-kit' ), [ 'status' => 401 ] );
		}

		return true;
	}

	/**
	 * Get install data
	 *
	 * @return void
	 */
	public function get_install_data(): void {
		$other_plugin_settings = ( new PluginDataImport() )->get_all();
		wp_send_json_success( $other_plugin_settings );
	}

	/**
	 * Get options
	 *
	 * @return void
	 */
	public function get_options(): void {
		wp_send_json_success( $this->options->get_all_raw() );
	}

	/**
	 * Set options
	 *
	 * @return void
	 */
	public function set_options(): void {
		$new_options = json_decode( file_get_contents( 'php://input' ), true );
		$this->options->set( $new_options );

		wp_send_json_success( $this->options->get_all_raw() );
	}

	/**
	 * Get site data
	 *
	 * @return void
	 */
	public function get_site_data(): void {
		$site_data = $this->util->get_site_data( $this->options->get_all_raw() );
		wp_send_json_success( $site_data );
	}

	/**
	 * Send Support Data
	 *
	 * @return void
	 */
	public function send_support_data(): void {
		$support_ticket = strtoupper( json_decode( file_get_contents( 'php://input' ), true ) );

		$match = preg_match( '/FS(\d+)-([A-Z0-9]+)/', $support_ticket, $matches );

		if ( $match === 1 ) {

			$url = 'https://support.gtmkit.com/api/wporg/support/' . $support_ticket;

			$body = [
				'system_data' => wp_json_encode( $this->util->get_site_data( $this->options->get_all_raw(), false ) ),
			];
			$args = [
				'method'    => 'PUT',
				'headers'   => [
					'Content-Type' => 'application/json',
				],
				'body'      => wp_json_encode( $body ),
				'sslverify' => false,
			];

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
			} else {
				wp_send_json_success( __( 'Thank you! We have received the data.', 'gtm-kit' ) );
			}
		} else {
			wp_send_json_error( __( 'The support ticket was not found. Please check that you have entered the correct ticket.', 'gtm-kit' ) );
		}
	}
}
