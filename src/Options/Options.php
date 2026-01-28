<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Options;

use TLA_Media\GTM_Kit\Admin\NotificationsHandler;
use TLA_Media\GTM_Kit\Installation\AutomaticUpdates;
use TLA_Media\GTM_Kit\Options\Processor\OptionProcessorRegistry;

/**
 * Options
 */
final class Options {

	/**
	 * The option_name in wp_options table.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'gtmkit';

	/**
	 * All the options.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/**
	 * Processor registry
	 *
	 * @var OptionProcessorRegistry
	 */
	private OptionProcessorRegistry $processor_registry;

	/**
	 * Validator
	 *
	 * @var OptionValidator
	 */
	private OptionValidator $validator;

	/**

	/**
	 * Construct
	 */
	public function __construct() {
		$this->options            = \get_option( self::OPTION_NAME, [] );
		$this->processor_registry = new OptionProcessorRegistry();
		$this->validator          = new OptionValidator();

		\add_filter( 'pre_update_option_gtmkit', [ $this, 'pre_update_option' ], 10, 2 );
	}

	/**
	 * Initialize options
	 *
	 * @deprecated Use OptionsFactory::get_instance() instead
	 * @return Options
	 * @example Options::init()->get('general', 'gtm_id');
	 */
	public static function init(): self {
		return OptionsFactory::get_instance();
	}

	/**
	 * Create new instance (for DI)
	 *
	 * @return Options
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Pre update option
	 *
	 * @param mixed $new_value The new value.
	 * @param mixed $old_value The old value.
	 *
	 * @return array<string, mixed>|null
	 */
	public function pre_update_option( $new_value, $old_value ): ?array {
		if ( ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return $new_value;
		}
		return array_merge( $old_value, $new_value );
	}

	/**
	 * The default options.
	 *
	 * @deprecated Use OptionSchema::get_schema() instead
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		$schema = OptionSchema::get_schema();

		// Apply filter for backward compatibility.
		return apply_filters( 'gtmkit_options_defaults', $schema );
	}

	/**
	 * Get options by a group and a key.
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 * @param bool   $strip_slashes If the slashes should be stripped from string values.
	 *
	 * @return mixed|null Null if value doesn't exist anywhere: in constants, in DB, in a map. So it's completely custom or a typo.
	 * @example Options::init()->get( 'general', 'gtm_id' ).
	 */
	public function get( string $group, string $key, bool $strip_slashes = true ) {
		$map = $this->get_default_key_value( $group, $key );

		if ( $this->is_const_defined( $group, $key ) ) {
			$value = constant( $map['constant'] );
		} elseif ( isset( $this->options[ $group ][ $key ] ) ) {
			$value = $this->options[ $group ][ $key ];
		} elseif ( $map ) {
			$value = $map['default'];
		} else {
			return null;
		}

		return is_string( $value ) && $strip_slashes && ! $this->is_const_defined( $group, $key )
			? stripslashes( $value )
			: $value;
	}

	/**
	 * Is overriding options with constants enabled or not.
	 *
	 * @return bool
	 */
	public function is_const_enabled(): bool {

		return defined( 'GTMKIT_ON' ) && GTMKIT_ON === true;
	}

