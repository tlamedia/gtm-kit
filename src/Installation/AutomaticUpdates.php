<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Options;

/**
 * Activation
 */
final class AutomaticUpdates {

	/**
	 * Instance of this class
	 *
	 * @var null|self
	 */
	public static ?self $instance = null;

	/**
	 * Register analytics
	 */
	public static function register(): void {
		self::$instance = new self();

		self::$instance->add_wp_hooks();

		if ( doing_action( 'activate_' . GTMKIT_BASENAME ) ) {
			self::$instance->activation_sync();
		}
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return self
	 */
	public static function instance(): self {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Updates the GTM Kit setting when the WordPress auto_update_plugins option is updated.
	 *
	 * @param string             $option    The name of the option.
	 * @param array<int, string> $value     The current value of the option.
	 * @param array<int, string> $old_value The previous value of the option.
	 */
	public function wp_option_updated( $option, $value, $old_value = [] ): void {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_POST['asset'] ) && ! empty( $_POST['state'] ) ) { // @phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Option is being updated by the ajax request performed when using the enable/disable auto-updates links on the plugins page.
			$asset = sanitize_text_field( urldecode( $_POST['asset'] ) ); // @phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $asset !== GTMKIT_BASENAME ) {
				return;
			}

			$is_enabled = $_POST['state'] === 'enable'; // @phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			// Option is being updated by some other means (e.g. CLI).
			$is_enabled  = in_array( GTMKIT_BASENAME, $value, true );
			$was_enabled = in_array( GTMKIT_BASENAME, $old_value, true );

			if ( $is_enabled === $was_enabled ) {
				return;
			}
		}

		$this->update_gtmkit_option( $is_enabled );
	}

	/**
	 * Updates the GTM Kit auto-update setting when the WordPress auto_update_plugins option is deleted.
	 */
	public function wp_option_deleted(): void {
		$this->update_gtmkit_option( false );
	}

	/**
	 * Updates the GTM Kit option.
	 *
	 * @param bool $is_enabled Indicates if auto-updates are enabled.
	 *
	 * @return void
	 */
	public function update_gtmkit_option( bool $is_enabled ): void {
		Options::init()->set_option( 'misc', 'auto_update', $is_enabled );
	}

	/**
	 * Activate auto-update of GTM Kit.
	 *
	 * Updates the WordPress auto_update_plugins option to enable or disable automatic updates for GTM Kit.
	 *
	 * @param bool $activate Activate or deactivate auto-updates.
	 */
	public function activate_auto_update( bool $activate ): void {
		$auto_updates = (array) get_site_option( 'auto_update_plugins', [] );

		if ( $activate ) {
			$auto_updates[] = GTMKIT_BASENAME;
			$auto_updates   = array_unique( $auto_updates );
		} else {
			$auto_updates = array_diff( $auto_updates, [ GTMKIT_BASENAME ] );
		}

		$this->remove_wp_hooks();
		update_site_option( 'auto_update_plugins', $auto_updates );
		$this->add_wp_hooks();
	}

	/**
	 * Adds the action hooks for the auto_update_plugins option.
	 *
	 * @return void
	 */
	public function add_wp_hooks(): void {
		add_action( 'add_site_option_auto_update_plugins', [ $this, 'wp_option_updated' ], 10, 2 );
		add_action( 'update_site_option_auto_update_plugins', [ $this, 'wp_option_updated' ], 10, 3 );
		add_action( 'delete_site_option_auto_update_plugins', [ $this, 'wp_option_deleted' ] );
	}

	/**
	 * Removes the action hooks for the auto_update_plugins option.

	 * @return void
	 */
	public function remove_wp_hooks(): void {
		remove_action( 'add_site_option_auto_update_plugins', [ $this, 'wp_option_updated' ] );
		remove_action( 'update_site_option_auto_update_plugins', [ $this, 'wp_option_updated' ] );
		remove_action( 'delete_site_option_auto_update_plugins', [ $this, 'wp_option_deleted' ] );
	}

	/**
	 * Updates the WordPress auto_update_plugins option to match the GTM Kit setting.
	 *
	 * @return void
	 */
	public function activation_sync(): void {
		$enabled = Options::init()->get( 'misc', 'auto_update' );
		if ( ! $enabled ) {
			return;
		}

		$this->activate_auto_update( $enabled );
	}
}
