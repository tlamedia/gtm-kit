<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

/**
 * Option Validator
 *
 * Validates option values against schema rules.
 */
final class OptionValidator {

	/**
	 * Validate option value
	 *
	 * @param string $group Option group.
	 * @param string $key Option key.
	 * @param mixed  $value Value to validate.
	 * @return ValidationResult
	 */
	public function validate( string $group, string $key, $value ): ValidationResult {
		$schema = OptionSchema::get_option_schema( $group, $key );

		if ( ! $schema ) {
			// Option not in schema - allow (backward compatibility).
			return ValidationResult::valid();
		}

		// Type validation.
		if ( isset( $schema['type'] ) ) {
			$result = $this->validate_type( $value, $schema['type'], $key );
			if ( ! $result->is_valid() ) {
				return $result;
			}
		}

		// Custom validation callback.
		if ( isset( $schema['validate'] ) && is_callable( $schema['validate'] ) ) {
			$callback = $schema['validate'];
			$is_valid = false;

			// Check if validator has extra parameters.
			if ( is_array( $callback ) && count( $callback ) > 2 ) {
				// Extract method and extra params.
				$method   = array_slice( $callback, 0, 2 );
				$params   = array_slice( $callback, 2 );
				$is_valid = call_user_func_array( $method, array_merge( [ $value ], $params ) );
			} else {
				$is_valid = $callback( $value );
			}

			if ( ! $is_valid ) {
				return ValidationResult::error(
					sprintf( 'Invalid value for %s', $key ),
					'validation_failed'
				);
			}
		}

		return ValidationResult::valid();
	}

	/**
	 * Validate type
	 *
	 * @param mixed  $value Value to validate.
	 * @param string $expected_type Expected type.
	 * @param string $key Option key (for error message).
	 * @return ValidationResult
	 */
	private function validate_type( $value, string $expected_type, string $key ): ValidationResult {
		$actual_type = gettype( $value );

		// Handle 'integer' vs 'int' naming.
		if ( $expected_type === 'integer' && $actual_type === 'integer' ) {
			return ValidationResult::valid();
		}

		// Handle integer type - accept numeric strings and booleans.
		if ( $expected_type === 'integer' ) {
			// Accept numeric strings (from frontend form inputs).
			if ( is_numeric( $value ) ) {
				return ValidationResult::valid();
			}
			// Accept booleans (true -> 1, false -> 0).
			if ( is_bool( $value ) ) {
				return ValidationResult::valid();
			}
		}

		// Handle boolean coercion (accept 1/0 as boolean).
		if ( $expected_type === 'boolean' && ( $value === 1 || $value === 0 || $value === '1' || $value === '0' || is_bool( $value ) ) ) {
			return ValidationResult::valid();
		}

		// Handle string type - accept most scalar values.
		if ( $expected_type === 'string' && is_scalar( $value ) ) {
			return ValidationResult::valid();
		}

		// Handle actual type match.
		if ( $actual_type === $expected_type ) {
			return ValidationResult::valid();
		}

		return ValidationResult::error(
			sprintf(
				'Type mismatch for %s: expected %s, got %s',
				$key,
				$expected_type,
				$actual_type
			),
			'type_mismatch'
		);
	}

	/**
	 * Validate all options in a group
	 *
	 * @param array<string, mixed> $options Options by group.
	 * @return array<string, ValidationResult> Results keyed by "group.key".
	 */
	public function validate_all( array $options ): array {
		$results = [];

		foreach ( $options as $group => $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}

			foreach ( $keys as $key => $value ) {
				$option_key             = "$group.$key";
				$results[ $option_key ] = $this->validate( $group, $key, $value );
			}
		}

		return $results;
	}
}
