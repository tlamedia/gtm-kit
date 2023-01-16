<?php

namespace TLA_Media\GTM_Kit\Admin;

class IntegrationsOptionsPage extends AbstractOptionsPage {

	protected $option_group = 'integrations';

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

		$dashboard_tabs = new OptionTabs( 'integrations' );
		$dashboard_tabs->add_tab(
			new OptionTab(
				'integrations',
				__( 'Integrations', 'gtm-kit' ),
				[
					'save_button' => false,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'woocommerce',
				'WooCommerce',
				[
					'save_button' => true,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'cf7',
				'Contact Form 7',
				[
					'save_button' => true,
				]
			)
		);
		$dashboard_tabs->add_tab(
			new OptionTab(
				'edd',
				'Easy Digital Downloads',
				[
					'save_button' => true,
				]
			)
		);

		$dashboard_tabs->display( $form );

		$form->admin_footer( true, false );

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
	 * Get the tabs of the admin page.
	 */
	protected function get_tabs(): void {
		$generalTabs = new OptionTabs( 'integrations' );
		$generalTabs->add_tab( new OptionTab( 'integrations', __( 'Overview', 'gtm-kit' ) ) );
		$generalTabs->add_tab( new OptionTab( 'woocommerce', 'WooCommerce' ) );
		$generalTabs->add_tab( new OptionTab( 'cf7', 'Contact Form 7' ) );
		$generalTabs->add_tab( new OptionTab( 'edd', 'Easy Digital Downloads' ) );
	}

}
