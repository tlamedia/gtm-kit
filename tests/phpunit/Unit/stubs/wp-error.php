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
	 * Constructor.
	 *
	 * @param string $code The error code.
	 */
	public function __construct( string $code = '' ) {
		$this->code = $code;
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
