<?php
/**
 * Bare `WP_Error` stub for the BrainMonkey unit harness.
 *
 * @package TLA_Media\GTM_Kit
 */

if ( class_exists( 'WP_Error' ) ) {
	return;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Squiz.Commenting.ClassComment.Missing -- Mirrors the WordPress core class name so unit-test typehints resolve without booting WP.
class WP_Error {

	/**
	 * The error code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * The error message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Constructor.
	 *
	 * @param string $code The error code.
	 * @param string $message The error message.
	 */
	public function __construct( string $code = '', string $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Get the error message, the way code under test reads it.
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Get the error code, the way code under test reads it.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->code;
	}
}
