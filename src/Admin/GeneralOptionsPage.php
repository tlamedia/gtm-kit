<?php

namespace TLA\GTM_Kit\Admin;

class GeneralOptionsPage extends AbstractOptionsPage {

	protected $option_group = 'general';

	/**
	 * Adds the admin page to the menu.
	 */
	public function add_admin_page(): void {
		add_menu_page(
			$this->get_page_title(),
			'GTM KIT',
			$this->get_capability(),
			$this->get_menu_slug(),
			[ $this, 'render' ]
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
	 * Renders the admin page.
	 */
	public function render(): void {
		$form = OptionsForm::get_instance();
		$form->admin_header( true, $this->option_name, $this->option_group, $this->get_menu_slug() );

		$dashboard_tabs = new OptionTabs( 'general' );
		$dashboard_tabs->add_tab(
			new OptionTab(
				'dashboard',
				__( 'Dashboard', 'gtmkit' ),
				[
					'save_button' => false,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'container',
				__( 'Container', 'gtmkit' ),
				[
					'save_button' => true,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'post_data',
				__( 'Post data', 'gtmkit' )
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'help',
				__( 'Help', 'gtmkit' ),
				[
					'save_button' => false,
				]
			)
		);

		$dashboard_tabs->display( $form );

		$form->admin_footer();

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
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return __( 'General', 'gtmkit' );
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title(): string {
		return __( 'General Settings', 'gtmkit' );
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
	 * Get the tabs of the admin page.
	 */
	protected function get_tabs(): void {
		$generalTabs = new OptionTabs( 'general' );
		$generalTabs->add_tab( new OptionTab( 'dashborar', __( 'Dashboard', 'gtmkit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'container', __( 'Container', 'gtmkit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'post_data', __( 'Post data', 'gtmkit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'help', __( 'Help', 'gtmkit' ) ) );
	}

}
