<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options;

/**
 * AbstractOptionsPage
 */
abstract class AbstractOptionsPage {

	/**
	 * That's where plugin options are saved in wp_options table.
	 *
	 * @var string
	 */
	protected $option_name = 'gtmkit';

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Utilities
	 *
	 * @var Util
	 */
	protected $util;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 * @param Util    $util An instance of Util.
	 */
	final public function __construct( Options $options, Util $util ) {
		$this->options = $options;
		$this->util    = $util;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 */
	public static function register( Options $options, Util $util ): void {
		$page = new static( $options, $util );

		add_action( 'admin_init', [ $page, 'configure' ] );
		add_action( 'admin_menu', [ $page, 'add_admin_page' ] );
		add_action( 'admin_enqueue_scripts', [ $page, 'enqueue_page_assets' ] );

		add_filter( 'admin_body_class', [ $page, 'admin_body_class' ] );

		add_action( 'activated_plugin', [ $page, 'clear_script_settings_cache' ] );
		add_action( 'deactivated_plugin', [ $page, 'clear_script_settings_cache' ] );
		add_action( 'switch_theme', [ $page, 'clear_script_settings_cache' ] );
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
			[ $this, 'render' ],
			$this->get_position()
		);
	}

	/**
	 * Renders the admin page.
	 */
	public function render(): void {
		$this->settings_error_page();
	}


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
	 * @return string
	 */
	abstract protected function get_parent_slug(): string;

	/**
	 * The position in the menu order this item should appear.
	 *
	 * @return int|null
	 */
	protected function get_position(): ?int {
		return null;
	}

	/**
	 * Enqueue admin page scripts and styles.
	 *
	 * @param string $hook Current hook.
	 */
	abstract public function enqueue_page_assets( string $hook ): void;

