<?php

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

class UserData {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options
	 */
	public static function register( Options $options ): void {
		$page = new static( $options );

		add_filter( 'gtmkit_datalayer_content', [ $page, 'get_datalayer_content' ], 9 );
	}

	/**
	 * Get the dataLayer content
	 */
	public function get_datalayer_content( array $datalayer ): array {

		$include_logged_in = $this->options->get( 'general', 'datalayer_logged_in' );
		$include_user_id   = $this->options->get( 'general', 'datalayer_user_id' );
		$include_user_role = $this->options->get( 'general', 'datalayer_user_role' );

		if ( $include_logged_in || $include_user_id || $include_user_role ) {
			$current_user_id = get_current_user_id();
		}

		if ( $include_logged_in ) {
			$datalayer['userLoggedIn'] = (bool) $current_user_id;
		}

		if ( $include_user_id && $current_user_id ) {
			$datalayer['user_id'] = $current_user_id;
		}

		if ( $include_user_role ) {

			if ( $current_user_id > 0 ) {
				$current_user = wp_get_current_user();
				$user_role    = implode( ',', $current_user->roles );
			} else {
				$user_role = 'not-logged-in';
			}

			$datalayer['userRole'] = $user_role;
		}

		return $datalayer;
	}

}
