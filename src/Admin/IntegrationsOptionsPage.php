<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\ContactForm7Conditional;
use TLA_Media\GTM_Kit\Common\Conditionals\EasyDigitalDownloadsConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\PremiumPluginConditional;
use TLA_Media\GTM_Kit\Common\Conditionals\WooCommerceConditional;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * IntegrationsOptionsPage
 */
final class IntegrationsOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'integrations';

	/**
	 * Create an instance of the options page.
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 *
	 * @return AbstractOptionsPage
	 */
	protected static function create_instance( Options $options, Util $util ): AbstractOptionsPage {
		return new self( $options, $util );
	}

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
	 * @param mixed $hook Current hook.
	 */
	public function enqueue_page_assets( $hook ): void {
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
			'objects'
		);

		$taxonomy_options = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( is_object( $taxonomy ) && property_exists( $taxonomy, 'label' ) && property_exists( $taxonomy, 'name' ) ) {
				$taxonomy_options[] = [
					'label' => $taxonomy->label,
					'value' => $taxonomy->name,
				];
			}
		}

		$pages = get_pages(
			[
				'sort_column' => 'post_title',
				'sort_order'  => 'ASC',
			]
		);

		$page_options = [];

		foreach ( $pages as $page ) {
			if ( is_object( $page ) && property_exists( $page, 'post_title' ) && property_exists( $page, 'ID' ) ) {
				$page_options[] = [
					'label' => $page->post_title . ' (ID: ' . $page->ID . ')',
					'value' => (string) $page->ID,
				];
			}
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
				'isPremium'        => ( new PremiumConditional() )->is_met(),
				'isPremiumPlugin'  => ( new PremiumPluginConditional() )->is_met(),
				'integrations'     => Integrations::get_integrations(),
				'adminPageUrl'     => $this->util->get_admin_page_url(),
				'pluginInstallUrl' => $this->util->get_plugin_install_url(),
				'plugins'          => self::get_plugins(),
				'taxonomyOptions'  => $taxonomy_options,
				'pageOptions'      => $page_options,
				'settings'         => $this->options->get_all_raw(),
			]
		);
	}

	/**
	 * Get the plugin-availability map exposed to the React app under the
	 * `plugins` key in `window.gtmkitSettings`.
	 *
	 * Public so other admin pages (e.g. {@see GeneralOptionsPage}) can
	 * surface the same map without duplicating the detection logic.
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