	/**
	 * Enqueue assets.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 * @param string $path The plugin path.
	 * @param string $url The plugin URL.
	 * @param string $domain The translation domain.
	 */
	protected function enqueue_assets( string $page_slug, string $script_handle, string $path = '', string $url = '', string $domain = 'gtm-kit' ) {

		if ( empty( $path ) ) {
			$path = GTMKIT_PATH;
		}
		if ( empty( $url ) ) {
			$url = GTMKIT_URL;
		}

		$deps_file  = $path . 'assets/admin/' . $script_handle . '.asset.php';
		$dependency = [];
		$version    = false;

		if ( \file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		\wp_enqueue_style( 'gtmkit-' . $script_handle . '-style', $url . 'assets/admin/' . $script_handle . '.css', array( 'wp-components' ), $version );

		\wp_enqueue_script( 'gtmkit-' . $script_handle . '-script', $url . 'assets/admin/' . $script_handle . '.js', $dependency, $version, true );

		$this->localize_script( $page_slug, $script_handle );

		\wp_set_script_translations( 'gtmkit-' . $script_handle . '-script', $domain );
	}

	/**
	 * Localize script.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 */
	abstract public function localize_script( string $page_slug, string $script_handle );

	/**
	 * Add body class.
	 *
	 * @param string $classes The body classes.
	 *
	 * @return string
	 */
	public function admin_body_class( string $classes ): string {

		$page_parent = get_admin_page_parent();

		if ( $this->get_parent_slug() === $page_parent ) {
			$classes .= ' gtmkit';
		}

		return $classes;
	}

	/**
	 * Error page HTML
	 *
	 * @param string $id The HTML ID attribute of the main container div.
	 * @param string $footer The centered footer content.
	 */
	public function settings_error_page( string $id = 'gtmkit-settings', string $footer = '' ): void {

		$inline_logo_image = 'data:image/svg+xml;base64,PHN2ZyBlbmFibGUtYmFja2dyb3VuZD0ibmV3IDAgMCAyNDY3LjEgMTU4Ni40IiBoZWlnaHQ9IjU0IiB2aWV3Qm94PSIwIDAgMTYwIDU0IiB3aWR0aD0iMTYwIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxyZWN0IGZpbGw9IiM4ODgiIGhlaWdodD0iNTQiIHJ4PSI0LjI2NSIgd2lkdGg9IjE2MCIvPjxwYXRoIGQ9Im04OC42NTkgNy4wMDJoLTQ5LjI2NmMtMi45MTUgMC01LjM3OC0uMTg0LTkuMjMgMi41OTZsLTE4Ljc0MyAxMi43NTJjLTQuNTQ5IDMuMjkyLTQuNTcxIDYuMDAxIDAgOS4zMDJsMTguNzQzIDEyLjc1MWMzLjY1MiAyLjY0NiA2LjI5OSAyLjU5NyA5LjIzIDIuNTk3aDE1LjY4NiAzMy41OGMyLjkzMS0uMDU3IDUuMzk0LTEuNzg0IDUuMzQtMy45MjN2LTMyLjIxYy0uMDEyLTIuMTM0LTIuNDA5LTMuODY1LTUuMzQtMy44NjV6IiBmaWxsPSIjMzk2OWJiIiB0cmFuc2Zvcm09Im1hdHJpeCgtMSAwIDAgLTEgMTAxLjk5OTg2NyA1My45OTk1NDcpIi8+PGcgZmlsbD0iI2ZmZiIgdHJhbnNmb3JtPSJtYXRyaXgoMS40NjUyMDIgMCAwIDEuNDY1MjAyIC00LjU4MjQzNiAtNy41NTMxMjYpIj48cGF0aCBkPSJtMTguMzE2IDIzLjA4Nmg0LjYzMnY2LjA1MmMtLjc1LjI0NS0xLjQ1OS40MTUtMi4xMjEuNTE0LS42NjMuMDk4LTEuMzQxLjE0OC0yLjAzMy4xNDgtMS43NjQgMC0zLjEwNi0uNTE3LTQuMDM1LTEuNTU0LS45MjktMS4wMzQtMS4zOTUtMi41MTktMS4zOTUtNC40NTcgMC0xLjg4Ni41MzgtMy4zNTYgMS42MTctNC40MSAxLjA3OC0xLjA1NCAyLjU3My0xLjU3OSA0LjQ4MS0xLjU3OSAxLjIgMCAyLjM1Ny4yMzkgMy40NjkuNzE4bC0uODIzIDEuOTc5Yy0uODUtLjQyNS0xLjczNy0uNjM3LTIuNjU3LS42MzctMS4wNzEgMC0xLjkzMi4zNTgtMi41NzIgMS4wNzctLjY0NS43MTgtLjk2OCAxLjY4NS0uOTY4IDIuODk5IDAgMS4yNjcuMjYgMi4yMzMuNzggMi45MDQuNTE2LjY2NyAxLjI3MS45OTkgMi4yNjIuOTk5LjUxNyAwIDEuMDQxLS4wNTIgMS41NzItLjE1OHYtMi40MzVoLTIuMjA5em0xMS45MTIgNi41NTVoLTIuNDc0di05LjYxNGgtMy4xNzN2LTIuMDZoOC44MTZ2Mi4wNmgtMy4xNjl6bTkuOTA4IDAtMi44MTEtOS4xNThoLS4wNzJjLjEwMyAxLjg2My4xNTQgMy4xMDUuMTU0IDMuNzI4djUuNDNoLTIuMjEzdi0xMS42NzRoMy4zNjhsMi43NjQgOC45MjdoLjA0OGwyLjkzNC04LjkyN2gzLjM2OXYxMS42NzRoLTIuMzA4di01LjUyNWMwLS4yNjEgMC0uNTYxLjAwOS0uOTAzLjAwOS0uMzQxLjA0OC0xLjI0Ni4xMTItMi43MTZoLS4wNzRsLTMuMDExIDkuMTQ0eiIvPjxwYXRoIGQ9Im04OC44ODMgMzEuODk4aC0zLjgxMWwtNC4xNDctNi42Ny0xLjQxNyAxLjAxOHY1LjY1MmgtMy4zNTd2LTE1LjgzaDMuMzU3djcuMjQybDEuMzE4LTEuODYxIDQuMjkzLTUuMzgxaDMuNzI0bC01LjUyMyA3LjAwNnptMS41ODktMTUuMjMzYzAtMS4wNzUuNi0xLjYxNCAxLjgtMS42MTQgMS4xOTkgMCAxLjc5OC41MzkgMS43OTggMS42MTQgMCAuNTEyLS4xNDguOTEtLjQ1IDEuMTk2LS4yOTcuMjg0LS43NDkuNDI5LTEuMzQ4LjQyOS0xLjIgMC0xLjgtLjU0My0xLjgtMS42MjV6bTMuNDQ5IDE1LjIzM2gtMy4zMDJ2LTEyLjEwNGgzLjMwMnptOC41NjMtMi40MTNjLjU3OCAwIDEuMjcxLS4xMjggMi4wNzctLjM4djIuNDU2Yy0uODIuMzctMS44MzEuNTUyLTMuMDMxLjU1Mi0xLjMyNCAwLTIuMjgtLjMzMy0yLjg4NS0xLjAwMS0uNjAyLS42NjctLjkwMi0xLjY2OC0uOTAyLTMuMDAzdi01LjgzNWgtMS41ODV2LTEuMzk5bDEuODIzLTEuMTAzLjk0OS0yLjU1N2gyLjExNXYyLjU3OWgzLjM4OHYyLjQ4aC0zLjM4OHY1LjgzNWMwIC40NjcuMTI5LjgxNS4zOTMgMS4wNDEuMjY0LjIyMi42MTQuMzM1IDEuMDQ2LjMzNXoiLz48L2c+PC9zdmc+';

		$admin_url = is_network_admin() ? network_admin_url() : admin_url()

		?>
		<style>
			#gtmkit-settings-loader {
				visibility: hidden;
				animation: loadGTMKitSettingsNoJSView 0s 2s forwards;
			}

			@keyframes loadGTMKitSettingsNoJSView {
				to {
					visibility: visible;
				}
			}

			body {
				background: #F0F0F1;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				margin: 0;
			}
		</style>
		<div id="<?php echo esc_attr( $id ); ?>">
			<div id="gtmkit-settings-loader">
				<header class="gtmkit-text-center gtmkit-border-t-4 gtmkit-border-color-primary gtmkit-px-3">
					<h1 class="gtmkit-mt-3 md:gtmkit-mt-12 gtmkit-w-[250px] gtmkit-inline-block">
						<img src="<?php echo esc_attr( $inline_logo_image ); ?>"
							alt="GTM Kit"
							class="gtmkit-w-full"
						>
					</h1>
				</header>
				<div
					class="gtmkit-text-center gtmkit-mt-4 md:gtmkit-mt-16 gtmkit-max-w-[90%] md:gtmkit-max-w-xl  gtmkit-mx-auto">
					<div
						class="gtmkit-py-12 gtmkit-px-20 gtmkit-bg-white gtmkit-border-1 gtmkit-border-color-border gtmkit-rounded-md gtmkit-drop-shadow-md">
						<h3 class="gtmkit-text-2xl gtmkit-font-medium gtmkit-mb-4 gtmkit-text-color-heading">
							<?php esc_html_e( "Whoops, something's not working.", 'gtm-kit' ); ?>
						</h3>
						<p class="gtmkit-mb-14 gtmkit-text-color-grey">
							<?php esc_html_e( 'It looks like something is preventing JavaScript from loading on your website. GTM Kit requires JavaScript in order to give you the best possible experience.', 'gtm-kit' ); ?>
						</p>
						<div class="gtmkit-mb-4">
							<a href="<?php echo esc_url( $admin_url ); ?>"
								class="gtmkit-bg-color-primary gtmkit-text-white gtmkit-rounded-md gtmkit-py-4 gtmkit-px-8">
								<?php esc_html_e( 'Go back to the Dashboard', 'gtm-kit' ); ?>
							</a>
						</div>
					</div>
					<div class="gtmkit-mt-3 md:gtmkit-mt-12 gtmkit-text-center">
						<?php echo wp_kses_post( $footer ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Clear the script settings cache.
	 *
	 * @return void
	 */
	public function clear_script_settings_cache(): void {
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
	}
}
