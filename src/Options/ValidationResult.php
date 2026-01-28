<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

/**
 * Validation Result
 *
 * Represents the result of a validation operation.
 */
final class ValidationResult {

	/**
	 * Is the validation successful
	 *
	 * @var bool
	 */
	private bool $valid;

	/**
	 * Error message
	 *
	 * @var string
	 */
	private string $error_message;

	/**
	 * Error code
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Constructor
	 *
	 * @param bool   $valid Is valid.
	 * @param string $error_message Error message.
	 * @param string $error_code Error code.
	 */
	private function __construct( bool $valid, string $error_message = '', string $error_code = '' ) {
		$this->valid         = $valid;
		$this->error_message = $error_message;
		$this->error_code    = $error_code;
	}

	/**
	 * Create valid result
	 *
	 * @return self
	 */
	public static function valid(): self {
		return new self( true );
	}

	/**
	 * Create error result
	 *
	 * @param string $message Error message.
	 * @param string $code Error code.
	 * @return self
	 */
	public static function error( string $message, string $code = 'invalid_value' ): self {
		return new self( false, $message, $code );
	}

	/**
	 * Is the result valid
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return $this->valid;
	}

	/**
	 * Get error message
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->error_message;
	}

	/**
	 * Get error code
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}
}
