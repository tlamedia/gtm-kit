<?php

namespace TLA_Media\GTM_Kit;


class Options {

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
			'container_active',
			'script_implementation',
			'noscript_implementation',
		],
		'integrations' => [
			'woocommerce_shipping_info',
			'woocommerce_payment_info',
		],

	];

	/**
	 * Construct
	 */
	public function __construct() {
		$this->options = get_option( self::OPTION_NAME, [] );

		add_filter( 'pre_update_option_gtmkit', [ $this, 'pre_update_option' ], 10, 2 );
	}

	function pre_update_option( $new_value, $old_value ): array {

		if ( ! is_array( $new_value ) ) {
			return $new_value;
		}

		foreach ( $new_value as $group => $settings ) {
			$old_value[ $group ] = [];
			$old_value[ $group ] = $settings;
		}

		return $old_value;
	}

	/**
	 * Initialize options
	 *
	 * @return Options
	 * @example Options::init()->get('general', 'gtm_id');
	 *
	 */
	public static function init(): Options {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Default options that are saved on first installation.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {

		return [
			'general' => [
				'script_implementation'   => '0',
				'noscript_implementation' => '0',
				'container_active'        => 'on',
			]
		];
	}

	/**
	 * Get options by a group and a key.
	 *
	 * @param string $group The option group.
	 * @param string $key The option key.
	 * @param bool $strip_slashes If the slashes should be stripped from string values.
	 *
	 * @return mixed|null Null if value doesn't exist anywhere: in constants, in DB, in a map. So it's completely custom or a typo.
	 * @example Options::init()->get( 'general', 'gtm_id' ).
	 *
	 */
	public function get( string $group, string $key, bool $strip_slashes = true ) {

		$value = null;

		$value = $this->get_const_value( $group, $key, $value );

		if ( $value === null ) {
			// Ordinary database or default values.
			if ( isset( $this->options[ $group ] ) ) {
				$value = $this->options[ $group ][ $key ] ?? $this->postprocess_key_defaults( $group, $key );
			} else {
				if (
					isset( self::$map[ $group ] ) &&
					in_array( $key, self::$map[ $group ], true )
				) {
					$value = $this->postprocess_key_defaults( $group, $key );
				}
			}
		}

		// Conditionally strip slashes only from values saved in DB. Constants should be processed as is.
		if ( $strip_slashes && is_string( $value ) && ! $this->is_const_defined( $group, $key ) ) {
			$value = stripslashes( $value );
		}

		return $value;
	}

	/**
	 * Postprocess options.
	 *
	 * @param string $group
	 * @param string $key
	 *
	 * @return mixed
	 */
	protected function postprocess_key_defaults( string $group, string $key ) {

		$value = '';

		switch ( $key ) {
			case 'woocommerce_shipping_info':
			case 'woocommerce_payment_info':
				$value = '1';
				break;
		}

		return $value;
	}

	/**
	 * Get constant value if defined.
	 *
	 * @param string $group
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	protected function get_const_value( string $group, string $key, $value ) {

		if ( ! $this->is_const_enabled() ) {
			return $value;
		}

		$return = null;

		switch ( $group ) {
			case 'general':
				switch ( $key ) {
					case 'gtm_id':
						$return = $this->is_const_defined( $group, $key ) ? GTMKIT_CONTAINER_ID : $value;
						break;
					case 'container_active':
						$return = $this->is_const_defined( $group, $key ) ? GTMKIT_CONTAINER_ACTIVE : $value;
						break;
				}
				break;
			default:
				$return = $value;
		}

		return $return;
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
	 * Is constant defined.
	 *
	 * @param string $group
	 * @param string $key
	 *
	 * @return bool
	 */
	public function is_const_defined( string $group, string $key ): bool {

		if ( ! $this->is_const_enabled() ) {
			return false;
		}

		$return = false;

		switch ( $group ) {
			case 'general':
				switch ( $key ) {
					case 'gtm_id':
						$return = defined( 'GTMKIT_CONTAINER_ID' ) && GTMKIT_CONTAINER_ID;
						break;
					case 'container_active':
						$return = defined( 'GTMKIT_CONTAINER_ACTIVE' ) && ( GTMKIT_CONTAINER_ACTIVE === false || GTMKIT_CONTAINER_ACTIVE === true );
						break;
				}

				break;
		}

		return $return;
	}

	/**
	 * Set plugin options.
	 *
	 * @param array $options Plugin options.
	 * @param bool $once Update existing options or only add once.
	 * @param bool $overwrite_existing Overwrite existing settings or merge.
	 */
	public function set( array $options, bool $once = false, bool $overwrite_existing = true ): void {

		if ( ! $overwrite_existing ) {
			$options = self::array_merge_recursive( $this->get_all_raw(), $options );
		}

		$options = $this->process_genericoptions( $options );

		// Whether to update existing options or to add these options only once if they don't exist yet.
		if ( $once ) {
			add_option( self::OPTION_NAME, $options, '', 'no' ); // Do not autoload these options.
		} else {
			if ( is_multisite() && WP::use_global_plugin_settings() ) {
				update_blog_option( get_main_site_id(), self::OPTION_NAME, $options );
			} else {
				update_option( self::OPTION_NAME, $options, 'no' );
			}
		}

		// Now we need to re-cache values.
		$this->options = get_option( self::OPTION_NAME, [] );

	}

	/**
	 * Process the generic plugin options.
	 *
	 * @param array $options The options array.
	 *
	 * @return array
	 */
	private function process_genericoptions( array $options ): array {

		foreach ( (array) $options as $group => $keys ) {
			foreach ( $keys as $option_name => $option_value ) {
				switch ( $group ) {
					case 'general':
						switch ( $option_name ) {
							case 'gtm_id':
								$options[ $group ][ $option_name ] = sanitize_text_field( $option_value );
								break;
						}
						break;

					case 'debug_events':
						switch ( $option_name ) {
							case 'email_debug':
								$options[ $group ][ $option_name ] = (bool) $option_value;
								break;
						}
				}
			}
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
