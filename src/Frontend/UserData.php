<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

/**
 * UserData
 */
final class UserData {

	/**
	 * Plugin options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options An instance of Options.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register frontend
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function register( Options $options ): void {
		$page = new self( $options );

		add_filter( 'gtmkit_datalayer_content', [ $page, 'get_datalayer_content' ], 9 );
	}

	/**
	 * Get the dataLayer content
	 *
	 * @param array<string, mixed> $datalayer The datalayer.
	 *
	 * @return array<string, mixed>
	 */
	public function get_datalayer_content( array $datalayer ): array {

		$include_logged_in = $this->options->get( 'general', 'datalayer_logged_in' );
		$include_user_id   = $this->options->get( 'general', 'datalayer_user_id' );
		$include_user_role = $this->options->get( 'general', 'datalayer_user_role' );

		$current_user_id = 0;
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
