<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Options;

/**
 * MetaBox
 */
final class MetaBox {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options The Options instance.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register meta box
	 *
	 * @param Options $options The Options instance.
	 */
	public static function register( Options $options ): void {
		$page = new MetaBox( $options );

		add_action( 'add_meta_boxes', [ $page, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $page, 'save_meta_box_options' ] );
	}

	/**
	 * Add "GTM Kit" meta box
	 */
	public function add_meta_boxes() {
		if ( current_user_can( 'manage_options' ) ) {
			$post_types = get_post_types(
				[
					'public' => true,
				],
				'objects'
			);
			if ( isset( $post_types['attachment'] ) ) {
				unset( $post_types['attachment'] );
			}

			foreach ( $post_types as $post_type => $post_type_object ) {
				$label = $post_type_object->labels->singular_name;
				add_meta_box(
					'gtmkit_options',
					sprintf( __( 'GTM Kit', 'gtm-kit' ), $label ),
					[
						$this,
						'display_meta_boxes',
					],
					$post_type,
					'side',
					'core'
				);
			}
		}
	}

	/**
	 * Displays some checkbox to de/activate some cache options
	 */
	public function display_meta_boxes() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_nonce_field( 'gtmkit_box_option', '_gtmkitnonce', false );
			$page_type = get_post_meta( get_the_ID(), 'gtmkit_page_type', true );
			?>
			<div class="gtmkit_options">

				<label for="gtmkit_option_page_type"
						style="font-weight: bold;"><?php esc_html_e( 'Set page type in datalayer:', 'gtm-kit' ); ?></label>
				<input name="gtmkit_option[page_type]" id="gtmkit_option_page_type" type="text"
						title="<?php esc_html_e( 'Page type', 'gtm-kit' ); ?>" value="<?php echo esc_attr( $page_type ); ?>">

				<p class="gtmkit-note" style="margin-top: 16px;">
					<?php
					// translators: %1$s = opening strong tag, %2$s = closing strong tag.
					printf( esc_html__( '%1$sNote:%2$s This will only be applied if page type has been activated in the global settings of GTM Kit.', 'gtm-kit' ), '<strong>', '</strong>' );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Manage the cache options from the meta box.
	 */
	public function save_meta_box_options() {
		if ( current_user_can( 'manage_options' ) && isset( $_POST['post_ID'], $_POST['_gtmkitnonce'] ) ) {

			check_admin_referer( 'gtmkit_box_option', '_gtmkitnonce' );

			if ( isset( $_POST['gtmkit_option']['page_type'] ) ) {
				if ( empty( $_POST['gtmkit_option']['page_type'] ) ) {
					delete_post_meta( (int) $_POST['post_ID'], 'gtmkit_page_type' );
				} else {
					update_post_meta( (int) $_POST['post_ID'], 'gtmkit_page_type', $_POST['gtmkit_option']['page_type'] );
				}
			}
		}
	}
}

