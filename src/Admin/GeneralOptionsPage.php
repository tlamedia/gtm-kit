<?php

namespace TLA_Media\GTM_Kit\Admin;

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
	 * Renders the admin page.
	 */
	public function render(): void {
		$form = OptionsForm::get_instance();
		$form->admin_header( true, $this->option_name, $this->option_group, $this->get_menu_slug() );

		$site_data = $this->util->get_site_data( $this->options->get_all_raw() );

		$dashboard_tabs = new OptionTabs( 'general' );
		$dashboard_tabs->add_tab(
			new OptionTab(
				'dashboard',
				__( 'Dashboard', 'gtm-kit' ),
				[
					'save_button' => false,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'container',
				__( 'Container', 'gtm-kit' ),
				[
					'save_button' => true,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'post_data',
				__( 'Post data', 'gtm-kit' )
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'user_data',
				__( 'User data', 'gtm-kit' )
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'misc',
				__( 'Misc', 'gtm-kit' ),
				[
					'tab_data' => ['site_data' => $site_data]
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'whats_new',
				__( "What's New", 'gtm-kit' ),
				[
					'save_button' => false,
				]
			)
		);

		$dashboard_tabs->display( $form );

		$form->admin_footer( true, false);

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
		return __( 'General', 'gtm-kit' );
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
	 * Get the tabs of the admin page.
	 */
	protected function get_tabs(): void {
		$generalTabs = new OptionTabs( 'general' );
		$generalTabs->add_tab( new OptionTab( 'dashboard', __( 'Dashboard', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'container', __( 'Container', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'post_data', __( 'Post data', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'user_data', __( 'User data', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'misc', __( 'Misc.', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'whats_new', __( "What's new", 'gtm-kit' ) ) );
	}

}
