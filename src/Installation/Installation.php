<?php

namespace TLA_Media\GTM_Kit\Installation;

use TLA_Media\GTM_Kit\Options;

class Installation {

	/**
	 * Checks if GTM Kit is installed for the first time.
	 */
	public function __construct() {
		if ( $this->is_first_install() ) {
			add_action( 'gtmkit_activate', [ $this, 'set_first_install_options' ] );
		}
	}

	/**
	 * When the option doesn't exist, it should be a new installation.
	 *
	 * @return bool
	 */
	private function is_first_install(): bool {
		return ( get_option( 'gtmkit_version' ) === false );
	}

	/**
	 * Sets the options on first install for showing the installation notice and disabling of the settings pages.
	 */
	public function set_first_install_options(): void {
		update_option( 'gtmkit_version', GTMKIT_VERSION );

		Options::init()->set( Options::get_defaults(), true );
	}

}
