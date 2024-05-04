<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Installation\PluginDataImport;
use TLA_Media\GTM_Kit\Options;
use WP_Error;

/**
 * Class for the plugin's Setup Wizard.
 */
final class SetupWizard {

	/**
	 * Slug of the setup wizard page.
	 *
	 * @var string
	 */
	const SLUG = 'gtmkit_setup_wizard';

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
	public function __construct( Options $options, Util $util ) {
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
		$page = new SetupWizard( $options, $util );

		add_action( 'admin_init', [ $page, 'maybe_redirect_after_activation' ], 9999 );
		add_action( 'admin_menu', [ $page, 'add_dashboard_page' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $page, 'enqueue_assets' ] );
	}

	/**
	 * Get the URL of the Setup Wizard page.
	 *
	 * @return string
	 */
	public static function get_site_url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Maybe redirect to the setup wizard after plugin activation.
	 */
	public function maybe_redirect_after_activation() {

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// Check if we should consider redirection.
		if ( ! get_transient( 'gtmkit_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'gtmkit_activation_redirect' );

		// Check option to disable setup wizard redirect.
		if ( get_option( 'gtmkit_activation_prevent_redirect' ) ) {
			return;
		}

		// Only do this for single site installs.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) { // phpcs:ignore
			return;
		}

		// Initial install.
		if ( get_option( 'gtmkit_initial_version' ) === GTMKIT_VERSION ) {
			update_option( 'gtmkit_activation_prevent_redirect', false );
			wp_safe_redirect( self::get_site_url() );
			exit;
		}
	}

	/**
	 * Add Dashboard Page
	 */
	public function add_dashboard_page(): void {
		add_submenu_page( 'options.php', '', '', 'manage_options', self::SLUG, [ $this, 'render_page' ] );
	}

	/**
	 * Load the assets needed for the Setup Wizard.
	 *
	 * @param string $hook The asset hook.
	 */
	public function enqueue_assets( string $hook ) {

		if ( strpos( $hook, self::SLUG ) === false ) {
			return;
		}

		$deps_file  = GTMKIT_PATH . 'assets/admin/wizard.asset.php';
		$dependency = [];
		if ( file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		wp_enqueue_style( 'gtmkit-wizard-style', GTMKIT_URL . 'assets/admin/wizard.css', array( 'wp-components' ), $version );

		wp_enqueue_script( 'gtmkit-wizard-script', GTMKIT_URL . 'assets/admin/wizard.js', $dependency, $version, true );

		wp_localize_script(
			'gtmkit-wizard-script',
			'gtmkitSettings',
			[
				'rootId'       => 'gtmkit-settings',
				'currentPage'  => 'wizard',
				'root'         => esc_url_raw( rest_url() ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'dashboardUrl' => menu_page_url( 'gtmkit_general', false ),
				'templatesUrl' => menu_page_url( 'gtmkit_templates', false ),
				'settings'     => $this->options->get_all_raw(),
				'site_data'    => $this->util->get_site_data( $this->options->get_all_raw() ),
				'install_data' => ( new PluginDataImport() )->get_all(),
			]
		);
	}

	/**
	 * Setup Wizard Content
	 */
	public function setup_wizard_content() {
		$admin_url = is_network_admin() ? network_admin_url() : admin_url();

		$this->settings_error_page( 'gtmkit-settings', '<a class="gtmkit-text-color-grey gtmkit-text-sm" href="' . $admin_url . '">' . esc_html__( 'Go back to the Dashboard', 'gtm-kit' ) . '</a>' );
	}

	/**
	 * Error page HTML
	 *
	 * @param string $id The HTML ID attribute of the main container div.
	 * @param string $footer The centered footer content.
	 */
	private function settings_error_page( string $id = 'gtmkit-react-site-settings', string $footer = '' ): void {

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
	 * Render page
	 *
	 * @return void
	 */
	public function render_page() {
		$this->setup_wizard_content();
	}
}