	/**
	 * Get default value for a key
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function get_default_key_value( string $group, string $key ): ?array {
		return OptionSchema::get_option_schema( $group, $key );
	}

	/**
	 * Is constant defined.
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 *
	 * @return bool
	 */
	public function is_const_defined( string $group, string $key ): bool {

		if ( ! $this->is_const_enabled() ) {
			return false;
		}

		$map = $this->get_default_key_value( $group, $key );
		if ( ! $map || ! isset( $map['constant'] ) || ! defined( $map['constant'] ) ) {
			return false;
		}

		$value_type = gettype( constant( $map['constant'] ) );

		if ( isset( $map['type'] ) && $map['type'] !== $value_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Set plugin options.
	 *
	 * @param array<string, mixed> $options Plugin options.
	 * @param bool                 $first_install Add option on first install.
	 * @param bool                 $overwrite_existing Overwrite existing settings or merge.
	 */
	public function set( array $options, bool $first_install = false, bool $overwrite_existing = true ): void {

		if ( ! $overwrite_existing ) {
			$options = self::array_merge_recursive( $this->get_all_raw(), $options );
		}

		// Validate and process options (skip on first install).
		if ( ! $first_install ) {
			// Validate options.
			$validation_results = $this->validator->validate_all( $options );

			// Check for errors.
			$errors = array_filter( $validation_results, fn( $result ) => ! $result->is_valid() );

			if ( ! empty( $errors ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					foreach ( $errors as $option_key => $result ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for validation errors.
						error_log(
							sprintf(
								'GTM Kit: Invalid option value for %s: %s',
								$option_key,
								$result->get_error_message()
							)
						);
					}
				}

				// Trigger error hook (for admin notices).
				do_action( 'gtmkit_options_validation_failed', $errors );

				// Don't save invalid options (fail-fast).
				return;
			}

			// Process options.
			$options = $this->process_options( $options );
			$options = \apply_filters( 'gtmkit_process_options', $options );
		}

		// Store old options for after_save hooks.
		$old_options = $first_install ? [] : $this->get_all_raw();

		// Whether to update existing options or to add these options only once if they don't exist yet.
		if ( $first_install ) {
			\add_option( self::OPTION_NAME, $options, '', true );
		} elseif ( is_multisite() ) {
			\update_blog_option( get_current_blog_id(), self::OPTION_NAME, $options );
		} else {
			\update_option( self::OPTION_NAME, $options, true );
		}

		// Run after_save hooks AFTER successful save.
		if ( ! $first_install ) {
			$this->run_after_save_hooks( $options, $old_options );
		}

		do_action( 'gtmkit_options_set' );

		$this->clear_cache();
	}

	/**
	 * Set single option.
	 *
	 * @param string $group Option group.
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 */
	public function set_option( string $group, string $key, $value ): void {

		$options = $this->get_all_raw();

		$options[ $group ][ $key ] = $value;

		if ( is_multisite() ) {
			\update_blog_option( get_current_blog_id(), self::OPTION_NAME, $options );
		} else {
			\update_option( self::OPTION_NAME, $options, true );
		}

		$this->clear_cache();
	}

	/**
	 * Clear the cache
	 *
	 * @return void
	 */
	private function clear_cache(): void {
		wp_cache_delete( self::OPTION_NAME, 'options' );
		wp_cache_delete( 'gtmkit_script_settings', 'gtmkit' );
		$this->options = get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Process the plugin options.
	 *
	 * @param array<string, mixed> $options The options array.
	 *
	 * @return array<string, mixed>
	 */
	private function process_options( array $options ): array {

		$old_options = $this->get_all_raw();

		foreach ( $options as $group => $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}

			foreach ( $keys as $option_name => $option_value ) {
				$option_key = "$group.$option_name";
				$old_value  = $old_options[ $group ][ $option_name ] ?? null;

				// Type coercion based on schema.
				$schema = OptionSchema::get_option_schema( $group, $option_name );
				if ( $schema && isset( $schema['type'] ) ) {
					$option_value = $this->coerce_type( $option_value, $schema['type'] );
				}

				// Process through registry.
				$options[ $group ][ $option_name ] = $this->processor_registry->process(
					$option_key,
					$option_value,
					$old_value
				);
			}
		}

		return $options;
	}

	/**
	 * Run after_save hooks for changed options
	 *
	 * @param array<string, mixed> $new_options New options.
	 * @param array<string, mixed> $old_options Old options.
	 * @return void
	 */
	private function run_after_save_hooks( array $new_options, array $old_options ): void {
		foreach ( $new_options as $group => $keys ) {
			if ( ! is_array( $keys ) ) {
				continue;
			}

			foreach ( $keys as $option_name => $option_value ) {
				$option_key = "$group.$option_name";
				$old_value  = $old_options[ $group ][ $option_name ] ?? null;

				// Run after_save hooks.
				$this->processor_registry->after_save(
					$option_key,
					$option_value,
					$old_value
				);
			}
		}
	}

	/**
	 * Coerce value to expected type
	 *
	 * @param mixed  $value Value to coerce.
	 * @param string $type Expected type.
	 * @return mixed Coerced value.
	 */
	private function coerce_type( $value, string $type ) {
		switch ( $type ) {
			case 'integer':
				return (int) $value;
			case 'boolean':
				// Handle string booleans from frontend.
				if ( $value === 'true' || $value === '1' || $value === 1 ) {
					return true;
				}
				if ( $value === 'false' || $value === '0' || $value === 0 ) {
					return false;
				}
				return (bool) $value;
			case 'string':
				return (string) $value;
			case 'array':
				return is_array( $value ) ? $value : [];
			default:
				return $value;
		}
	}

	/**
	 * Merge recursively, including a proper substitution of values in sub-arrays when keys are the same.
	 *
	 * @return array<string, mixed>
	 */
	public static function array_merge_recursive(): array {

		$arrays = func_get_args();

		if ( count( $arrays ) < 2 ) {
			return $arrays[0] ?? [];
		}

		$merged = [];

		while ( $arrays ) {
			$array = array_shift( $arrays );

			if ( ! is_array( $array ) ) {
				return [];
			}

			if ( empty( $array ) ) {
				continue;
			}

			foreach ( $array as $key => $value ) {
				if ( is_string( $key ) ) {
					if (
						is_array( $value ) &&
						array_key_exists( $key, $merged ) &&
						is_array( $merged[ $key ] )
					) {
						$merged[ $key ] = call_user_func( __METHOD__, $merged[ $key ], $value );
					} else {
						$merged[ $key ] = $value;
					}
				} else {
					$merged[] = $value;
				}
			}
		}

		return $merged;
	}

	/**
	 * Get all the options, but without stripping the slashes.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_raw(): array {

		$options = $this->options;

		foreach ( $options as $group => $g_value ) {
			foreach ( $g_value as $key => $value ) {
				$options[ $group ][ $key ] = $this->get( $group, $key, false );
			}
		}

		return $options;
	}
}
