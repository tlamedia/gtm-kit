<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin\Introductions\UI;

use TLA_Media\GTM_Kit\Admin\AssetsTrait;
use TLA_Media\GTM_Kit\Admin\Introductions\Application\Current_Page_Trait;
use TLA_Media\GTM_Kit\Admin\Introductions\Application\Introductions_Collector;
use TLA_Media\GTM_Kit\Admin\Introductions\Application\Welcome_Introduction;
use TLA_Media\GTM_Kit\Admin\Introductions\Domain\Introduction_Item;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Introductions_Seen_Repository;
use TLA_Media\GTM_Kit\Admin\Introductions\Infrastructure\Remote_Introductions_Source;
use TLA_Media\GTM_Kit\Common\Util;
use TLA_Media\GTM_Kit\Options\Options;

/**
 * Boots the introductions module: builds the collector with bundled intros, registers the REST
 * seen route, and hooks `admin_enqueue_scripts` to surface the eligible intro on GTM Kit admin
 * pages.
 */
final class Introductions_Integration {

	use AssetsTrait;
	use Current_Page_Trait;

	/**
	 * Script handle for the introductions bundle.
	 *
	 * @var string
	 */
	public const SCRIPT_HANDLE = 'introductions';

	/**
	 * DOM mount id the React bundle attaches to.
	 *
	 * @var string
	 */
	public const MOUNT_ID = 'gtmkit-introductions-root';

	/**
	 * Collector for eligible intros.
	 *
	 * @var Introductions_Collector
	 */
	private Introductions_Collector $collector;

	/**
	 * Seen-state repository.
	 *
	 * @var Introductions_Seen_Repository
	 */
	private Introductions_Seen_Repository $seen;

	/**
	 * Constructor.
	 *
	 * @param Introductions_Collector       $collector The collector.
	 * @param Introductions_Seen_Repository $seen The repository.
	 */
	public function __construct( Introductions_Collector $collector, Introductions_Seen_Repository $seen ) {
		$this->collector = $collector;
		$this->seen      = $seen;
	}

	/**
	 * Register the integration: wire the REST route and hook `admin_enqueue_scripts`.
	 *
	 * @param Options $options The Options instance.
	 * @param Util    $util The Util instance.
	 *
	 * @return void
	 */
	public static function register( Options $options, Util $util ): void {
		unset( $options );

		$seen      = new Introductions_Seen_Repository();
		$collector = new Introductions_Collector(
			$seen,
			[
				new Welcome_Introduction(),
			]
		);

		$seen_route = new Introductions_Seen_Route( $seen, $collector );
		( new Introductions_REST_Controller( $util->rest_api_server, $seen_route ) )->register();

		( new Remote_Introductions_Source( $util ) )->register();

		$integration = new self( $collector, $seen );

		\add_action( 'admin_enqueue_scripts', [ $integration, 'enqueue' ] );
		\add_action( 'admin_footer', [ $integration, 'render_mount_point' ] );
	}

	/**
	 * Localize the script payload used by the JS bundle.
	 *
	 * Required by AssetsTrait. The actual payload is built in `enqueue()` because the
	 * eligible-intro list is computed there.
	 *
	 * @param string $page_slug The page slug.
	 * @param string $script_handle The script handle.
	 *
	 * @return void
	 */
	public function localize_script( string $page_slug, string $script_handle ): void {
		unset( $page_slug, $script_handle );
	}

	/**
	 * Enqueue the bundle if the current admin page is eligible and the user has unseen intros.
	 *
	 * @param mixed $hook The current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue( $hook ): void {
		if ( $this->is_on_setup_wizard() ) {
			return;
		}
		if ( $this->is_on_installation_success() ) {
			return;
		}
		if ( ! $this->is_on_gtmkit_admin_page( $hook ) ) {
			return;
		}

		$user_id = \get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$intros = $this->collector->get_for( $user_id );
		if ( empty( $intros ) ) {
			return;
		}

		$asset_path = GTMKIT_PATH . 'assets/admin/' . self::SCRIPT_HANDLE . '.js';
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$this->enqueue_assets( 'introductions', self::SCRIPT_HANDLE, '', '', 'gtm-kit', false );

		\wp_localize_script(
			'gtmkit-' . self::SCRIPT_HANDLE . '-script',
			'gtmkitIntroductions',
			[
				'mountId'       => self::MOUNT_ID,
				'restRoot'      => \esc_url_raw( \rest_url( 'gtmkit/v1' ) ),
				'nonce'         => \wp_create_nonce( 'wp_rest' ),
				'introductions' => array_map(
					static function ( Introduction_Item $item ): array {
						return $item->to_array();
					},
					$intros
				),
			]
		);

		$highest = $intros[0];
		$this->seen->mark_seen( $user_id, $highest->get_id() );
	}

	/**
	 * Print the mount point in the admin footer when the bundle is enqueued.
	 *
	 * @return void
	 */
	public function render_mount_point(): void {
		if ( ! \wp_script_is( 'gtmkit-' . self::SCRIPT_HANDLE . '-script', 'enqueued' ) ) {
			return;
		}
		echo '<div id="' . \esc_attr( self::MOUNT_ID ) . '"></div>';
	}
}
