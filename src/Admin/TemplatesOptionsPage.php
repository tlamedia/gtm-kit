<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * IntegrationsOptionsPage
 */
final class TemplatesOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected $option_group = 'templates';

	/**
	 * Configure the options page.
	 */
	public function configure(): void {
		register_setting( $this->get_menu_slug(), $this->option_name );
	}

	/**
	 * Get the options page menu slug.
	 *
	 * @return string
	 */
	protected function get_menu_slug(): string {
		return 'gtmkit_templates';
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'GTM Templates', 'gtm-kit' );
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'GTM Templates', 'gtm-kit' );
	}

	/**
	 * Get the parent slug of the options page.
	 *
	 * @return string
	 */
	protected function get_parent_slug(): string {
		return 'gtmkit_general';
	}

	/**
	 * Enqueue admin page scripts and styles.
	 *
	 * @param string $hook Current hook.
	 */
	public function enqueue_page_assets( string $hook ): void {
		if ( \strpos( $hook, $this->get_menu_slug() ) !== false ) {
			$this->enqueue_assets( 'templates', 'settings' );
		}
	}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {

		\wp_localize_script(
			'gtmkit-' . $script_handle . '-script',
			'gtmkitSettings',
			[
				'rootId'          => 'gtmkit-settings',
				'currentPage'     => $page_slug,
				'root'            => \esc_url_raw( rest_url() ),
				'nonce'           => \wp_create_nonce( 'wp_rest' ),
				'templates'       => $this->get_templates(),
				'dashboardUrl'    => \menu_page_url( 'gtmkit_general', false ),
				'integrationsUrl' => \menu_page_url( 'gtmkit_integrations', false ),
				'templatesUrl'    => \menu_page_url( 'gtmkit_templates', false ),
				'settings'        => $this->options->get_all_raw(),
			]
		);
	}

	/**
	 * Get the templates
	 *
	 * @return array
	 */
	private function get_templates(): array {
		$transient = 'gtmkit_templates';
		$templates = get_transient( $transient );

		if ( ! WP_DEBUG && $templates !== false ) {
			return $templates;
		}

		$url = 'https://app.gtmkit.com/api/v1/get-templates';

		if ( \is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$url = add_query_arg( 'woo', 1, $url );
		}

		$url      = add_query_arg( 'woo', 1, $url );
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$json      = wp_remote_retrieve_body( $response );
		$templates = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return [];
		}

		set_transient( $transient, $templates, 12 * HOUR_IN_SECONDS );

		return $templates;
	}
}
