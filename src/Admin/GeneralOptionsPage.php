<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Conditionals\PremiumConditional;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * GeneralOptionsPage
 */
final class GeneralOptionsPage extends AbstractOptionsPage {

	/**
	 * The option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'general';

	/**
	 * The notifications
	 *
	 * @var array<string, array<string, int|array<string>>|int>
	 */
	protected array $notifications = [];

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
	 * Adds the admin page to the menu.
	 */
	public function add_admin_page(): void {
		add_menu_page(
			$this->get_page_title(),
			$this->get_main_menu_title(),
			$this->get_capability(),
			$this->get_menu_slug(),
			[ $this, 'render' ],
			'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjOWVhM2E4IiBoZWlnaHQ9IjY0IiB2aWV3Qm94PSIwIDAgNDIgMjQiIHdpZHRoPSI2NCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJtMzguNTE2IDEuMjc5aC0yMi45MTRjLTEuMzU3IDAtMi41MDMtLjEtNC4yOTQgMS4zOTJsLTguNzE4IDYuODM2Yy0yLjExNCAxLjc2NS0yLjEyNSAzLjIxNyAwIDQuOTg2bDguNzE4IDYuODM2YzEuNjk5IDEuNDIgMi45MyAxLjM5MyA0LjI5NCAxLjM5M2g3LjI5NSAxNS42MTljMS4zNjQtLjAzMiAyLjUxLS45NTcgMi40ODQtMi4xMDR2LTE3LjI2N2MtLjAwNi0xLjE0Ni0xLjEyLTIuMDcyLTIuNDg0LTIuMDcyeiIgdHJhbnNmb3JtPSJtYXRyaXgoLTEgMCAwIC0xIDQyLjAwMDgwNiAyMy45OTk2MzkpIi8+PC9zdmc+'
		);

		add_submenu_page(
			$this->get_parent_slug(),
			$this->get_page_title(),
			$this->get_menu_title(),
			$this->get_capability(),
			$this->get_menu_slug(),
			[ $this, 'render' ]
		);
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
		return 'gtmkit_general';
	}

	/**
	 * Get the main admin page menu title.
	 *
	 * @return string
	 */
	protected function get_main_menu_title(): string {
		return 'GTM Kit' . $this->get_notification_counter();
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'General', 'gtm-kit' ) . $this->get_notification_counter();
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'General Settings', 'gtm-kit' );
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
			$this->enqueue_assets( 'general', 'settings' );
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
				'rootId'        => 'gtmkit-settings',
				'currentPage'   => $page_slug,
				'root'          => \esc_url_raw( rest_url() ),
				'nonce'         => \wp_create_nonce( 'wp_rest' ),
				'pluginUrl'     => GTMKIT_URL,
				'isPremium'     => ( new PremiumConditional() )->is_met(),
				'tutorials'     => $this->get_tutorials(),
				'integrations'  => Integrations::get_integrations(),
				'adminPageUrl'  => $this->util->get_admin_page_url(),
				'settings'      => $this->options->get_all_raw(),
				'site_data'     => $this->util->get_site_data( $this->options->get_all_raw() ),
				'user_roles'    => $this->get_user_roles(),
				'notifications' => $this->get_notifications(),
			]
		);
	}

	/**
	 * Get the tutorials
	 *
	 * @return array<string, mixed>
	 */
	private function get_tutorials(): array {
		return $this->util->get_data( '/get-tutorials', 'gtmkit_tutorials' );
	}

	/**
	 * Get user roles
	 *
	 * @return array<array<string, string>>
	 */
	private function get_user_roles(): array {

		$user_roles = [];
		$roles      = get_editable_roles();

		foreach ( $roles as $role_id => $role_info ) {
			$user_roles[] = [
				'role' => $role_id,
				'name' => translate_user_role( $role_info['name'] ),
			];
		}

		return $user_roles;
	}

	/**
	 * Get the notifications array
	 *
	 * @return array<string, array<string, int|array<string>>|int>
	 */
	private function get_notifications_array(): array {
		if ( empty( $this->notifications ) ) {
			$notifications_handler = NotificationsHandler::get();
			$this->notifications   = $notifications_handler->get_notifications_array();
		}

		return $this->notifications;
	}

	/**
	 * Returns the notification count in HTML format.
	 *
	 * @return string The notification count in HTML format.
	 */
	private function get_notification_counter(): string {
		return sprintf(
			' <span class="menu-counter count-%1$d"><span class="count" aria-hidden="true">%1$d</span></span>',
			$this->get_notifications_array()['metrics']['total']
		);
	}

	/**
	 * Returns the notifications.
	 *
	 * @return object The notifications.
	 */
	protected function get_notifications(): object {
		return (object) $this->get_notifications_array();
	}
}
