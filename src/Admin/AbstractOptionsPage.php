<?php

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Options;

abstract class AbstractOptionsPage {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * That's where plugin options are saved in wp_options table.
	 *
	 * @var string
	 */
	protected $option_name = 'gtmkit';

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register the options page.
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {
		$page = new static( $options );

		add_action( 'admin_init', [ $page, 'configure' ] );
		add_action( 'admin_menu', [ $page, 'add_admin_page' ] );
		add_action( 'admin_enqueue_scripts', [ $page, 'enqueue_assets' ] );

		add_filter( 'admin_body_class', [ $page, 'admin_body_class' ] );
	}

	/**
	 * Adds the admin page to the menu.
	 */
	public function add_admin_page(): void {
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
	 * Renders the admin page.
	 */
	abstract public function render(): void;


	/**
	 * Configure the admin page using the settings API.
	 */
	abstract public function configure();

	/**
	 * Get the capability required to view the admin pages.
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return apply_filters( 'gtmkit_admin_capability', 'manage_options' );
	}

	/**
	 * Get the admin page menu title.
	 *
	 * @return string
	 */
	protected function get_menu_title(): string {
		return $this->get_page_title();
	}

	/**
	 * Get the admin page menu slug.
	 *
	 * This slug must be unique.
	 *
	 * @return string
	 */
	abstract protected function get_menu_slug(): string;

	/**
	 * Get the admin page title.
	 *
	 * @return string
	 */
	abstract protected function get_page_title(): string;

	/**
	 * Get the parent slug of the admin page.
	 *
	 * @return string|null
	 */
	abstract protected function get_parent_slug(): string;

	/**
	 * Get the tabs of the admin page.
	 */
	abstract protected function get_tabs(): void;

	/**
	 * Enqueue admin area scripts and styles.
	 *
	 * @param string $hook Current hook.
	 */
	public function enqueue_assets( string $hook ) {

		if ( strpos( $hook, GTMKIT_ADMIN_SLUG ) === false ) {
			return;
		}

		if ( wp_get_environment_type() == 'local' ) {
			$version = time();
		} else {
			$version = GTMKIT_VERSION;
		}

		// General styles and js.
		wp_enqueue_style(
			'gtmkit-admin-css',
			GTMKIT_URL . 'assets/css/admin.css',
			false,
			$version
		);
		wp_enqueue_script(
			'gtmkit-admin',
			GTMKIT_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			$version,
			true
		);

		$script_data = [
			'plugin_url' => GTMKIT_URL,
			'nonce'      => wp_create_nonce( 'gtmkit-admin' ),
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
		];

		wp_localize_script( 'gtmkit-admin', 'gtmkit', $script_data );

	}

	/**
	 * Add body class.
	 *
	 * @param string $classes The body classes.
	 *
	 * @return string
	 */
	function admin_body_class( string $classes ): string {

		$page_parent = get_admin_page_parent();

		if ( $this->get_parent_slug() === $page_parent ) {
			$classes .= ' gtmkit-admin-page';
		}

		return $classes;
	}

}
