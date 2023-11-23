<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit;

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
	 * @var array
	 */
	private $options;

	/**
	 * Map of all the default options
	 *
	 * @var array
	 */
	private static $map = [
		'general'      => [
			'gtm_id'                  => [
				'default'  => '',
				'constant' => 'GTMKIT_CONTAINER_ID',
			],
			'script_implementation'   => [ 'default' => 0 ],
			'noscript_implementation' => [ 'default' => 0 ],
			'container_active'        => [
				'default'  => true,
				'constant' => 'GTMKIT_CONTAINER_ACTIVE',
				'type'     => 'boolean',
			],
			'sgtm_domain'             => [ 'default' => '' ],
			'console_log'             => [
				'default'  => false,
				'constant' => 'GTMKIT_CONSOLE_LOG',
				'type'     => 'boolean',
			],
			'gtm_auth'                => [
				'default'  => '',
				'constant' => 'GTMKIT_AUTH',
			],
			'gtm_preview'             => [
				'default'  => '',
				'constant' => 'GTMKIT_PREVIEW',
			],
		],
		'integrations' => [
			'woocommerce_shipping_info'             => [ 'default' => 1 ],
			'woocommerce_payment_info'              => [ 'default' => 1 ],
			'woocommerce_variable_product_tracking' => [ 'default' => 0 ],
			'woocommerce_view_item_list_limit'      => [ 'default' => 0 ],
			'woocommerce_debug_track_purchase'      => [
				'default'  => false,
				'constant' => 'GTMKIT_WC_DEBUG_TRACK_PURCHASE',
				'type'     => 'boolean',
			],
			'cf7_load_js'                           => [ 'default' => 1 ],
			'edd_debug_track_purchase'              => [
				'default'  => false,
				'constant' => 'GTMKIT_EDD_DEBUG_TRACK_PURCHASE',
				'type'     => 'boolean',
			],
		],
	];

	/**
	 * Construct
	 */
	public function __construct() {
		$this->options = \get_option( self::OPTION_NAME, [] );

		\add_filter( 'pre_update_option_gtmkit', [ $this, 'pre_update_option' ], 10, 2 );
	}

	/**
	 * Initialize options
	 *
	 * @return Options
	 * @example Options::init()->get('general', 'gtm_id');
	 */
	public static function init(): self {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Pre update option
	 *
	 * @param mixed $new_value The new value.
	 * @param mixed $old_value The old value.
	 *
	 * @return array|null
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
	 * @param bool $flat Flattens the default settings for first install.
	 *
	 * @return array
	 */
	public static function get_defaults( bool $flat = false ): array {

		$map = self::$map;

		if ( $flat === true ) {
			$defaults = [];
			foreach ( $map as $group => $settings ) {
				foreach ( $settings as $key => $option ) {
					$defaults[ $group ][ $key ] = $option['default'];
				}
			}
			return $defaults;
		}

		return $map;
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
	 * @return array|null
	 */
	protected function get_default_key_value( string $group, string $key ): ?array {
		$defaults = $this->get_defaults();
		return $defaults[ $group ][ $key ] ?? null;
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
	 * @param array $options Plugin options.
	 * @param bool  $first_install Add option on first install.
	 * @param bool  $overwrite_existing Overwrite existing settings or merge.
	 */
	public function set( array $options, bool $first_install = false, bool $overwrite_existing = true ): void {

		if ( ! $overwrite_existing ) {
			$options = self::array_merge_recursive( $this->get_all_raw(), $options );
		}

		$options = $this->process_generic_options( $options );

		// Whether to update existing options or to add these options only once if they don't exist yet.
		if ( $first_install ) {
			\add_option( self::OPTION_NAME, $options, '', true );
		} elseif ( is_multisite() ) {
				\update_blog_option( get_main_site_id(), self::OPTION_NAME, $options );
		} else {
			\update_option( self::OPTION_NAME, $options, true );
		}

		// Now we need to re-cache values.
		wp_cache_delete( self::OPTION_NAME, 'options' );
		$this->options = get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Process the generic plugin options.
	 *
	 * @param array $options The options array.
	 *
	 * @return array
	 */
	private function process_generic_options( array $options ): array {

		foreach ( $options as $group => $keys ) {
			foreach ( $keys as $option_name => $option_value ) {
				switch ( $group ) {
					case 'general':
						if ( $option_name === 'gtm_id' ) {
							$options[ $group ][ $option_name ] = \sanitize_text_field( $option_value );
						}
						break;

					case 'debug_events':
						if ( $option_name === 'email_debug' ) {
							$options[ $group ][ $option_name ] = (bool) $option_value;
						}
				}
			}
		}

		if ( ! isset( $options['integrations'] ) ) {
			$options['integrations'] = [];
		}

		return $options;
	}

	/**
	 * Merge recursively, including a proper substitution of values in sub-arrays when keys are the same.
	 *
	 * @return array
	 */
	public static function array_merge_recursive(): array {

		$arrays = func_get_args();

		if ( count( $arrays ) < 2 ) {
			return $arrays[0] ?? array();
		}

		$merged = array();

		while ( $arrays ) {
			$array = array_shift( $arrays );

			if ( ! is_array( $array ) ) {
				return array();
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
	 * @return array
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
