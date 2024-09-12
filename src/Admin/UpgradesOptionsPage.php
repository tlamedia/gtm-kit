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
final class UpgradesOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'upgrades';

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
		return 'gtmkit_upgrades';
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'Upgrades', 'gtm-kit' );
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'Upgrades', 'gtm-kit' );
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
			$this->enqueue_assets( 'upgrades', 'settings' );
		}
	}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {

		$admin_url = is_network_admin() ? network_admin_url() : admin_url();

		\wp_localize_script(
			'gtmkit-' . $script_handle . '-script',
			'gtmkitSettings',
			[
				'rootId'        => 'gtmkit-settings',
				'currentPage'   => $page_slug,
				'root'          => \esc_url_raw( rest_url() ),
				'nonce'         => \wp_create_nonce( 'wp_rest' ),
				'opportunities' => $this->get_upgrade_opportunities(),
				'adminURL'      => $this->util->get_admin_page_url(),
				'settings'      => $this->options->get_all_raw(),
			]
		);
	}

	/**
	 * Get opportunities
	 *
	 * @return object
	 */
	private function get_upgrade_opportunities(): object {
		$integrations = $this->util->get_data( '/get-upgrade-opportunities', 'gtmkit_upgrade_opportunities' );

		$active_theme = wp_get_theme();

		$opportunities = [];

		if ( ! empty( $integrations['upgrades'] ) ) {
			foreach ( $integrations['upgrades'] as $add_on => $add_on_info ) {
				if ( \is_plugin_active( $add_on . '/' . $add_on . '.php' ) ) {
					$opportunities['upgrades'][ $add_on ] = [
						'name'   => $add_on_info['name'],
						'header' => __( 'The plugin is installed and activated', 'gtm-kit' ),
						'usp'    => [],
					];
				} else {
					$opportunities['upgrades'][ $add_on ] = $add_on_info;

					foreach ( $integrations['plugins'] as $plugin => $plugin_info ) {
						if ( \is_plugin_active( $plugin ) && $plugin_info['add_on'][ $add_on ] ) {
							$opportunities['plugins'][ $plugin ] = $plugin_info;
						}
					}

					foreach ( $integrations['themes'] as $theme => $theme_info ) {
						if ( $active_theme->get_template() === $theme && $theme_info['add_on'][ $add_on ] ) {
							$opportunities['theme'] = $theme_info;
							break;
						}
					}
				}
			}
		}

		return (object) $opportunities;
	}
}
