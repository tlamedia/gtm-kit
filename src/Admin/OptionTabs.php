<?php

namespace TLA_Media\GTM_Kit\Admin;

class OptionTabs {

	/**
	 * Tabs base.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * The tabs in this group.
	 *
	 * @var array
	 */
	private $tabs = [];

	/**
	 * Name of the active tab.
	 *
	 * @var string
	 */
	private $active_tab = '';

	/**
	 * OptionTabs constructor.
	 *
	 * @param string $base Base of the tabs.
	 * @param string $active_tab Currently active tab.
	 */
	public function __construct( string $base, string $active_tab = '' ) {
		$this->base = sanitize_title( $base );

		$tab              = filter_input( INPUT_GET, 'tab' );
		$this->active_tab = empty( $tab ) ? $active_tab : $tab;
	}

	/**
	 * Get the base.
	 *
	 * @return string
	 */
	public function get_base(): string {
		return $this->base;
	}

	/**
	 * Add a tab.
	 *
	 * @param OptionTab $tab Tab to add.
	 *
	 * @return $this
	 */
	public function add_tab( OptionTab $tab ): OptionTabs {
		$this->tabs[] = $tab;

		return $this;
	}

	/**
	 * Get active tab.
	 *
	 * @return OptionTab|null Get the active tab.
	 */
	public function get_active_tab(): ?OptionTab {
		if ( empty( $this->active_tab ) ) {
			return null;
		}

		$active_tabs = array_filter( $this->tabs, [ $this, 'is_active_tab' ] );
		if ( ! empty( $active_tabs ) ) {
			$active_tabs = array_values( $active_tabs );
			if ( count( $active_tabs ) === 1 ) {
				return $active_tabs[0];
			}
		}

		return null;
	}

	/**
	 * Is the tab the active tab.
	 *
	 * @param OptionTab $tab Tab to check for active tab.
	 *
	 * @return bool
	 */
	public function is_active_tab( OptionTab $tab ): bool {
		return ( $tab->get_name() === $this->active_tab );
	}

	/**
	 * Get all tabs.
	 *
	 * @return OptionTab[]
	 */
	public function get_tabs(): array {
		return $this->tabs;
	}

	/**
	 * Retrieves the path to the view of the tab.
	 *
	 * @param OptionTab $tab Tab to get name from.
	 *
	 * @return string
	 */
	public function get_tab_view( OptionTab $tab ): string {
		return GTMKIT_PATH . 'src/views/tabs/' . $this->get_base() . '/' . $tab->get_name() . '.php';
	}

	/**
	 * Outputs the option tabs.
	 *
	 * @param OptionsForm $form Option Tabs to get tabs from.
	 */
	public function display( OptionsForm $form ): void {

		?>
		<div class="nav-tab-wrapper" id="gtmkit-tabs">
		<?php
		foreach ( $this->get_tabs() as $tab ) {
			printf(
				'<a class="nav-tab" id="%1$s" href="%2$s">%3$s</a>',
				esc_attr( $tab->get_name() . '-tab' ),
				esc_url( '#top#' . $tab->get_name() ),
				esc_html( $tab->get_label() )
			);
		}
		?>
		</div>
		<?php

		foreach ( $this->get_tabs() as $tab ) {
			$identifier = $tab->get_name();

			$class = 'gtmkit-tab ' . ( $tab->has_save_button() ? 'save' : 'nosave' );
			printf( '<div id="%1$s" class="%2$s">', esc_attr( $identifier ), esc_attr( $class ) );

			$tab_filter_name = sprintf( '%s_%s', $this->get_base(), $tab->get_name() );

			// Output the settings view for all tabs.
			$tab_view = $this->get_tab_view( $tab );

			if ( is_file( $tab_view ) ) {
				require $tab_view;
			}

			echo '</div>';
		}
	}
}
