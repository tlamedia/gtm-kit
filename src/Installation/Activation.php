<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Options\Options;
use TLA_Media\GTM_Kit\Options\OptionSchema;

/**
 * Activation
 */
final class Activation {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Checks if GTM Kit is installed for the first time.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;

		if ( $this->is_first_install() ) {
			\add_action( 'gtmkit_activate', [ $this, 'set_first_install_options' ] );
		} else {
			$this->set_autoload_on_options();
		}
	}

	/**
	 * When the option doesn't exist, it should be a new installation.
	 *
	 * @return bool
	 */
	private function is_first_install(): bool {
		return ( \get_option( 'gtmkit_version' ) === false );
	}

	/**
	 * Sets the options on first install for showing the installation notice and disabling of the settings pages.
	 */
	public function set_first_install_options(): void {
		\add_option( 'gtmkit_initial_version', GTMKIT_VERSION, '', false );
		\update_option( 'gtmkit_version', GTMKIT_VERSION, false );

		$map      = OptionSchema::get_schema();
		$defaults = [];
		foreach ( $map as $group => $settings ) {
			foreach ( $settings as $key => $option ) {
				$defaults[ $group ][ $key ] = $option['default'];
			}
		}

		$this->options->set( $defaults, true );

		// Add transient to trigger redirect to the Setup Wizard.
		\set_transient( 'gtmkit_activation_redirect', true, 30 );
		\set_transient( 'gtmkit_first_install', true, 300 );
	}

	/**
	 * Set autoload on options.
	 */
	public function set_autoload_on_options(): void {

		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( 'gtmkit', true );
		}
	}
}
