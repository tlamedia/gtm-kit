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
final class IntegrationsOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected $option_group = 'integrations';

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
		return 'gtmkit_integrations';
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'Integrations', 'gtm-kit' );
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'Integrations', 'gtm-kit' );
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
			$this->enqueue_assets( 'integrations', 'settings' );
		}
	}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {
		$taxonomies = get_taxonomies(
			[
				'show_ui'  => true,
				'public'   => true,
				'_builtin' => false,
			],
			'object'
		);

		$taxonomy_options = [];

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_options[] = [
				'label' => $taxonomy->label,
				'value' => $taxonomy->name,
			];
		}

		$admin_url = is_network_admin() ? network_admin_url() : admin_url();

		\wp_localize_script(
			'gtmkit-' . $script_handle . '-script',
			'gtmkitSettings',
			[
				'rootId'           => 'gtmkit-settings',
				'currentPage'      => $page_slug,
				'root'             => \esc_url_raw( rest_url() ),
				'nonce'            => \wp_create_nonce( 'wp_rest' ),
				'dashboardUrl'     => \menu_page_url( 'gtmkit_general', false ),
				'integrationsUrl'  => \menu_page_url( 'gtmkit_integrations', false ),
				'pluginInstallUrl' => $admin_url . 'plugin-install.php?tab=search&type=term&s=',
				'plugins'          => [
					'woocommerce' => \is_plugin_active( 'woocommerce/woocommerce.php' ),
					'cf7'         => \is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
					'edd'         => ( \is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ) || \is_plugin_active( 'easy-digital-downloads-pro/easy-digital-downloads.php' ) ),
				],
				'taxonomyOptions'  => $taxonomy_options,
				'settings'         => $this->options->get_all_raw(),
			]
		);
	}
}
