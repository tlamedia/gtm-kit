<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Frontend;

use TLA_Media\GTM_Kit\Options;

/**
 * Stape
 */
final class Stape {

	/**
	 * The Cookie Keeper name.
	 *
	 * @var string
	 */
	const COOKIE_KEEPER_NAME = '_sbp';

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
	 * Register
	 *
	 * @param Options $options An instance of Options.
	 */
	public static function register( Options $options ): void {
		$page = new self( $options );

		add_action( 'init', [ $page, 'add_cookie_keeper' ] );
	}

	/**
	 * Add cookie keeper.
	 *
	 * @return void
	 */
	public function add_cookie_keeper() {
		if ( ! $this->options->get( 'general', 'sgtm_cookie_keeper' ) ) {
			if ( ! empty( $_COOKIE[ self::COOKIE_KEEPER_NAME ] ) ) {
				$this->delete_cookie();
			}
			return;
		}

		if ( ! empty( $_COOKIE[ self::COOKIE_KEEPER_NAME ] ) ) {
			return;
		}

		$this->set_cookie(
			[
				'name'    => self::COOKIE_KEEPER_NAME,
				'value'   => md5( wp_rand( PHP_INT_MIN, PHP_INT_MAX ) . '|' . filter_input( INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_SANITIZE_SPECIAL_CHARS ) . '|' . time() ),
				'expires' => time() + ( YEAR_IN_SECONDS * 2 ),
			]
		);
	}


	/**
	 * Delete cookie.
	 *
	 * @return void
	 */
	private function delete_cookie(): void {
		$this->set_cookie(
			[
				'name'    => self::COOKIE_KEEPER_NAME,
				'value'   => '',
				'expires' => -1,
			]
		);
		unset( $_COOKIE[ self::COOKIE_KEEPER_NAME ] );
	}

	/**
	 * Set cookie.
	 *
	 * @param  array<string, mixed> $args Parameters.
	 * @return void
	 */
	private function set_cookie( array $args ): void {
		$args = wp_parse_args(
			$args,
			[
				'name'     => '',
				'value'    => '',
				'expires'  => 0,
				'path'     => '/',
				'domain'   => '.' . wp_parse_url( home_url(), PHP_URL_HOST ),
				'secure'   => true,
				'httponly' => false,
				'samesite' => 'lax',
			]
		);

		setcookie(
			$args['name'],
			$args['value'],
			$args['expires'],
			$args['path'],
			$args['domain'],
			$args['secure'],
			$args['httponly']
		);
	}
}
